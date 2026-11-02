<?php

namespace App\Guesstimate\Contracts;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;
use ZipArchive;

/**
 * Odczyt DOCX jako punktu wyjścia szablonu. To nie jest pełna zgodność z Wordem: bierze strukturę i tekst,
 * a wszystko, czego nie przenosi, wymienia w ostrzeżeniach.
 *
 * Bezpieczeństwo: plik nie jest rozpakowywany na dysk ani zapisywany, rozmiary są sprawdzane przed odczytem,
 * XML bez DOCTYPE i encji zewnętrznych. Tekst wyniku jest porównywany z tekstem pliku; przy różnicy import jest
 * odrzucany, bo cichy ubytek w tekście umowy jest gorszy niż brak importu.
 */
final class DocxImporter
{
	public const MAX_FILE = 10_000_000;

	private const MAX_PART = 30_000_000;

	private const MAX_TOTAL = 200_000_000;

	private const MAX_ENTRIES = 3000;

	private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

	private const ALIGN = ['center' => 'ct-center', 'right' => 'ct-right', 'end' => 'ct-right', 'both' => 'ct-justify'];

	private const SKIPPED_RUN_CONTAINERS = ['del', 'moveFrom', 'drawing', 'pict', 'object', 'AlternateContent'];

	/** @var array<string, int> */
	private array $counts = [];

	/** @var array<string, string> styleId → nazwa */
	private array $styles = [];

	/** @var array<string, bool> numId → punktor */
	private array $bullets = [];

	/**
	 * @return array{html: string, warnings: list<string>, stats: array<string, int>}
	 */
	public function read(string $path): array
	{
		$this->counts = [];
		$size = @filesize($path);

		if ($size === false || $size > self::MAX_FILE) {
			throw new InvalidArgumentException('Plik DOCX może mieć najwyżej 10 MB.');
		}

		$zip = new ZipArchive();

		if ($zip->open($path, ZipArchive::RDONLY) !== true) {
			throw new InvalidArgumentException('To nie jest poprawny plik DOCX.');
		}

		try {
			$this->assertSafe($zip);
			$main = $this->xml($zip, 'word/document.xml');
			$this->styles = $this->styleNames($zip);
			$this->bullets = $this->bulletLists($zip);
			$this->countParts($zip);
		} finally {
			$zip->close();
		}

		$xpath = new DOMXPath($main);
		$xpath->registerNamespace('w', self::W);
		$body = $xpath->query('/w:document/w:body')->item(0);

		if (! $body) {
			throw new InvalidArgumentException('Ten plik DOCX nie ma treści dokumentu.');
		}

		$html = ContractHtml::clean($this->blocks($body));
		$this->assertTextKept($xpath, $html);

		return ['html' => $html, 'warnings' => $this->warnings($xpath), 'stats' => $this->counts];
	}

	private function assertSafe(ZipArchive $zip): void
	{
		if ($zip->numFiles > self::MAX_ENTRIES) {
			throw new InvalidArgumentException('Plik DOCX ma zbyt wiele elementów.');
		}

		$total = 0;

		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entry = $zip->statIndex($i);
			$total += (int) ($entry['size'] ?? 0);

			if (str_ends_with(strtolower((string) ($entry['name'] ?? '')), 'vbaproject.bin')) {
				throw new InvalidArgumentException('Plik zawiera makra. Zapisz go jako zwykły DOCX bez makr.');
			}
		}

		if ($total > self::MAX_TOTAL) {
			throw new InvalidArgumentException('Plik DOCX po rozpakowaniu jest zbyt duży.');
		}

		$types = (string) $zip->getFromName('[Content_Types].xml', self::MAX_PART);

