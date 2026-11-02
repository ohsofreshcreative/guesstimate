<?php

namespace Tests\Unit\Contracts;

use App\Guesstimate\Contracts\DocxImporter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class DocxImporterTest extends TestCase
{
	private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"';

	private const TYPES = '<Types><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';

	/** @var list<string> */
	private array $files = [];

	protected function tearDown(): void
	{
		array_map('unlink', $this->files);
	}

	private function docx(string $body, array $parts = []): string
	{
		$path = tempnam(sys_get_temp_dir(), 'gst');
		$this->files[] = $path;
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::OVERWRITE);
		$zip->addFromString('[Content_Types].xml', self::TYPES);
		$zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document ' . self::NS . '><w:body>' . $body . '</w:body></w:document>');

		foreach ($parts as $name => $content) {
			$zip->addFromString($name, $content);
		}

		$zip->close();

		return $path;
	}

	private function read(string $body, array $parts = []): array
	{
		return (new DocxImporter())->read($this->docx($body, $parts));
	}

	private function p(string $text): string
	{
		return '<w:p><w:r><w:t xml:space="preserve">' . $text . '</w:t></w:r></w:p>';
	}

	public function test_paragraphs_headings_and_inline_formatting(): void
	{
		$styles = '<w:styles ' . self::NS . '><w:style w:styleId="T"><w:name w:val="Title"/></w:style><w:style w:styleId="Nagwek2"><w:name w:val="heading 2"/></w:style></w:styles>';
		$body = '<w:p><w:pPr><w:pStyle w:val="T"/><w:jc w:val="center"/></w:pPr><w:r><w:t>UMOWA</w:t></w:r></w:p>'
			. '<w:p><w:pPr><w:pStyle w:val="Nagwek2"/></w:pPr><w:r><w:t>Strony</w:t></w:r></w:p>'
			. '<w:p><w:r><w:rPr><w:b/></w:rPr><w:t>gruby</w:t></w:r><w:r><w:rPr><w:b w:val="0"/><w:i/><w:u w:val="single"/></w:rPr><w:t xml:space="preserve"> ozdobny</w:t></w:r></w:p>';

		$read = $this->read($body, ['word/styles.xml' => $styles]);

		$this->assertSame('<h1 class="ct-center">UMOWA</h1><h2>Strony</h2><p><strong>gruby</strong><u><em> ozdobny</em></u></p>', $read['html']);
		$this->assertSame([], $read['warnings']);
	}

	public function test_lists_follow_numbering_format_and_warn_about_numbers(): void
	{
		$numbering = '<w:numbering ' . self::NS . '><w:abstractNum w:abstractNumId="0"><w:lvl><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum><w:abstractNum w:abstractNumId="1"><w:lvl><w:numFmt w:val="decimal"/></w:lvl></w:abstractNum>'
			. '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num></w:numbering>';
		$item = fn ($id, $text, $level = 0) => "<w:p><w:pPr><w:numPr><w:ilvl w:val=\"$level\"/><w:numId w:val=\"$id\"/></w:numPr></w:pPr><w:r><w:t>$text</w:t></w:r></w:p>";

		$read = $this->read($item(1, 'a') . $item(1, 'b') . $item(2, 'c') . $item(2, 'd', 1), ['word/numbering.xml' => $numbering]);

		$this->assertSame('<ul><li>a</li><li>b</li></ul><ol><li>c</li><li>d</li></ol>', $read['html']);
		$this->assertStringContainsString('numeracją automatyczną: 4', implode(' ', $read['warnings']));
		$this->assertStringContainsString('zagnieżdżone: 1', implode(' ', $read['warnings']));
	}

	public function test_tables_with_spans(): void
	{
		$body = '<w:tbl><w:tr><w:tc><w:tcPr><w:gridSpan w:val="2"/></w:tcPr><w:p><w:r><w:t>A</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>B</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>C</w:t></w:r></w:p></w:tc></w:tr></w:tbl>';

		$this->assertSame('<table><tbody><tr><td colspan="2"><p>A</p></td></tr><tr><td><p>B</p></td><td><p>C</p></td></tr></tbody></table>', $this->read($body)['html']);
	}

	public function test_page_break_splits_the_paragraph(): void
	{
		$read = $this->read('<w:p><w:r><w:t>a</w:t><w:br w:type="page"/></w:r><w:r><w:t>b</w:t></w:r></w:p>');

		$this->assertSame('<p>a</p><hr class="ct-break"><p>b</p>', $read['html']);
	}

	public function test_tracked_deletions_are_skipped_and_reported(): void
	{
		$read = $this->read('<w:p><w:r><w:t>zostaje </w:t></w:r><w:del><w:r><w:delText>usunięte</w:delText></w:r></w:del><w:ins><w:r><w:t>wstawione</w:t></w:r></w:ins></w:p>');

		$this->assertSame('<p>zostaje wstawione</p>', $read['html']);
		$warnings = implode(' ', $read['warnings']);
		$this->assertStringContainsString('usunięcia: 1', $warnings);
		$this->assertStringContainsString('wstawienia: 1', $warnings);
	}

	public function test_lost_parts_are_reported(): void
	{
		$body = '<w:p><w:r><w:t>a</w:t><w:footnoteReference w:id="1"/><w:commentReference w:id="1"/><w:drawing><w:txbxContent><w:p><w:r><w:t>w ramce</w:t></w:r></w:p></w:txbxContent></w:drawing></w:r></w:p>';

		$read = $this->read($body, ['word/header1.xml' => '<x/>']);
		$warnings = implode(' ', $read['warnings']);

		$this->assertSame('<p>a</p>', $read['html']);

		foreach (['Przypisy: 1', 'Komentarze: 1', 'Grafiki i obiekty: 1', 'Pola tekstowe: 1', 'Nagłówki i stopki z pliku: 1'] as $expected) {
			$this->assertStringContainsString($expected, $warnings);
		}
	}

	public function test_text_is_escaped_not_treated_as_markup(): void
	{
		$this->assertSame('<p>&lt;script&gt;x&lt;/script&gt; a &amp; b</p>', $this->read($this->p('&lt;script&gt;x&lt;/script&gt; a &amp; b'))['html']);
	}

	public function test_unknown_container_stops_the_import_instead_of_losing_text(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('całego tekstu');

		$this->read('<w:unknownWrapper><w:p><w:r><w:t>Klauzula, której kod nie zna</w:t></w:r></w:p></w:unknownWrapper>');
	}

	public function test_rejects_doctype_so_xxe_cannot_work(): void
	{
		$path = tempnam(sys_get_temp_dir(), 'gst');
		$this->files[] = $path;
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::OVERWRITE);
		$zip->addFromString('[Content_Types].xml', self::TYPES);
		$zip->addFromString('word/document.xml', '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><w:document ' . self::NS . '><w:body><w:p><w:r><w:t>&e;</w:t></w:r></w:p></w:body></w:document>');
		$zip->close();

		$this->expectExceptionMessage('niedozwolone deklaracje');
		(new DocxImporter())->read($path);
	}

	public function test_rejects_macros(): void
	{
		$this->expectExceptionMessage('makra');
		$this->read($this->p('a'), ['word/vbaProject.bin' => 'x']);
	}

	public function test_rejects_zip_bomb_before_reading_it(): void
	{
		$big = tempnam(sys_get_temp_dir(), 'gst');
		$this->files[] = $big;
		$handle = fopen($big, 'w');
		ftruncate($handle, 201_000_000);
		fclose($handle);

		$path = $this->docx($this->p('a'));
		$zip = new ZipArchive();
		$zip->open($path);
		$zip->addFile($big, 'word/media/big.bin');
		$zip->close();
		$this->assertLessThan(DocxImporter::MAX_FILE, filesize($path), 'plik musi przejść limit rozmiaru, żeby test sprawdzał bombę');

		$this->expectExceptionMessage('zbyt duży');
		(new DocxImporter())->read($path);
	}

	public function test_rejects_files_that_are_not_docx(): void
	{
		$path = tempnam(sys_get_temp_dir(), 'gst');
		$this->files[] = $path;
		file_put_contents($path, 'to nie jest zip');

		$this->expectExceptionMessage('nie jest poprawny');
		(new DocxImporter())->read($path);
	}

	public function test_rejects_zip_without_word_content(): void
	{
		$path = tempnam(sys_get_temp_dir(), 'gst');
		$this->files[] = $path;
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::OVERWRITE);
		$zip->addFromString('a.txt', 'x');
		$zip->close();

		$this->expectExceptionMessage('dokument Word');
		(new DocxImporter())->read($path);
	}

	public function test_rejects_broken_xml(): void
	{
		$path = tempnam(sys_get_temp_dir(), 'gst');
		$this->files[] = $path;
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::OVERWRITE);
		$zip->addFromString('[Content_Types].xml', self::TYPES);
		$zip->addFromString('word/document.xml', '<nie-zamkniety>');
		$zip->close();

		$this->expectExceptionMessage('uszkodzony');
		(new DocxImporter())->read($path);
	}
}
