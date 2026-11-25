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

	private const TWIPS_PER_MM = 1440 / 25.4;

	private const JC = ['ct-center' => 'center', 'ct-right' => 'right', 'ct-justify' => 'both'];

	/** @var list<int> pierwsze numery kolejnych list numerowanych; każda dostaje własne `w:num` */
	private array $ordered = [];

	/**
	 * @param  string|null  $background  plik graficzny tła stron (papier firmowy) albo null
	 */
	public function render(array $regions, string $title, ?string $background = null, ?PageSetup $page = null): string
	{
		$page ??= PageSetup::defaults();
		$header = $this->part($regions['header']);
		$footer = $this->part($regions['footer']);
		$body = $this->part($regions['body']);
		// Pole treści jak w panelu i PDF (ten sam krój, więc te same wysokości): margines albo nagłówek/stopka + odstęp.
		$space = PdfExporter::measure($regions, $page);
		$twips = fn (float $mm) => (int) round($mm * self::TWIPS_PER_MM);
		$section = '<w:sectPr><w:headerReference w:type="default" r:id="rId3"/><w:footerReference w:type="default" r:id="rId4"/>'
			. sprintf(
				'<w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="%d" w:right="%d" w:bottom="%d" w:left="%d" w:header="%d" w:footer="%d" w:gutter="0"/></w:sectPr>',
				$twips($page->bodyTop($space['top'])),
				$twips($page->get('right')),
				$twips($page->bodyBottom($space['bottom'])),
				$twips($page->get('left')),
				$twips($page->get('header')),
				$twips($page->get('footer')),
			);
		$image = $background ? self::image($background) : null;
		// Tło: obraz na całą kartkę zakotwiczony w nagłówku, za tekstem — Word powtarza go na każdej stronie.
		// Trafia do pierwszego akapitu nagłówka, a gdy go nie ma — do akapitu o zerowej wysokości.
		if ($image) {
			$header = (string) preg_replace('~^<w:p>(<w:pPr>.*?</w:pPr>)?~s', '<w:p>$1' . self::backgroundRun(), $header, 1, $count);

			if (! $count) {
				$header = '<w:p><w:pPr><w:spacing w:after="0" w:line="20" w:lineRule="exact"/></w:pPr>' . self::backgroundRun() . '</w:p>' . $header;
			}
		}

		$header = $header ?: '<w:p/>';

		$parts = [
			'[Content_Types].xml' => self::contentTypes(),
			'_rels/.rels' => self::rels(),
			'docProps/core.xml' => self::core($title),
			'word/document.xml' => self::XML . '<w:document ' . self::NS . '><w:body>' . $body . $section . '</w:body></w:document>',
			'word/header1.xml' => self::XML . '<w:hdr ' . self::NS . ' ' . self::DRAWING_NS . '>' . $header . '</w:hdr>',
			'word/footer1.xml' => self::XML . '<w:ftr ' . self::NS . '>' . ($footer ?: '<w:p/>') . '</w:ftr>',
			'word/styles.xml' => self::styles(),
			'word/settings.xml' => self::XML . '<w:settings ' . self::NS . '><w:embedTrueTypeFonts/></w:settings>',
			'word/numbering.xml' => $this->numbering(),
			'word/_rels/document.xml.rels' => self::documentRels(),
		];

		if ($image) {
			$parts['word/media/background.' . $image['ext']] = $image['bytes'];
			$parts['word/_rels/header1.xml.rels'] = self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
				. '<Relationship Id="rIdBg" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/background.' . $image['ext'] . '"/></Relationships>';
		}

		return self::zip($parts + self::fonts());
	}

	private const DRAWING_NS = 'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';

	/** A4 w EMU (1 mm = 36 000 EMU). */
	private const PAGE_EMU = [7560000, 10692000];

	/** Krój panelu i PDF. Word go zwykle nie ma, więc plik niesie go w sobie (osadzone fonty, ECMA-376 §17.8.1). */
	private const FONT = 'DejaVu Sans';

	private const FONT_FILES = [
		'embedRegular' => 'DejaVuSans.ttf',
		'embedBold' => 'DejaVuSans-Bold.ttf',
		'embedItalic' => 'DejaVuSans-Oblique.ttf',
		'embedBoldItalic' => 'DejaVuSans-BoldOblique.ttf',
	];

	private static function backgroundRun(): string
	{
		[$cx, $cy] = self::PAGE_EMU;

		return '<w:r><w:drawing><wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="0" behindDoc="1" locked="1" layoutInCell="1" allowOverlap="1">'
			. '<wp:simplePos x="0" y="0"/><wp:positionH relativeFrom="page"><wp:posOffset>0</wp:posOffset></wp:positionH><wp:positionV relativeFrom="page"><wp:posOffset>0</wp:posOffset></wp:positionV>'
			. "<wp:extent cx=\"{$cx}\" cy=\"{$cy}\"/><wp:effectExtent l=\"0\" t=\"0\" r=\"0\" b=\"0\"/><wp:wrapNone/><wp:docPr id=\"1\" name=\"Tło strony\"/><wp:cNvGraphicFramePr/>"
			. '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic><pic:nvPicPr><pic:cNvPr id="1" name="background"/><pic:cNvPicPr/></pic:nvPicPr>'
			. '<pic:blipFill><a:blip r:embed="rIdBg"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
			. "<pic:spPr><a:xfrm><a:off x=\"0\" y=\"0\"/><a:ext cx=\"{$cx}\" cy=\"{$cy}\"/></a:xfrm><a:prstGeom prst=\"rect\"><a:avLst/></a:prstGeom></pic:spPr>"
			. '</pic:pic></a:graphicData></a:graphic></wp:anchor></w:drawing></w:r>';
	}

	/**
	 * Obraz tła w formacie, który zna Word: JPG i PNG bez zmian, WebP przez GD do PNG.
	 *
	 * @return array{ext: string, bytes: string}|null
	 */
	private static function image(string $file): ?array
	{
		if (! is_readable($file)) {
			return null;
		}

		$type = function_exists('wp_check_filetype') ? (wp_check_filetype($file)['type'] ?? null) : (mime_content_type($file) ?: null);

		if ($type === 'image/jpeg' || $type === 'image/png') {
			return ['ext' => $type === 'image/png' ? 'png' : 'jpeg', 'bytes' => (string) file_get_contents($file)];
		}

		if ($type === 'image/webp' && function_exists('imagecreatefromwebp') && ($gd = @imagecreatefromwebp($file))) {
			ob_start();
			imagepng($gd);

			return ['ext' => 'png', 'bytes' => (string) ob_get_clean()];
		}

		return null;
	}

	/**
	 * Osadzone fonty: plik `.odttf` to TTF z pierwszymi 32 bajtami zaszyfrowanymi XOR kluczem z GUID (odwrócone bajty).
	 *
	 * @return array<string, string>
	 */
	private static function fonts(): array
	{
		$dir = dirname(__DIR__, 3) . '/resources/fonts/dejavu/';
		$parts = [];
		$embeds = '';
		$rels = '';
		$n = 0;

		foreach (self::FONT_FILES as $element => $file) {
			if (! is_readable($dir . $file)) {
				continue;
			}

			$n++;
			$hex = strtoupper(md5('gst-font-' . $file));
			$guid = sprintf('{%s-%s-%s-%s-%s}', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
			$key = strrev((string) hex2bin($hex));
			$bytes = (string) file_get_contents($dir . $file);

			for ($i = 0; $i < 32; $i++) {
				$bytes[$i] = $bytes[$i] ^ $key[$i % 16];
			}

			$parts["word/fonts/font{$n}.odttf"] = $bytes;
			$embeds .= "<w:{$element} r:id=\"rIdF{$n}\" w:fontKey=\"{$guid}\"/>";
			$rels .= "<Relationship Id=\"rIdF{$n}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/font\" Target=\"fonts/font{$n}.odttf\"/>";
		}

		$parts['word/fontTable.xml'] = self::XML . '<w:fonts ' . self::NS . '><w:font w:name="' . self::FONT . '"><w:family w:val="swiss"/><w:pitch w:val="variable"/>' . $embeds . '</w:font>'
			. '<w:font w:name="Arial"><w:family w:val="swiss"/><w:pitch w:val="variable"/></w:font></w:fonts>';
		$parts['word/_rels/fontTable.xml.rels'] = self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';

		return $parts;
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

		return self::breaksBefore($this->blocks($document->getElementsByTagName('body')->item(0)));
	}

	private const PAGE_BREAK = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

	/**
	 * Podział strony jako właściwość „od nowej strony” akapitu, który ją zaczyna, a nie osobny akapit ze znakiem
	 * podziału: taki akapit zostawiał pusty wiersz na górze każdej strony, a gdy Word przełamał stronę sam chwilę
	 * wcześniej — całą pustą stronę. Przed tabelą (bez akapitu) zostaje znak podziału w akapicie o zerowej wysokości.
	 */
	private static function breaksBefore(string $xml): string
	{
		$xml = (string) preg_replace_callback('~' . preg_quote(self::PAGE_BREAK, '~') . '<w:p>(?:<w:pPr>(.*?)</w:pPr>)?~s', function ($match) {
			// Kolejność w w:pPr wynika ze schematu: pStyle, keepNext, keepLines, pageBreakBefore…
			$properties = (string) preg_replace('~^((?:<w:pStyle [^>]*/>)?(?:<w:keepNext/>)?(?:<w:keepLines/>)?)~', '$1<w:pageBreakBefore/>', $match[1] ?? '', 1);

			return '<w:p><w:pPr>' . $properties . '</w:pPr>';
		}, $xml);

		return str_replace(self::PAGE_BREAK, '<w:p><w:pPr><w:spacing w:after="0" w:line="20" w:lineRule="exact"/></w:pPr><w:r><w:br w:type="page"/></w:r></w:p>', $xml);
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
				'hr' => self::PAGE_BREAK,
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
		// Przeglądarka pomija łamanie wiersza na końcu akapitu (pusty akapit z <br> to jeden wiersz), a Word dodaje
		// po nim kolejną linię. Bez tego każdy pusty akapit z edytora był w Wordzie dwa razy wyższy i strony się przelewały.
		$runs = (string) preg_replace('~(?:<w:r>(?:<w:rPr>(?:(?!</w:rPr>).)*</w:rPr>)?(?:<w:br/>|<w:t xml:space="preserve">\s*</w:t>)</w:r>)+$~s', '', $runs);

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
					$xml .= self::paragraph($this->runs($inline, []), 'ListText', null, $first);
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
				$xml .= self::paragraph($this->runs($inline, []), 'ListText', null, $first);
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
		$xml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>' . implode('', array_map($border, ['top', 'left', 'bottom', 'right', 'insideH', 'insideV'])) . '</w:tblBorders><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="85" w:type="dxa"/><w:left w:w="113" w:type="dxa"/><w:bottom w:w="85" w:type="dxa"/><w:right w:w="113" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>'
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
		// Akapit po tabeli (Word go wymaga) ma wysokość odstępu pod tabelą w panelu: 2,5 mm.
		return $xml . '</w:tbl><w:p><w:pPr><w:spacing w:after="0" w:line="142" w:lineRule="exact"/></w:pPr></w:p>';
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

		// Miary i krój jak w panelu i PDF (DejaVu Sans osadzony w pliku, 10 pt, interlinia 1,4 = stałe 14 pt, odstępy w mm),
		// więc wiersze łamią się tak samo, a twarde łamania z panelu wyznaczają podział stron.
		return self::XML . '<w:styles ' . self::NS . '><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="DejaVu Sans" w:hAnsi="DejaVu Sans" w:eastAsia="DejaVu Sans" w:cs="DejaVu Sans"/><w:sz w:val="20"/><w:szCs w:val="20"/><w:lang w:val="pl-PL"/></w:rPr></w:rPrDefault>'
			. '<w:pPrDefault><w:pPr><w:spacing w:after="142" w:line="280" w:lineRule="exact"/></w:pPr></w:pPrDefault></w:docDefaults>'
			. '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
			// Word dodaje „po” poprzedniego akapitu do „przed” kolejnego, a przeglądarka bierze większy z marginesów.
			// Dlatego „przed” to różnica do odstępu akapitu (2,5 mm = 142): 4 mm → 85, 3 mm → 28.
			. $style('Heading1', 'heading 1', '<w:keepNext/><w:spacing w:before="85" w:after="170" w:line="420" w:lineRule="exact"/>', '<w:b/><w:sz w:val="30"/><w:szCs w:val="30"/>')
			. $style('Heading2', 'heading 2', '<w:keepNext/><w:spacing w:before="85" w:after="142" w:line="336" w:lineRule="exact"/>', '<w:b/><w:sz w:val="24"/><w:szCs w:val="24"/>')
			. $style('Heading3', 'heading 3', '<w:keepNext/><w:spacing w:before="28" w:after="113"/>', '<w:b/><w:sz w:val="20"/><w:szCs w:val="20"/>')
			. $style('ContractPar', 'Paragraf umowy', '<w:keepNext/><w:spacing w:before="85" w:after="142"/><w:jc w:val="center"/>', '<w:b/>')
			. $style('ListText', 'Punkt listy', '<w:contextualSpacing/>')
			. $style('Small', 'Drobny druk', '<w:spacing w:line="224" w:lineRule="exact"/>', '<w:sz w:val="16"/><w:szCs w:val="16"/>')
			. $style('TableText', 'Tekst tabeli', '<w:spacing w:after="0" w:line="238" w:lineRule="exact"/>', '<w:sz w:val="17"/><w:szCs w:val="17"/>')
			. '</w:styles>';
	}

	private static function contentTypes(): string
	{
		$main = 'application/vnd.openxmlformats-officedocument.wordprocessingml';
		$override = fn ($part, $type) => "<Override PartName=\"/{$part}\" ContentType=\"{$type}\"/>";

		return self::XML . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
			. '<Default Extension="odttf" ContentType="application/vnd.openxmlformats-officedocument.obfuscatedFont"/><Default Extension="png" ContentType="image/png"/><Default Extension="jpeg" ContentType="image/jpeg"/>'
			. $override('word/settings.xml', "{$main}.settings+xml")
			. $override('word/fontTable.xml', "{$main}.fontTable+xml")
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
			. "<Relationship Id=\"rId5\" Type=\"{$type}/settings\" Target=\"settings.xml\"/>"
			. "<Relationship Id=\"rId6\" Type=\"{$type}/fontTable\" Target=\"fontTable.xml\"/>"
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
