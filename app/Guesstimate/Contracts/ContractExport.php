<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Document\Media;
use InvalidArgumentException;

/**
 * Jeden dokument dla obu formatów: PDF i DOCX powstają z tych samych, gotowych części zatwierdzonej wersji.
 * Pliki nie są zapisywane: bajty powstają przy pobraniu z zamrożonej wersji, więc nie ma czego wykraść z dysku.
 */
final class ContractExport
{
	public const FORMATS = [
		'pdf' => 'application/pdf',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	];

	/**
	 * @throws InvalidArgumentException gdy wersja nie jest zatwierdzona, nie zgadza się ze skrótem albo ma braki
	 */
	/**
	 * @param  list<array{0: int, 1: int}>  $breaks  początki stron z panelu (ContractExport::breaks)
	 */
	public static function file(ContractVersion $version, string $format, string $title, array $breaks = []): string
	{
		if (! $version->isApproved()) {
			throw new InvalidArgumentException('Eksport jest dostępny tylko dla zatwierdzonej wersji umowy.');
		}

		if (! $version->isIntact()) {
			throw new InvalidArgumentException('Treść tej wersji nie zgadza się ze skrótem z zatwierdzenia. Eksport zablokowany.');
		}

		$body = (string) $version->body;
		$snapshot = (array) $version->data_snapshot;

		if ($missing = ContractValues::missing($body, $snapshot)) {
			throw new InvalidArgumentException('Nie można wyeksportować umowy z pustymi polami: ' . implode(', ', $missing) . '.');
		}

		$regions = self::compose($body, $snapshot);
		$regions['body'] = self::paginate($regions['body'], $breaks);

		$page = PageSetup::current();
		$background = self::background($version);

		return $format === 'pdf' ? PdfExporter::render($regions, $title, $background, $page) : (new DocxExporter())->render($regions, $title, $background, $page);
	}

	/**
	 * Plik tła zatwierdzonej wersji (PDF i DOCX). Pełny plik z biblioteki: WordPress i tak zmniejsza oryginały
	 * powyżej 2560 px, a mniejszy rozmiar (np. „large”, 1024 px) dawał na A4 ok. 120 DPI, czyli rozmyte tło.
	 */
	private static function background(ContractVersion $version): ?string
	{
		$id = $version->backgroundId();

		if (! $id || ! Media::isImage($id)) {
			return null;
		}

		$file = get_attached_file($id);

		return $file && is_readable($file) ? $file : null;
	}

	/**
	 * Tokeny podstawione, a numery paragrafów wyliczone raz: w edytorze § daje CSS, którego nie ma w eksporcie.
	 *
	 * @return array{header: string, body: string, footer: string}
	 */
	public static function compose(string $body, array $snapshot): array
	{
		$regions = array_map(fn ($part) => ContractValues::render(ContractHtml::clean($part), $snapshot), TemplateDocument::parse($body));
		$number = 0;
		$regions['body'] = (string) preg_replace_callback('~<p\b[^>]*\bclass="[^"]*\bct-par\b[^"]*"[^>]*>~', function ($match) use (&$number) {
			$number++;

			return $match[0] . "§ {$number}. ";
		}, $regions['body']);

		return $regions;
	}

	/**
	 * Początki stron z panelu w postaci „3,7.2,12”: numer bloku treści, a po kropce numer wiersza tabeli lub punktu
	 * listy, od którego zaczyna się strona. Wpisy niepoprawne są pomijane.
	 *
	 * @return list<array{0: int, 1: int}>
	 */
	public static function breaks(string $value): array
	{
		$breaks = [];

		foreach (array_slice(explode(',', $value), 0, 1000) as $item) {
			if (preg_match('/^(\d{1,5})(?:\.(\d{1,5}))?$/', trim($item), $match)) {
				$breaks[] = [(int) $match[1], (int) ($match[2] ?? 0)];
			}
		}

		return $breaks;
	}

