<?php

namespace App\Guesstimate\Contracts;

use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;

/**
 * PDF A4 z gotowych części dokumentu (ContractExport::compose). Dompdf bez dostępu do sieci, PHP i JS.
 * Font DejaVu Sans jest w bibliotece i ma polskie znaki, ale nie ma emoji: zamiast pustego kwadratu w umowie
 * eksport jest odrzucany.
 */
final class PdfExporter
{
	private const MARGIN = 20;

	/** Odstęp między nagłówkiem lub stopką a treścią, w mm. */
	private const GAP = 3;

	/** Nagłówek i stopka razem mogą zająć tyle mm: reszta A4 zostaje na treść. */
	private const MAX_FURNITURE = 180;

	private const PT_TO_MM = 25.4 / 72;

	/** Wysokość A4 w pt. */
	private const PAGE_HEIGHT = 841.89;

	public static function render(array $regions, string $title): string
	{
		if (preg_match('/[\x{10000}-\x{10FFFF}]/u', implode('', $regions))) {
			throw new InvalidArgumentException('Umowa zawiera znaki spoza fontu PDF (np. emoji). Usuń je w nowej wersji albo użyj DOCX.');
		}

		$space = self::measure($regions);

		if ($space['top'] + $space['bottom'] > self::MAX_FURNITURE) {
			throw new InvalidArgumentException('Nagłówek i stopka umowy są zbyt wysokie, by zmieścić treść na stronie A4.');
		}

		$uses = str_contains(implode('', $regions), 'data-var="page.count"');
		$bytes = self::pass($regions, $title, 0, $pages, $space);

		// Dompdf nie zna liczby stron w `counter(pages)`, więc pierwszy przebieg ją mierzy.
		return $uses ? self::pass($regions, $title, $pages, $pages, $space) : $bytes;
	}

