<?php

namespace App\Guesstimate\Contracts;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * DOCX z gotowych części dokumentu (ContractExport::compose). Własny zapis OOXML zamiast konwersji HTML z biblioteki:
 * każdy węzeł tekstu jest przenoszony wprost, więc nic z treści prawnej nie ginie. Kolejność elementów w `w:pPr`,
 * `w:rPr` i `w:tblPr` wynika ze schematu; Word odrzuca albo „naprawia” plik przy innej.
 */
final class DocxExporter
{
	private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

	private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

	/** Szerokość tekstu w twipach: A4 minus marginesy 20 mm. */
	private const WIDTH = 9638;

	private const BULLETS = 1;

	private const JC = ['ct-center' => 'center', 'ct-right' => 'right', 'ct-justify' => 'both'];

	/** @var list<int> pierwsze numery kolejnych list numerowanych; każda dostaje własne `w:num` */
	private array $ordered = [];

	public function render(array $regions, string $title): string
	{
		$header = $this->part($regions['header']);
		$footer = $this->part($regions['footer']);
		$body = $this->part($regions['body']);
		$section = '<w:sectPr><w:headerReference w:type="default" r:id="rId3"/><w:footerReference w:type="default" r:id="rId4"/>'
			. '<w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr>';
		$parts = [
			'[Content_Types].xml' => self::contentTypes(),
			'_rels/.rels' => self::rels(),
			'docProps/core.xml' => self::core($title),
			'word/document.xml' => self::XML . '<w:document ' . self::NS . '><w:body>' . $body . $section . '</w:body></w:document>',
			'word/header1.xml' => self::XML . '<w:hdr ' . self::NS . '>' . ($header ?: '<w:p/>') . '</w:hdr>',
			'word/footer1.xml' => self::XML . '<w:ftr ' . self::NS . '>' . ($footer ?: '<w:p/>') . '</w:ftr>',
			'word/styles.xml' => self::styles(),
			'word/numbering.xml' => $this->numbering(),
			'word/_rels/document.xml.rels' => self::documentRels(),
		];

		return self::zip($parts);
	}

	private function part(string $html): string
	{
		if (trim($html) === '') {
			return '';
		}

		$document = new DOMDocument('1.0', 'UTF-8');
		$previous = libxml_use_internal_errors(true);
		$document->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		return $this->blocks($document->getElementsByTagName('body')->item(0));
	}

	private function blocks(DOMNode $parent, array $cell = []): string
	{
		$xml = '';
		$inline = [];
		$flush = function () use (&$inline, &$xml, $cell) {
			if (self::hasText($inline)) {
				$xml .= self::paragraph($this->runs($inline, $cell), $cell['style'] ?? null, $cell['jc'] ?? null);
			}

			$inline = [];
		};

		foreach (iterator_to_array($parent->childNodes) as $node) {
			$tag = $node instanceof DOMElement ? strtolower($node->tagName) : '';

			if (! in_array($tag, ['p', 'h1', 'h2', 'h3', 'ul', 'ol', 'table', 'hr'], true)) {
				$inline[] = $node;
				continue;
			}

			$flush();
			$xml .= match ($tag) {
				'ul', 'ol' => $this->listing($node, 0),
				'table' => $this->table($node),
				'hr' => '<w:p><w:r><w:br w:type="page"/></w:r></w:p>',
				default => $this->block($node, $cell),
			};
		}

		$flush();

		return $xml;
	}

	private function block(DOMElement $node, array $cell, string $numbering = ''): string
	{
		$tag = strtolower($node->tagName);
		$classes = preg_split('/\s+/', (string) $node->getAttribute('class')) ?: [];
		$style = ['h1' => 'Heading1', 'h2' => 'Heading2', 'h3' => 'Heading3'][$tag] ?? $cell['style'] ?? null;
		$style = in_array('ct-par', $classes, true) ? 'ContractPar' : (in_array('ct-small', $classes, true) ? 'Small' : $style);
		$jc = $cell['jc'] ?? null;

		foreach (self::JC as $class => $value) {
			$jc = in_array($class, $classes, true) ? $value : $jc;
		}

		return self::paragraph($this->runs(iterator_to_array($node->childNodes), $cell), $style, $jc, $numbering);
	}