	/**
	 * Twarde łamanie strony tam, gdzie zaczyna się strona w panelu: PDF i DOCX mają wtedy te same strony co podgląd.
	 * Zmienia się tylko podział (tabela lub lista może zostać rozdzielona na dwie części), nigdy tekst.
	 *
	 * @param  list<array{0: int, 1: int}>  $breaks
	 */
	public static function paginate(string $html, array $breaks): string
	{
		if (! $breaks || trim($html) === '') {
			return $html;
		}

		$document = new \DOMDocument('1.0', 'UTF-8');
		$document->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="gst-root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		$root = $document->getElementById('gst-root');

		if (! $root) {
			return $html;
		}

		$blocks = [];

		foreach ($root->childNodes as $node) {
			if ($node instanceof \DOMElement) {
				$blocks[] = $node;
			}
		}

		// Od końca: wstawione elementy nie przesuwają numerów bloków, które jeszcze czekają.
		usort($breaks, fn ($a, $b) => [$b[0], $b[1]] <=> [$a[0], $a[1]]);

		foreach ($breaks as [$index, $from]) {
			$block = $blocks[$index] ?? null;

			if (! $block) {
				continue;
			}

			$start = $block;

			if ($from > 0) {
				$start = self::split($block, $from);

				if (! $start) {
					continue;
				}
			}

			$previous = $start->previousSibling;

			while ($previous && ! $previous instanceof \DOMElement) {
				$previous = $previous->previousSibling;
			}

			// Ręczny podział strony już tu jest — drugi dałby pustą stronę.
			if ($previous && $previous->nodeName === 'hr' && str_contains((string) $previous->getAttribute('class'), 'ct-break')) {
				continue;
			}

			$break = $document->createElement('hr');
			$break->setAttribute('class', 'ct-break');
			$start->parentNode->insertBefore($break, $start);
		}

		$out = '';

		foreach ($root->childNodes as $node) {
			$out .= $document->saveHTML($node);
		}

		return $out;
	}

	/**
	 * Dzieli tabelę (wiersze tbody) albo listę (punkty) przed pozycją $from. Zwraca drugą część albo null.
	 */
	private static function split(\DOMElement $block, int $from): ?\DOMElement
	{
		$name = strtolower($block->nodeName);

		if ($name === 'table') {
			$body = $block->getElementsByTagName('tbody')->item(0);
			$rows = $body ? array_values(array_filter(iterator_to_array($body->childNodes), fn ($n) => $n instanceof \DOMElement && $n->nodeName === 'tr')) : [];

			if ($from >= count($rows)) {
				return null;
			}

			$copy = $block->cloneNode(false);

			foreach ($block->childNodes as $child) {
				if ($child instanceof \DOMElement && $child->nodeName === 'thead') {
					$copy->appendChild($child->cloneNode(true));
				}
			}

			$newBody = $block->ownerDocument->createElement('tbody');

			foreach (array_slice($rows, $from) as $row) {
				$newBody->appendChild($row);
			}

			$copy->appendChild($newBody);
		} elseif ($name === 'ul' || $name === 'ol') {
			$items = array_values(array_filter(iterator_to_array($block->childNodes), fn ($n) => $n instanceof \DOMElement && $n->nodeName === 'li'));

			if ($from >= count($items)) {
				return null;
			}

			$copy = $block->cloneNode(false);

			if ($name === 'ol') {
				$copy->setAttribute('start', (string) (((int) $block->getAttribute('start') ?: 1) + $from));
			}

			foreach (array_slice($items, $from) as $item) {
				$copy->appendChild($item);
			}
		} else {
			return null;
		}

		$block->parentNode->insertBefore($copy, $block->nextSibling);

		return $copy;
	}

	/** Tylko numery, bez nazwy klienta: nagłówek pobierania nie powinien nieść danych z umowy. */
	public static function filename(ContractVersion $version, string $format): string
	{
		return sprintf('umowa-%d-v%d.%s', $version->contract_id, $version->version_no, $format);
	}
}