	private static function pass(array $regions, string $title, int $total, ?int &$pages, array $space): string
	{
		$dompdf = self::engine();
		$limit = self::PAGE_HEIGHT - (self::MARGIN + $space['bottom']) / self::PT_TO_MM;
		$cut = false;

		// Dompdf nie dzieli wiersza tabeli: wiersz wyższy niż strona zostałby ucięty bez błędu. Stopka zaczyna się za limitem.
		$dompdf->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use ($limit, &$cut) {
			if ($frame->get_node()->nodeName === 'tr') {
				$box = $frame->get_border_box();
				$cut = $cut || ($box['y'] < $limit && $box['y'] + $box['h'] > $limit + 1);
			}
		}]]);
		$dompdf->loadHtml(self::html($regions, $title, $total, $space), 'UTF-8');
		$dompdf->render();

		if ($cut) {
			throw new InvalidArgumentException('Wiersz tabeli nie mieści się na jednej stronie PDF. Podziel go w nowej wersji albo użyj DOCX.');
		}

		$dompdf->addInfo('Title', $title);
		$pages = $dompdf->getCanvas()->get_page_count();

		return (string) $dompdf->output();
	}

	private static function engine(): Dompdf
	{
		$temp = sys_get_temp_dir() . '/gst-dompdf';
		@mkdir($temp, 0700);

		$dompdf = new Dompdf(new Options([
			'isRemoteEnabled' => false,
			'isPhpEnabled' => false,
			'isJavascriptEnabled' => false,
			'defaultFont' => 'DejaVu Sans',
			'tempDir' => $temp,
			'fontCache' => $temp,
		]));
		$dompdf->setPaper('A4', 'portrait');

		return $dompdf;
	}

	/**
	 * Wysokość nagłówka i stopki w mm. Dompdf układa je poza przepływem i nie rezerwuje dla nich miejsca,
	 * więc są mierzone osobnym przebiegiem w tej samej szerokości i z tym samym stylem.
	 *
	 * @return array{top: float, bottom: float}
	 */
	public static function measure(array $regions): array
	{
		if (trim($regions['header'] . $regions['footer']) === '') {
			return ['top' => 0.0, 'bottom' => 0.0];
		}

		$dompdf = self::engine();
		$dompdf->loadHtml('<!DOCTYPE html><html><head><meta charset="utf-8"><style>@page{size:A4 portrait;margin:0 ' . self::MARGIN . 'mm}' . self::STYLE . '#gst-top,#gst-bottom{padding:0.2mm 0}</style></head><body>'
			. '<div id="gst-top">' . self::prepare($regions['header'], 999) . '</div><div id="gst-bottom">' . self::prepare($regions['footer'], 999) . '</div></body></html>', 'UTF-8');
		$space = ['top' => 0.0, 'bottom' => 0.0];

		// Po render() drzewo ramek jest już zwolnione, więc pomiar musi się odbyć w trakcie.
		// Padding zatrzymuje zapadanie marginesów, które inaczej nie weszłyby do pomiaru.
		$dompdf->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$space, $regions) {
			$node = $frame->get_node();
			$id = $node->nodeName === 'div' ? $node->getAttribute('id') : '';

			if (($id === 'gst-top' && trim($regions['header']) !== '') || ($id === 'gst-bottom' && trim($regions['footer']) !== '')) {
				$space[substr($id, 4)] = $frame->get_border_box()['h'] * self::PT_TO_MM + self::GAP;
			}
		}]]);
		$dompdf->render();

		return $space;
	}

	public static function html(array $regions, string $title, int $total = 0, array $space = ['top' => 0.0, 'bottom' => 0.0]): string
	{
		[$header, $body, $footer] = array_map(fn ($part) => self::prepare($part, $total), [$regions['header'], $regions['body'], $regions['footer']]);
		$top = $space['top'];
		$bottom = $space['bottom'];
		$css = sprintf(
			'@page{size:A4 portrait;margin:%1$.1fmm %3$dmm %2$.1fmm}.gst-header{position:fixed;top:-%4$.1fmm;left:0;right:0}.gst-footer{position:fixed;bottom:-%5$.1fmm;left:0;right:0}',
			self::MARGIN + $top,
			self::MARGIN + $bottom,
			self::MARGIN,
			$top,
			$bottom,
		);

		return '<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title><style>' . $css . self::STYLE . '</style></head><body>'
			. ($header !== '' ? "<div class=\"gst-header\">{$header}</div>" : '')
			. ($footer !== '' ? "<div class=\"gst-footer\">{$footer}</div>" : '')
			. $body . '</body></html>';
	}

	private const STYLE = 'body{font-family:"DejaVu Sans";font-size:10pt;line-height:1.4;color:#000;word-wrap:break-word}p,ul,ol,table{margin:0 0 2.5mm}'
		. 'h1{font-size:15pt;margin:4mm 0 3mm}h2{font-size:12pt;margin:4mm 0 2.5mm}h3{font-size:10pt;margin:3mm 0 2mm}'
		. 'ul,ol{padding-left:6mm}table{width:100%;border-collapse:collapse;table-layout:fixed}'
		. 'td,th{border:1px solid #888;padding:1.5mm 2mm;vertical-align:top;word-wrap:break-word;font-size:8.5pt}td p,th p{margin:0}'
		. 'thead{display:table-header-group}tr{page-break-inside:avoid}h1,h2,h3,.ct-par{page-break-after:avoid}'
		. '.ct-par{margin-top:4mm;font-weight:bold;text-align:center}.ct-small{font-size:8pt}.ct-center{text-align:center}'
		. '.ct-right{text-align:right}.ct-justify{text-align:justify}.gst-break{page-break-before:always}.gst-page::after{content:counter(page)}';

	private static function prepare(string $html, int $total): string
	{
		$html = str_replace('<hr class="ct-break">', '<div class="gst-break"></div>', $html);
		$html = (string) preg_replace('~<span data-var="page\.number">.*?</span>~s', '<span class="gst-page"></span>', $html);

		return (string) preg_replace('~<span data-var="page\.count">.*?</span>~s', (string) $total, $html);
	}
}