	private static function paragraph(string $runs, ?string $style, ?string $jc, string $numbering = ''): string
	{
		$properties = ($style ? "<w:pStyle w:val=\"{$style}\"/>" : '') . $numbering . ($jc ? "<w:jc w:val=\"{$jc}\"/>" : '');

		return '<w:p>' . ($properties ? "<w:pPr>{$properties}</w:pPr>" : '') . $runs . '</w:p>';
	}

	private function runs(array $nodes, array $format): string
	{
		$xml = '';

		foreach ($nodes as $node) {
			if ($node->nodeType === XML_TEXT_NODE) {
				$xml .= self::run('<w:t xml:space="preserve">' . self::escape($node->nodeValue) . '</w:t>', $format);
			} elseif ($node instanceof DOMElement) {
				$tag = strtolower($node->tagName);
				$field = ['page.number' => 'PAGE', 'page.count' => 'NUMPAGES'][$node->getAttribute('data-var')] ?? null;

				if ($field) {
					$xml .= "<w:fldSimple w:instr=\" {$field} \"><w:r><w:t>1</w:t></w:r></w:fldSimple>";
				} elseif ($tag === 'br') {
					$xml .= self::run('<w:br/>', $format);
				} else {
					$xml .= $this->runs(iterator_to_array($node->childNodes), array_merge($format, [
						'b' => ($format['b'] ?? false) || in_array($tag, ['strong', 'b'], true),
						'i' => ($format['i'] ?? false) || in_array($tag, ['em', 'i'], true),
						'u' => ($format['u'] ?? false) || $tag === 'u',
					]));
				}
			}
		}

		return $xml;
	}

	private static function run(string $content, array $format): string
	{
		$properties = (($format['b'] ?? false) ? '<w:b/>' : '') . (($format['i'] ?? false) ? '<w:i/>' : '') . (($format['u'] ?? false) ? '<w:u w:val="single"/>' : '');

		return '<w:r>' . ($properties ? "<w:rPr>{$properties}</w:rPr>" : '') . $content . '</w:r>';
	}

	/** Punkt listy może mieć listę zagnieżdżoną i kilka akapitów: numer dostaje pierwszy z nich, tekst zostaje cały. */
	private function listing(DOMElement $list, int $level): string
	{
		$number = self::BULLETS;

		if (strtolower($list->tagName) === 'ol') {
			$this->ordered[] = max(1, (int) ($list->getAttribute('start') ?: 1));
			$number = self::BULLETS + count($this->ordered);
		}

		$numbering = sprintf('<w:numPr><w:ilvl w:val="%d"/><w:numId w:val="%d"/></w:numPr>', min($level, 2), $number);
		$xml = '';

		foreach ($list->childNodes as $item) {
			if (! $item instanceof DOMElement) {
				continue;
			}

			$first = $numbering;
			$inline = [];

			foreach (iterator_to_array($item->childNodes) as $node) {
				$tag = $node instanceof DOMElement ? strtolower($node->tagName) : '';

				if (in_array($tag, ['ul', 'ol', 'p'], true) && self::hasText($inline)) {
					$xml .= self::paragraph($this->runs($inline, []), null, null, $first);
					$first = '';
				}

				if (in_array($tag, ['ul', 'ol', 'p'], true)) {
					$inline = [];
				}

				if ($tag === 'ul' || $tag === 'ol') {
					$xml .= $this->listing($node, $level + 1);
				} elseif ($tag === 'p') {
					$xml .= $this->block($node, [], $first);
					$first = '';
				} else {
					$inline[] = $node;
				}
			}

			if (self::hasText($inline)) {
				$xml .= self::paragraph($this->runs($inline, []), null, null, $first);
			}
		}

		return $xml;
	}

	private static function hasText(array $nodes): bool
	{
		return trim(implode('', array_map(fn ($node) => $node->textContent, $nodes))) !== '';
	}