		if (! str_contains($types, 'wordprocessingml.document.main')) {
			throw new InvalidArgumentException('To nie jest dokument Word (DOCX).');
		}
	}

	private function xml(ZipArchive $zip, string $name): DOMDocument
	{
		$stat = $zip->statName($name);

		if ($stat === false) {
			throw new InvalidArgumentException('W pliku DOCX brakuje ' . $name . '.');
		}

		if ($stat['size'] > self::MAX_PART) {
			throw new InvalidArgumentException('Plik DOCX po rozpakowaniu jest zbyt duży.');
		}

		$raw = (string) $zip->getFromName($name, self::MAX_PART);

		// DOCTYPE nie ma prawa wystąpić w DOCX, a to przez niego przechodzą ataki XXE i rozrost encji.
		if (stripos($raw, '<!DOCTYPE') !== false || stripos($raw, '<!ENTITY') !== false) {
			throw new InvalidArgumentException('Plik DOCX zawiera niedozwolone deklaracje XML.');
		}

		$document = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$loaded = $document->loadXML($raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if (! $loaded) {
			throw new InvalidArgumentException('Plik DOCX jest uszkodzony.');
		}

		return $document;
	}

	/**
	 * @return array<string, string>
	 */
	private function styleNames(ZipArchive $zip): array
	{
		if ($zip->statName('word/styles.xml') === false) {
			return [];
		}

		$names = [];

		foreach ($this->xml($zip, 'word/styles.xml')->getElementsByTagNameNS(self::W, 'style') as $style) {
			$name = $style->getElementsByTagNameNS(self::W, 'name')->item(0);
			$names[$style->getAttributeNS(self::W, 'styleId')] = strtolower($name?->getAttributeNS(self::W, 'val') ?? '');
		}

		return $names;
	}

	/**
	 * @return array<string, bool>
	 */
	private function bulletLists(ZipArchive $zip): array
	{
		if ($zip->statName('word/numbering.xml') === false) {
			return [];
		}

		$document = $this->xml($zip, 'word/numbering.xml');
		$abstract = [];

		foreach ($document->getElementsByTagNameNS(self::W, 'abstractNum') as $node) {
			$format = $node->getElementsByTagNameNS(self::W, 'numFmt')->item(0);
			$abstract[$node->getAttributeNS(self::W, 'abstractNumId')] = $format?->getAttributeNS(self::W, 'val') === 'bullet';
		}

		$lists = [];

		foreach ($document->getElementsByTagNameNS(self::W, 'num') as $node) {
			$link = $node->getElementsByTagNameNS(self::W, 'abstractNumId')->item(0);
			$lists[$node->getAttributeNS(self::W, 'numId')] = $abstract[$link?->getAttributeNS(self::W, 'val') ?? ''] ?? false;
		}

		return $lists;
	}

	private function countParts(ZipArchive $zip): void
	{
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = (string) $zip->getNameIndex($i);

			if (preg_match('#^word/(header|footer)\d*\.xml$#', $name)) {
				$this->count('header_footer');
			}
		}
	}

	private function blocks(DOMNode $parent): string
	{
		$out = '';
		$list = null;
		$items = '';
		$close = function () use (&$out, &$list, &$items) {
			if ($list !== null) {
				$tag = $this->bullets[$list] ?? false ? 'ul' : 'ol';
				$out .= "<$tag>$items</$tag>";
				$list = null;
				$items = '';
			}
		};

		foreach ($parent->childNodes as $node) {
			if (! $node instanceof DOMElement || $node->namespaceURI !== self::W) {
				continue;
			}

			if ($node->localName === 'p') {
				$number = $this->numbering($node);

				if ($number === null) {
					$close();
					$out .= $this->paragraph($node);

					continue;
				}

				if ($list !== $number) {
					$close();
					$list = $number;
				}

				$items .= '<li>' . $this->runs($node) . '</li>';
			} elseif ($node->localName === 'tbl') {
				$close();
				$out .= $this->table($node);
			} elseif ($node->localName === 'sdt') {
				$close();
				$out .= $this->blocks($this->child($node, 'sdtContent') ?? $node);
			} elseif (in_array($node->localName, ['ins', 'moveTo', 'customXml', 'smartTag'], true)) {
				$close();
				$out .= $this->blocks($node);
			}
		}

		$close();

		return $out;
	}

	private function child(DOMNode $node, string $name): ?DOMElement
	{
		foreach ($node->childNodes as $child) {
			if ($child instanceof DOMElement && $child->localName === $name && $child->namespaceURI === self::W) {
				return $child;
			}
		}

		return null;
	}

	private function numbering(DOMElement $paragraph): ?string
	{
		$properties = $this->child($paragraph, 'pPr');
		$number = $properties ? $this->child($properties, 'numPr') : null;

		if (! $number) {
			return null;
		}

		$id = $this->child($number, 'numId')?->getAttributeNS(self::W, 'val');

		if ($id === null || $id === '0') {
			return null;
		}

		$this->count('numbered');

		if ((int) $this->child($number, 'ilvl')?->getAttributeNS(self::W, 'val') > 0) {
			$this->count('nested');
		}

		return $id;
	}

	private function paragraph(DOMElement $node): string
	{
		$properties = $this->child($node, 'pPr');
		$style = $this->styles[$properties ? ($this->child($properties, 'pStyle')?->getAttributeNS(self::W, 'val') ?? '') : ''] ?? '';
		$tag = match (true) {
			$style === 'title', $style === 'heading 1' => 'h1',
			$style === 'heading 2' => 'h2',
			str_starts_with($style, 'heading ') => 'h3',
			default => 'p',
		};
		$align = self::ALIGN[$properties ? ($this->child($properties, 'jc')?->getAttributeNS(self::W, 'val') ?? '') : ''] ?? null;
		$class = $align ? ' class="' . $align . '"' : '';
		$break = $properties && $this->child($properties, 'pageBreakBefore') ? '<hr class="ct-break">' : '';
		$content = $this->runs($node);

		return $break . "<$tag$class>$content</$tag>";
	}

	private function runs(DOMNode $parent): string
	{
		$out = '';

		foreach ($parent->childNodes as $node) {
			if (! $node instanceof DOMElement) {
				continue;
			}

			$name = $node->localName;

			if ($name === 'r') {
				$out .= $this->run($node);
			} elseif ($name === 'del' || $name === 'moveFrom') {
				$this->count('deleted');
			} elseif ($name === 'ins' || $name === 'moveTo') {
				$this->count('inserted');
				$out .= $this->runs($node);
			} elseif (in_array($name, ['hyperlink', 'smartTag', 'fldSimple', 'customXml', 'sdt', 'sdtContent'], true)) {
				$out .= $this->runs($node);
			}
		}

		return $out;
	}

	private function run(DOMElement $run): string
	{
		$text = '';
		$pageBreak = false;

		foreach ($run->childNodes as $node) {
			if (! $node instanceof DOMElement) {
				continue;
			}

			match ($node->localName) {
				't' => $text .= htmlspecialchars($node->textContent, ENT_NOQUOTES, 'UTF-8'),
				'tab' => $text .= ' ',
				'noBreakHyphen' => $text .= '-',
				'br' => $node->getAttributeNS(self::W, 'type') === 'page' ? $pageBreak = true : $text .= '<br>',
				'footnoteReference', 'endnoteReference' => $this->count('notes'),
				'commentReference' => $this->count('comments'),
				'drawing', 'pict', 'object', 'AlternateContent' => $this->count('graphics'),
				default => null,
			};
		}

		$properties = $this->child($run, 'rPr');

		foreach (['b' => 'strong', 'i' => 'em', 'u' => 'u'] as $word => $tag) {
			$flag = $properties ? $this->child($properties, $word) : null;

			if ($flag && $text !== '' && ! in_array($flag->getAttributeNS(self::W, 'val'), ['0', 'false', 'none'], true)) {
				$text = "<$tag>$text</$tag>";
			}
		}

		// Blok nie może siedzieć w akapicie, więc podział strony domyka akapit i otwiera następny.
		return $text . ($pageBreak ? '</p><hr class="ct-break"><p>' : '');
	}

	private function table(DOMElement $table): string
	{
		$rows = '';

		foreach ($table->childNodes as $row) {
			if (! $row instanceof DOMElement || $row->localName !== 'tr') {
				continue;
			}

			$cells = '';

			foreach ($row->childNodes as $cell) {
				if (! $cell instanceof DOMElement || $cell->localName !== 'tc') {
					continue;
				}

				$properties = $this->child($cell, 'tcPr');
				$span = (int) ($properties ? $this->child($properties, 'gridSpan')?->getAttributeNS(self::W, 'val') : 0);

				if ($properties && $this->child($properties, 'vMerge')) {
					$this->count('merged');
				}

				$cells .= ($span > 1 ? "<td colspan=\"$span\">" : '<td>') . $this->blocks($cell) . '</td>';
			}

			$rows .= "<tr>$cells</tr>";
		}

		return "<table><tbody>$rows</tbody></table>";
	}

	/**
	 * Niezależny od przejścia przez dokument: bierze cały tekst pliku wprost z XML. Element, którego kod nie zna,
	 * kończy się odrzuceniem pliku, a nie cichym ubytkiem klauzuli.
	 */
	private function assertTextKept(DOMXPath $xpath, string $html): void
	{
		$source = '';
		$query = '//w:t[not(ancestor::w:del or ancestor::w:moveFrom or ancestor::w:txbxContent or ancestor::w:drawing or ancestor::w:pict or ancestor::w:object)]';

		foreach ($xpath->query($query) as $node) {
			$source .= $node->textContent;
		}

		$squeeze = fn (string $text) => preg_replace('/\s+/u', '', str_replace("\u{00A0}", ' ', $text));

		if ($squeeze($source) !== $squeeze(ContractHtml::text($html))) {
			throw new InvalidArgumentException('Nie udało się bezpiecznie odczytać całego tekstu tego pliku. Dokument nie został zaimportowany.');
		}
	}

	private function count(string $key): void
	{
		$this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
	}

	/**
	 * @return list<string>
	 */
	private function warnings(DOMXPath $xpath): array
	{
		$boxes = $xpath->query('//w:txbxContent')->length;
		$messages = [
			'numbered' => 'Punkty z numeracją automatyczną: %d. Numery z Worda nie są tekstem, więc w szablonie liczą się od nowa. Porównaj je z oryginałem, także odwołania do nich.',
			'nested' => 'Punkty listy zagnieżdżone: %d. Zostały spłaszczone do jednego poziomu.',
			'deleted' => 'Śledzone usunięcia: %d. Usunięty tekst pominięto.',
			'inserted' => 'Śledzone wstawienia: %d. Wstawiony tekst przyjęto jako zaakceptowany.',
			'comments' => 'Komentarze: %d. Pominięte.',
			'notes' => 'Przypisy: %d. Ich treść pominięto.',
			'graphics' => 'Grafiki i obiekty: %d. Pominięte.',
			'header_footer' => 'Nagłówki i stopki z pliku: %d. Nie zostały przeniesione.',
			'merged' => 'Komórki scalone w pionie: %d. Rozdzielone na osobne wiersze.',
		];
		$out = [];

		foreach ($messages as $key => $message) {
			if (! empty($this->counts[$key])) {
				$out[] = sprintf($message, $this->counts[$key]);
			}
		}

		if ($boxes > 0) {
			$out[] = sprintf('Pola tekstowe: %d. Ich treść pominięto.', $boxes);
		}

		return $out;
	}
}
