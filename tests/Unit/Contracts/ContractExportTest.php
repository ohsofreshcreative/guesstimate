<?php

namespace Tests\Unit\Contracts;

use App\Guesstimate\Contracts\ContractExport;
use App\Guesstimate\Contracts\ContractSnapshot;
use App\Guesstimate\Contracts\DocxExporter;
use App\Guesstimate\Contracts\DocxImporter;
use App\Guesstimate\Contracts\PdfExporter;
use App\Guesstimate\Contracts\TemplateDocument;
use DOMDocument;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ContractExportTest extends TestCase
{
	private function snapshot(): array
	{
		return ContractSnapshot::compose(['company_name' => 'ACME & „Syn” <sp>'], ['number' => 'OSF-1', 'currency' => 'PLN'], [], 'x');
	}

	private function token(string $key): string
	{
		return '<span class="ct-var" data-var="' . $key . '" contenteditable="false">X</span>';
	}

	private function regions(string $body = '', string $header = '', string $footer = ''): array
	{
		return ContractExport::compose(TemplateDocument::compose($header, $body, $footer), $this->snapshot());
	}

	/** @return array<string, string> */
	private function unzip(string $bytes): array
	{
		$path = tempnam(sys_get_temp_dir(), 'gst-test');
		file_put_contents($path, $bytes);
		$zip = new ZipArchive();
		$zip->open($path);
		$parts = [];

		for ($i = 0; $i < $zip->numFiles; $i++) {
			$parts[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
		}

		$zip->close();
		unlink($path);

		return $parts;
	}

	private function docx(array $regions): array
	{
		return $this->unzip((new DocxExporter())->render($regions, 'Tytuł'));
	}

	public function test_paragraph_numbers_are_real_text_in_order(): void
	{
		$regions = $this->regions('<p class="ct-par">Przedmiot</p><p>t</p><p class="ct-par ct-center">Cena</p>');

		$this->assertSame('<p class="ct-par">§ 1. Przedmiot</p><p>t</p><p class="ct-par ct-center">§ 2. Cena</p>', $regions['body']);
	}

	public function test_tokens_are_resolved_and_page_tokens_stay(): void
	{
		$regions = $this->regions('<p>' . $this->token('client.company_name') . '</p>', '', '<p>' . $this->token('page.number') . '</p>');

		$this->assertStringNotContainsString('ct-var', $regions['body']);
		$this->assertStringContainsString('ACME &amp;', $regions['body']);
		$this->assertStringContainsString('data-var="page.number"', $regions['footer']);
	}

	public function test_all_docx_parts_are_well_formed(): void
	{
		$parts = $this->docx($this->regions('<h1>T</h1><p>a &amp; b</p><ol><li>x</li></ol><table><tbody><tr><td>c</td></tr></tbody></table>', '<p>H</p>', '<p>F</p>'));

		$this->assertArrayHasKey('[Content_Types].xml', $parts);
		$this->assertArrayHasKey('word/numbering.xml', $parts);

		foreach ($parts as $name => $xml) {
			$this->assertTrue((new DOMDocument())->loadXML($xml, LIBXML_NONET), $name);
		}
	}

	public function test_docx_escapes_and_keeps_text(): void
	{
		$xml = $this->docx($this->regions('<p>' . $this->token('client.company_name') . '</p>'))['word/document.xml'];

		$this->assertStringContainsString('ACME &amp; „Syn” &lt;sp&gt;', $xml);
	}

	public function test_docx_round_trip_through_independent_reader(): void
	{
		$body = '<h1 class="ct-center">Umowa</h1><p class="ct-par">Przedmiot</p><p class="ct-justify">Zażółć <strong>gęślą</strong> <em>jaźń</em> <u>x</u></p><ul><li>punkt</li></ul><ol><li>numer</li></ol><table><tbody><tr><td colspan="2">scalona</td></tr><tr><td>a</td><td>b</td></tr></tbody></table>';
		$path = tempnam(sys_get_temp_dir(), 'gst-test');
		file_put_contents($path, (new DocxExporter())->render($this->regions($body), 'T'));

		try {
			$read = (new DocxImporter())->read($path);
		} finally {
			unlink($path);
		}

		foreach (['Umowa', '§ 1. Przedmiot', 'gęślą', 'jaźń', 'punkt', 'numer', 'scalona'] as $text) {
			$this->assertStringContainsString($text, $read['html']);
		}

		$this->assertStringContainsString('<strong>gęślą</strong>', $read['html']);
	}

	public function test_docx_page_fields_break_and_header_row(): void
	{
		$parts = $this->docx($this->regions('<p>a</p><hr class="ct-break"><table><thead><tr><th>h</th></tr></thead><tbody><tr><td>c</td></tr></tbody></table>', '', '<p>' . $this->token('page.number') . ' / ' . $this->token('page.count') . '</p>'));

		$this->assertStringContainsString('w:instr=" PAGE "', $parts['word/footer1.xml']);
		$this->assertStringContainsString('w:instr=" NUMPAGES "', $parts['word/footer1.xml']);
		$this->assertStringContainsString('<w:br w:type="page"/>', $parts['word/document.xml']);
		$this->assertStringContainsString('<w:tblHeader/>', $parts['word/document.xml']);
		$this->assertStringContainsString('<w:cantSplit/>', $parts['word/document.xml']);
	}

	public function test_docx_ordered_lists_restart_independently(): void
	{
		$parts = $this->docx($this->regions('<ol><li>a</li></ol><p>t</p><ol start="5"><li>b</li></ol>'));

		$this->assertStringContainsString('<w:numId w:val="2"/>', $parts['word/document.xml']);
		$this->assertStringContainsString('<w:numId w:val="3"/>', $parts['word/document.xml']);
		$this->assertStringContainsString('<w:startOverride w:val="5"/>', $parts['word/numbering.xml']);
	}

	public function test_docx_keeps_nested_list_and_all_item_text(): void
	{
		$xml = $this->docx($this->regions('<ul><li>a<ul><li>b</li></ul>c</li></ul>'))['word/document.xml'];

		foreach (['>a<', '>b<', '>c<', '<w:ilvl w:val="1"/>'] as $needle) {
			$this->assertStringContainsString($needle, $xml);
		}
	}

	public function test_docx_is_never_empty_of_header_paragraph(): void
	{
		$parts = $this->docx($this->regions('<p>a</p>'));

		$this->assertStringContainsString('<w:p/>', $parts['word/header1.xml']);
	}

	public function test_pdf_is_a4_with_real_page_total(): void
	{
		$regions = $this->regions('<p>a</p><hr class="ct-break"><p>b</p>', '', '<p>' . $this->token('page.number') . ' z ' . $this->token('page.count') . '</p>');
		$html = PdfExporter::html($regions, 'T', 2);
		$bytes = PdfExporter::render($regions, 'Tytuł');

		$this->assertStringStartsWith('%PDF-', $bytes);
		$this->assertStringContainsString('</span> z 2', $html);
		$this->assertStringNotContainsString('data-var', $html);
		$this->assertSame(2, preg_match_all('~/Type\s*/Page\b~', $bytes));
	}

	public function test_pdf_background_covers_every_page_under_content(): void
	{
		$regions = $this->regions('<p>a</p><hr class="ct-break"><p>b</p>');
		$html = PdfExporter::html($regions, 'T', 0, ['top' => 0.0, 'bottom' => 0.0], 'data:image/png;base64,AAAA');

		$this->assertStringContainsString('.gst-bg{position:fixed;top:-20.0mm;left:-20mm;width:210mm;height:297mm}', $html);
		$this->assertLessThan(strpos($html, '<p>a</p>'), strpos($html, '<div class="gst-bg"><img src="data:image/png;base64,AAAA"'));
		$this->assertStringNotContainsString('gst-bg', PdfExporter::html($regions, 'T'));
	}

	public function test_pdf_refuses_characters_the_font_would_lose(): void
	{
		$this->expectException(InvalidArgumentException::class);
		PdfExporter::render($this->regions('<p>😀</p>'), 'T');
	}

	public function test_pdf_html_escapes_title_and_turns_break_into_page_break(): void
	{
		$html = PdfExporter::html($this->regions('<hr class="ct-break">'), '<b>T</b>');

		$this->assertStringContainsString('&lt;b&gt;T', $html);
		$this->assertStringContainsString('gst-break', $html);
	}

	public function test_header_space_is_measured_from_its_content(): void
	{
		$short = PdfExporter::measure($this->regions('', '<p>a</p>'));
		$long = PdfExporter::measure($this->regions('', '<h1>a</h1><p>b</p><p>' . str_repeat('c ', 400) . '</p>', '<p>d</p>'));

		$this->assertGreaterThan(0, $short['top']);
		$this->assertSame(0.0, $short['bottom']);
		$this->assertGreaterThan($short['top'] + 20, $long['top']);
		$this->assertGreaterThan(0, $long['bottom']);
	}

	public function test_pdf_refuses_header_taller_than_the_page_allows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		PdfExporter::render($this->regions('<p>a</p>', str_repeat('<p>nagłówek</p>', 60)), 'T');
	}

	public function test_pdf_refuses_a_row_it_would_cut(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Wiersz tabeli');
		PdfExporter::render($this->regions('<table><tbody><tr><td>' . str_repeat('długi tekst ', 3000) . '</td></tr></tbody></table>'), 'T');
	}

	public function test_docx_row_span_keeps_every_row_on_the_grid(): void
	{
		$xml = $this->docx($this->regions('<table><tbody><tr><td rowspan="2">a</td><td>b</td></tr><tr><td>c</td></tr><tr><td>d</td><td rowspan="2">e</td></tr><tr><td>f</td></tr></tbody></table>'))['word/document.xml'];

		$this->assertSame(2, substr_count($xml, '<w:vMerge w:val="restart"/>'));
		$this->assertSame(2, substr_count($xml, '<w:vMerge/>'));

		foreach (explode('</w:tr>', $xml, -1) as $row) {
			$this->assertSame(2, substr_count($row, '<w:tc>'));
		}
	}

	public function test_docx_nested_table_stays_in_its_cell(): void
	{
		$xml = $this->docx($this->regions('<table><tbody><tr><td><table><tbody><tr><td>w</td></tr></tbody></table></td><td>x</td></tr></tbody></table>'))['word/document.xml'];

		$this->assertSame(2, substr_count($xml, '<w:tbl>'));
		$this->assertSame(2, substr_count($xml, '<w:tr>'));
		$this->assertStringContainsString('</w:tbl><w:p><w:pPr><w:pStyle w:val="TableText"/></w:pPr></w:p></w:tc>', $xml);
	}
}