	private function table(DOMElement $table): string
	{
		$rows = iterator_to_array((new DOMXPath($table->ownerDocument))->query('./tr|./thead/tr|./tbody/tr', $table));
		$columns = 1;

		foreach ($rows as $row) {
			$span = 0;

			foreach ($row->childNodes as $cell) {
				$span += $cell instanceof DOMElement ? max(1, (int) $cell->getAttribute('colspan')) : 0;
			}

			$columns = max($columns, $span);
		}

		$width = (int) floor(self::WIDTH / $columns);
		$border = fn ($side) => "<w:{$side} w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"888888\"/>";
		$xml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>' . implode('', array_map($border, ['top', 'left', 'bottom', 'right', 'insideH', 'insideV'])) . '</w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>'
			. str_repeat("<w:gridCol w:w=\"{$width}\"/>", $columns) . '</w:tblGrid>';

		$merged = [];

		foreach ($rows as $row) {
			$head = strtolower($row->parentNode->nodeName) === 'thead';
			$xml .= '<w:tr><w:trPr><w:cantSplit/>' . ($head ? '<w:tblHeader/>' : '') . '</w:trPr>';
			$column = 0;

			foreach ($row->childNodes as $cell) {
				if ($cell instanceof DOMElement) {
					$xml .= self::continuations($merged, $column, $width, false);
					$xml .= $this->cell($cell, $width, $merged, $column);
				}
			}

			$xml .= self::continuations($merged, $column, $width, true) . '</w:tr>';
		}

		// Dwie tabele bez akapitu między nimi Word skleja w jedną.
		return $xml . '</w:tbl><w:p><w:pPr><w:pStyle w:val="TableText"/></w:pPr></w:p>';
	}

	/**
	 * Word nie ma odpowiednika rowspan: scalona kolumna potrzebuje w każdym kolejnym wierszu pustej komórki `vMerge`.
	 *
	 * @param array<int, array{rows: int, span: int}> $merged otwarte scalenia wg kolumny
	 */
	private static function continuations(array &$merged, int &$column, int $width, bool $all): string
	{
		$xml = '';

		while ($all ? array_filter(array_keys($merged), fn ($key) => $key >= $column) : isset($merged[$column])) {
			$column = $all ? min(array_filter(array_keys($merged), fn ($key) => $key >= $column)) : $column;
			['rows' => $rows, 'span' => $span] = $merged[$column];
			$xml .= '<w:tc><w:tcPr><w:tcW w:w="' . ($width * $span) . '" w:type="dxa"/>' . ($span > 1 ? "<w:gridSpan w:val=\"{$span}\"/>" : '') . '<w:vMerge/></w:tcPr><w:p><w:pPr><w:pStyle w:val="TableText"/></w:pPr></w:p></w:tc>';

			if ($rows > 1) {
				$merged[$column]['rows'] = $rows - 1;
			} else {
				unset($merged[$column]);
			}

			$column += $span;
		}

		return $xml;
	}

	private function cell(DOMElement $cell, int $width, array &$merged, int &$column): string
	{
		$span = max(1, (int) $cell->getAttribute('colspan'));
		$rows = max(1, (int) $cell->getAttribute('rowspan'));

		if ($rows > 1) {
			$merged[$column] = ['rows' => $rows - 1, 'span' => $span];
		}

		$column += $span;
		$classes = preg_split('/\s+/', (string) $cell->getAttribute('class')) ?: [];
		$jc = null;

		foreach (self::JC as $class => $value) {
			$jc = in_array($class, $classes, true) ? $value : $jc;
		}

		$content = $this->blocks($cell, ['style' => 'TableText', 'jc' => $jc, 'b' => strtolower($cell->tagName) === 'th']);

		return '<w:tc><w:tcPr><w:tcW w:w="' . ($width * $span) . '" w:type="dxa"/>' . ($span > 1 ? "<w:gridSpan w:val=\"{$span}\"/>" : '') . ($rows > 1 ? '<w:vMerge w:val="restart"/>' : '') . '</w:tcPr>'
			. ($content ?: '<w:p><w:pPr><w:pStyle w:val="TableText"/></w:pPr></w:p>') . '</w:tc>';
	}

	private static function escape(string $text): string
	{
		$legal = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);

