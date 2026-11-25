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
	/** Odstęp między nagłówkiem lub stopką a treścią, w mm. */
	private const GAP = PageSetup::GAP;

	/** Na treść musi zostać co najmniej tyle mm wysokości strony. */
	private const MIN_BODY = 60;

	private const PT_TO_MM = 25.4 / 72;

	/** Wysokość A4 w pt. */
	private const PAGE_HEIGHT = 841.89;

	/**
	 * @param  string|null  $background  plik graficzny tła stron (papier firmowy) albo null
	 */
	public static function render(array $regions, string $title, ?string $background = null, ?PageSetup $page = null): string
	{
		$page ??= PageSetup::defaults();

		if (preg_match('/[\x{10000}-\x{10FFFF}]/u', implode('', $regions))) {
			throw new InvalidArgumentException('Umowa zawiera znaki spoza fontu PDF (np. emoji). Usuń je w nowej wersji albo użyj DOCX.');
		}

		$space = self::measure($regions, $page);

		if (297 - $page->bodyTop($space['top']) - $page->bodyBottom($space['bottom']) < self::MIN_BODY) {
			throw new InvalidArgumentException('Nagłówek, stopka i marginesy umowy są zbyt duże, by zmieścić treść na stronie A4.');
		}

		// Dompdf działa bez dostępu do plików i sieci, więc tło trafia do dokumentu jako data URI.
		$image = $background ? self::dataUri($background) : null;
		$uses = str_contains(implode('', $regions), 'data-var="page.count"');
		$bytes = self::pass($regions, $title, 0, $pages, $space, $image, $page);

		// Dompdf nie zna liczby stron w `counter(pages)`, więc pierwszy przebieg ją mierzy.
		return $uses ? self::pass($regions, $title, $pages, $pages, $space, $image, $page) : $bytes;
	}

	private static function pass(array $regions, string $title, int $total, ?int &$pages, array $space, ?string $image, PageSetup $page): string
	{
		$dompdf = self::engine();
		$limit = self::PAGE_HEIGHT - $page->bodyBottom($space['bottom']) / self::PT_TO_MM;
		$cut = false;

		// Dompdf nie dzieli wiersza tabeli: wiersz wyższy niż strona zostałby ucięty bez błędu. Stopka zaczyna się za limitem.
		$dompdf->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use ($limit, &$cut) {
			if ($frame->get_node()->nodeName === 'tr') {
				$box = $frame->get_border_box();
				$cut = $cut || ($box['y'] < $limit && $box['y'] + $box['h'] > $limit + 1);
			}
		}]]);
		$dompdf->loadHtml(self::html($regions, $title, $total, $space, $image, $page), 'UTF-8');
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
	public static function measure(array $regions, ?PageSetup $page = null): array
	{
		if (trim($regions['header'] . $regions['footer']) === '') {
			return ['top' => 0.0, 'bottom' => 0.0];
		}

		$page ??= PageSetup::defaults();
		$dompdf = self::engine();
		$dompdf->loadHtml('<!DOCTYPE html><html><head><meta charset="utf-8"><style>@page{size:A4 portrait;margin:0 ' . $page->get('right') . 'mm 0 ' . $page->get('left') . 'mm}' . self::style() . '#gst-top,#gst-bottom{padding:0.2mm 0}</style></head><body>'
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

	public static function html(array $regions, string $title, int $total = 0, array $space = ['top' => 0.0, 'bottom' => 0.0], ?string $image = null, ?PageSetup $page = null): string
	{
		$page ??= PageSetup::defaults();
		[$header, $body, $footer] = array_map(fn ($part) => self::prepare($part, $total), [$regions['header'], $regions['body'], $regions['footer']]);
		// Pole treści zaczyna się na marginesie albo pod nagłówkiem (jak w Wordzie); nagłówek stoi w swojej odległości od krawędzi.
		$top = $page->bodyTop($space['top']);
		$bottom = $page->bodyBottom($space['bottom']);
		$css = sprintf(
			'@page{size:A4 portrait;margin:%1$.1fmm %3$.1fmm %2$.1fmm %4$.1fmm}.gst-header{position:fixed;top:-%5$.1fmm;left:0;right:0}.gst-footer{position:fixed;bottom:-%6$.1fmm;left:0;right:0}',
			$top,
			$bottom,
			$page->get('right'),
			$page->get('left'),
			$top - $page->get('header'),
			$bottom - $page->get('footer'),
		);

		// Tło całej kartki: element stały jest liczony od pola treści, więc przesuwamy go o marginesy strony.
		// z-index:-1 jest konieczny: dompdf rysuje elementy pozycjonowane bez z-index po treści, czyli na niej.
		if ($image !== null) {
			$css .= sprintf('.gst-bg{position:fixed;top:-%1$.1fmm;left:-%2$.1fmm;width:210mm;height:297mm;z-index:-1}.gst-bg img{width:210mm;height:297mm}', $top, $page->get('left'));
		}

		return '<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title><style>' . $css . self::style() . '</style></head><body>'
			. ($image !== null ? '<div class="gst-bg"><img src="' . $image . '" alt=""></div>' : '')
			. ($header !== '' ? "<div class=\"gst-header\">{$header}</div>" : '')
			. ($footer !== '' ? "<div class=\"gst-footer\">{$footer}</div>" : '')
			. $body . '</body></html>';
	}

	/** Interlinia jak w panelu: 1,4 rozmiaru pisma (10 pt → 14 pt). */
	private const LINE_HEIGHT = 1.4;

	private static ?float $lineFactor = null;

	/**
	 * Przeglądarka liczy `line-height` od rozmiaru pisma, a dompdf mnoży go jeszcze przez wysokość kroju
	 * (DejaVu Sans ok. 1,28 rozmiaru). Bez korekty wiersz w PDF ma ~18 pt zamiast 14 pt i na stronie mieści się
	 * mniej tekstu niż w panelu, więc strony się rozjeżdżają. Wysokość kroju czytamy z dompdf, nie wpisujemy jej na sztywno.
	 */
	private static function style(): string
	{
		if (self::$lineFactor === null) {
			$metrics = self::engine()->getFontMetrics();
			$font = $metrics->getFont('DejaVu Sans', 'normal');
			$height = $font ? $metrics->getFontHeight($font, 10) / 10 : 0;
			self::$lineFactor = $height > 0 ? $height : 1.0;
		}

		return str_replace('{line}', rtrim(rtrim(number_format(self::LINE_HEIGHT / self::$lineFactor, 4, '.', ''), '0'), '.'), self::STYLE);
	}

	private const STYLE = 'body{font-family:"DejaVu Sans";font-size:10pt;line-height:{line};color:#000;word-wrap:break-word}p,ul,ol,table{margin:0 0 2.5mm}'
		. 'h1{font-size:15pt;margin:4mm 0 3mm}h2{font-size:12pt;margin:4mm 0 2.5mm}h3{font-size:10pt;margin:3mm 0 2mm}'
		. 'ul,ol{padding-left:6mm}table{width:100%;border-collapse:collapse;table-layout:fixed}'
		. 'td,th{border:1px solid #888;padding:1.5mm 2mm;vertical-align:top;word-wrap:break-word;font-size:8.5pt}td p,th p{margin:0}'
		. 'thead{display:table-header-group}tr{page-break-inside:avoid}h1,h2,h3,.ct-par{page-break-after:avoid}'
		. '.ct-par{margin-top:4mm;font-weight:bold;text-align:center}.ct-small{font-size:8pt}.ct-center{text-align:center}'
		. '.ct-right{text-align:right}.ct-justify{text-align:justify}.gst-break{page-break-before:always}.gst-page::after{content:counter(page)}';

	private static function dataUri(string $file): ?string
	{
		$type = wp_check_filetype($file)['type'] ?? null;

		if (! in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true) || ! is_readable($file)) {
			return null;
		}

		return 'data:' . $type . ';base64,' . base64_encode((string) file_get_contents($file));
	}

	private static function prepare(string $html, int $total): string
	{
		$html = str_replace('<hr class="ct-break">', '<div class="gst-break"></div>', $html);
		$html = (string) preg_replace('~<span data-var="page\.number">.*?</span>~s', '<span class="gst-page"></span>', $html);

		return (string) preg_replace('~<span data-var="page\.count">.*?</span>~s', (string) $total, $html);
	}
}
