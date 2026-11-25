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

		foreach (array_filter($parts, fn ($name) => str_ends_with($name, '.xml') || str_ends_with($name, '.rels'), ARRAY_FILTER_USE_KEY) as $name => $xml) {
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

		$this->assertStringContainsString('.gst-bg{position:fixed;top:-20.0mm;left:-20.0mm;width:210mm;height:297mm;z-index:-1}', $html);
		$this->assertLessThan(strpos($html, '<p>a</p>'), strpos($html, '<div class="gst-bg"><img src="data:image/png;base64,AAAA"'));
		$this->assertStringNotContainsString('gst-bg', PdfExporter::html($regions, 'T'));
	}

	public function test_page_setup_validates_and_moves_body_below_tall_header(): void
	{
		$page = \App\Guesstimate\Contracts\PageSetup::from(['top' => 45, 'header' => 10, 'left' => 'x', 'bottom' => 500]);

		$this->assertSame(45.0, $page->bodyTop(20.0), 'niski nagłówek: treść na marginesie');
		$this->assertSame(40.0 + 10, $page->bodyTop(40.0), 'wysoki nagłówek: treść pod nim');
		$this->assertSame(45.0, $page->bodyTop(0.0), 'bez nagłówka: margines');
		$this->assertSame(20.0, $page->get('left'), 'błędna wartość wraca do domyślnej');
		$this->assertSame(20.0, $page->get('bottom'), 'wartość ponad limit wraca do domyślnej');
		$this->assertStringContainsString('--ct-top: 45mm;', $page->css());

		$result = \App\Guesstimate\Contracts\PageSetup::validate(['top' => '12,5', 'bottom' => '-1', 'left' => '20', 'right' => '20', 'header' => '10', 'footer' => 'abc']);
		$this->assertSame(12.5, $result['values']['top']);
		$this->assertArrayHasKey('bottom', $result['errors']);
		$this->assertArrayHasKey('footer', $result['errors']);
	}

	public function test_pdf_uses_page_setup(): void
	{
		$page = \App\Guesstimate\Contracts\PageSetup::from(['top' => 40, 'bottom' => 30, 'left' => 25, 'right' => 15, 'header' => 10, 'footer' => 12]);
		$html = PdfExporter::html($this->regions('<p>a</p>', '<p>H</p>', '<p>F</p>'), 'T', 0, ['top' => 8.0, 'bottom' => 6.0], 'data:image/png;base64,AAAA', $page);

		$this->assertStringContainsString('@page{size:A4 portrait;margin:40.0mm 15.0mm 30.0mm 25.0mm}', $html);
		$this->assertStringContainsString('.gst-header{position:fixed;top:-30.0mm;', $html);
		$this->assertStringContainsString('.gst-footer{position:fixed;bottom:-18.0mm;', $html);
		$this->assertStringContainsString('.gst-bg{position:fixed;top:-40.0mm;left:-25.0mm;', $html);
	}

	public function test_docx_embeds_font_and_background(): void
	{
		$image = tempnam(sys_get_temp_dir(), 'gst-bg') . '.png';
		$gd = imagecreatetruecolor(4, 6);
		imagepng($gd, $image);
		$parts = $this->unzip((new DocxExporter())->render($this->regions('<p>a</p>', '<p>Nagłówek</p>'), 'T', $image));
		@unlink($image);

		$this->assertArrayHasKey('word/media/background.png', $parts);
		$this->assertStringContainsString('behindDoc="1"', $parts['word/header1.xml']);
		$this->assertStringContainsString('<a:blip r:embed="rIdBg"/>', $parts['word/header1.xml']);
		$this->assertStringContainsString('Nagłówek', $parts['word/header1.xml']);
		$this->assertStringContainsString('media/background.png', $parts['word/_rels/header1.xml.rels']);

		foreach (['word/header1.xml', 'word/fontTable.xml', 'word/settings.xml', '[Content_Types].xml', 'word/_rels/document.xml.rels'] as $part) {
			$this->assertNotFalse(@simplexml_load_string($parts[$part]), $part . ' to poprawny XML');
		}

		// Osadzony krój: po odszyfrowaniu pierwszych 32 bajtów kluczem z GUID plik jest z powrotem TTF.
		$this->assertMatchesRegularExpression('~<w:embedRegular r:id="rIdF1" w:fontKey="\{[0-9A-F-]{36}\}"/>~', $parts['word/fontTable.xml']);
		preg_match('~w:fontKey="\{([0-9A-F-]{36})\}"~', $parts['word/fontTable.xml'], $match);
		$key = strrev(hex2bin(str_replace('-', '', $match[1])));
		$font = $parts['word/fonts/font1.odttf'];

		for ($i = 0; $i < 32; $i++) {
			$font[$i] = $font[$i] ^ $key[$i % 16];
		}

		$this->assertSame(file_get_contents(dirname(__DIR__, 3) . '/resources/fonts/dejavu/DejaVuSans.ttf'), $font);
		$this->assertStringContainsString('w:ascii="DejaVu Sans"', $parts['word/styles.xml']);
	}

	public function test_breaks_from_panel_are_parsed_strictly(): void
	{
		$this->assertSame([[3, 0], [7, 2]], ContractExport::breaks('3, 7.2,x,-1,4.5.6,'));
		$this->assertSame([], ContractExport::breaks(''));
	}

	public function test_panel_breaks_become_hard_page_breaks_without_changing_text(): void
	{
		$html = '<p>Zażółć</p><table><thead><tr><th>H</th></tr></thead><tbody><tr><td>1</td></tr><tr><td>2</td></tr><tr><td>3</td></tr></tbody></table><ol><li>a</li><li>b</li></ol><hr class="ct-break"><p>Po</p>';
		$out = ContractExport::paginate($html, [[1, 2], [2, 1], [4, 0], [9, 0]]);

		// Ten sam tekst; jedyny dodatek to powtórzony nagłówek tabeli na nowej stronie (jak w panelu).
		$this->assertSame('ZażółćH12H3abPo', strip_tags($out));
		$this->assertStringContainsString('<tr><td>2</td></tr></tbody></table><hr class="ct-break"><table><thead><tr><th>H</th></tr></thead><tbody><tr><td>3</td></tr></tbody></table>', $out);
		$this->assertStringContainsString('<li>a</li></ol><hr class="ct-break"><ol start="2"><li>b</li></ol>', $out);
		$this->assertSame(1, substr_count($out, '<hr class="ct-break"><p>Po</p>'), 'ręczny podział nie jest dublowany');
		$this->assertSame(3, substr_count($out, 'ct-break'));
		$this->assertSame($html, ContractExport::paginate($html, []));
	}

	public function test_pdf_line_height_matches_panel(): void
	{
		// Dompdf mnoży line-height przez wysokość kroju; po korekcie wiersz 10 pt ma 14 pt jak w przeglądarce.
		preg_match('~body\{[^}]*line-height:([0-9.]+)~', PdfExporter::html($this->regions('<p>a</p>'), 'T'), $match);

		$this->assertEqualsWithDelta(1.09, (float) $match[1], 0.02);
	}

	public function test_docx_spacing_does_not_double_and_lists_are_tight(): void
	{
		$parts = $this->docx($this->regions('<ul><li>a</li><li>b</li></ul>'));

		$this->assertStringContainsString('<w:pStyle w:val="ListText"/>', $parts['word/document.xml']);
		$this->assertStringContainsString('<w:contextualSpacing/>', $parts['word/styles.xml']);
		$this->assertStringContainsString('w:styleId="ContractPar"><w:name w:val="Paragraf umowy"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="85"', $parts['word/styles.xml']);
	}

	public function test_docx_drops_trailing_line_break_like_browser(): void
	{
		$xml = $this->docx($this->regions('<p><br></p><p>a<br>b<br></p><p><strong>c<br></strong></p>'))['word/document.xml'];

		$this->assertStringContainsString('<w:p></w:p>', $xml, 'pusty akapit z <br> ma jeden wiersz');
		$this->assertSame(1, substr_count($xml, '<w:br/>'), 'zostaje tylko łamanie w środku akapitu');
	}

	public function test_docx_page_break_starts_next_paragraph_without_empty_line(): void
	{
		$xml = $this->docx($this->regions('<p>a</p><hr class="ct-break"><p class="ct-par">P</p><hr class="ct-break"><p>b</p>'))['word/document.xml'];

		$this->assertStringContainsString('<w:p><w:pPr><w:pStyle w:val="ContractPar"/><w:pageBreakBefore/></w:pPr>', $xml);
		$this->assertStringContainsString('<w:p><w:pPr><w:pageBreakBefore/></w:pPr>', $xml);
		$this->assertStringNotContainsString('<w:br w:type="page"/>', $xml, 'bez akapitu ze znakiem podziału');
	}

	public function test_docx_uses_panel_measures(): void
	{
		$parts = $this->docx($this->regions('<p>a</p>'));

		$this->assertStringContainsString('<w:sz w:val="20"/>', $parts['word/styles.xml']);
		$this->assertStringContainsString('w:line="280" w:lineRule="exact"', $parts['word/styles.xml']);
		$this->assertStringContainsString('w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="1134"', $parts['word/document.xml']);
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
		$this->assertStringContainsString('</w:tbl><w:p><w:pPr><w:spacing w:after="0" w:line="142" w:lineRule="exact"/></w:pPr></w:p></w:tc>', $xml);
	}
}