		return htmlspecialchars($legal, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function numbering(): string
	{
		$levels = fn (string $format, string $text) => implode('', array_map(
			fn ($level) => sprintf('<w:lvl w:ilvl="%1$d"><w:start w:val="1"/><w:numFmt w:val="%2$s"/><w:lvlText w:val="%3$s"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="%4$d" w:hanging="340"/></w:pPr></w:lvl>', $level, $format, $format === 'bullet' ? $text : '%' . ($level + 1) . '.', 340 * ($level + 1)),
			[0, 1, 2],
		));
		$xml = self::XML . '<w:numbering ' . self::NS . '>'
			. '<w:abstractNum w:abstractNumId="0"><w:multiLevelType w:val="multilevel"/>' . $levels('bullet', '•') . '</w:abstractNum>'
			. '<w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="multilevel"/>' . $levels('decimal', '') . '</w:abstractNum>'
			. '<w:num w:numId="' . self::BULLETS . '"><w:abstractNumId w:val="0"/></w:num>';

		foreach ($this->ordered as $i => $start) {
			$xml .= sprintf('<w:num w:numId="%d"><w:abstractNumId w:val="1"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="%d"/></w:lvlOverride></w:num>', self::BULLETS + $i + 1, $start);
		}

		return $xml . '</w:numbering>';
	}

	private static function styles(): string
	{
		$style = fn (string $id, string $name, string $paragraph, string $run = '') => sprintf('<w:style w:type="paragraph" w:styleId="%1$s"><w:name w:val="%2$s"/><w:basedOn w:val="Normal"/><w:pPr>%3$s</w:pPr><w:rPr>%4$s</w:rPr></w:style>', $id, $name, $paragraph, $run);

		return self::XML . '<w:styles ' . self::NS . '><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:eastAsia="Arial" w:cs="Arial"/><w:sz w:val="21"/><w:szCs w:val="21"/><w:lang w:val="pl-PL"/></w:rPr></w:rPrDefault>'
			. '<w:pPrDefault><w:pPr><w:spacing w:after="140" w:line="288" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
			. '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
			. $style('Heading1', 'heading 1', '<w:keepNext/><w:spacing w:before="220" w:after="170"/>', '<w:b/><w:sz w:val="32"/><w:szCs w:val="32"/>')
			. $style('Heading2', 'heading 2', '<w:keepNext/><w:spacing w:before="220" w:after="140"/>', '<w:b/><w:sz w:val="26"/><w:szCs w:val="26"/>')
			. $style('Heading3', 'heading 3', '<w:keepNext/><w:spacing w:before="170" w:after="110"/>', '<w:b/><w:sz w:val="22"/><w:szCs w:val="22"/>')
			. $style('ContractPar', 'Paragraf umowy', '<w:keepNext/><w:spacing w:before="220" w:after="140"/><w:jc w:val="center"/>', '<w:b/>')
			. $style('Small', 'Drobny druk', '', '<w:sz w:val="17"/><w:szCs w:val="17"/>')
			. $style('TableText', 'Tekst tabeli', '<w:spacing w:after="0"/>', '<w:sz w:val="18"/><w:szCs w:val="18"/>')
			. '</w:styles>';
	}

	private static function contentTypes(): string
	{
		$main = 'application/vnd.openxmlformats-officedocument.wordprocessingml';
		$override = fn ($part, $type) => "<Override PartName=\"/{$part}\" ContentType=\"{$type}\"/>";

		return self::XML . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
			. $override('word/document.xml', "{$main}.document.main+xml")
			. $override('word/styles.xml', "{$main}.styles+xml")
			. $override('word/numbering.xml', "{$main}.numbering+xml")
			. $override('word/header1.xml', "{$main}.header+xml")
			. $override('word/footer1.xml', "{$main}.footer+xml")
			. $override('docProps/core.xml', 'application/vnd.openxmlformats-package.core-properties+xml')
			. '</Types>';
	}

	private static function rels(): string
	{
		return self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
			. '</Relationships>';
	}

	private static function documentRels(): string
	{
		$type = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

		return self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. "<Relationship Id=\"rId1\" Type=\"{$type}/styles\" Target=\"styles.xml\"/>"
			. "<Relationship Id=\"rId2\" Type=\"{$type}/numbering\" Target=\"numbering.xml\"/>"
			. "<Relationship Id=\"rId3\" Type=\"{$type}/header\" Target=\"header1.xml\"/>"
			. "<Relationship Id=\"rId4\" Type=\"{$type}/footer\" Target=\"footer1.xml\"/>"
			. '</Relationships>';
	}

	private static function core(string $title): string
	{
		return self::XML . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>' . self::escape($title) . '</dc:title></cp:coreProperties>';
	}

	/** Plik tymczasowy istnieje tylko na czas pakowania: ZipArchive nie umie pisać do pamięci. */
	private static function zip(array $parts): string
	{
		$path = tempnam(sys_get_temp_dir(), 'gst-docx');
		$zip = new ZipArchive();

		try {
			if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
				throw new RuntimeException('Nie można utworzyć pliku DOCX.');
			}

			foreach ($parts as $name => $content) {
				$zip->addFromString($name, $content);
			}

			$zip->close();

			return (string) file_get_contents($path);
		} finally {
			@unlink($path);
		}
	}
}
