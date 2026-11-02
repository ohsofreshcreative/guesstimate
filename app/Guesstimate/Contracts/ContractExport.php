<?php

namespace App\Guesstimate\Contracts;

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
	public static function file(ContractVersion $version, string $format, string $title): string
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

		return $format === 'pdf' ? PdfExporter::render($regions, $title) : (new DocxExporter())->render($regions, $title);
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

	/** Tylko numery, bez nazwy klienta: nagłówek pobierania nie powinien nieść danych z umowy. */
	public static function filename(ContractVersion $version, string $format): string
	{
		return sprintf('umowa-%d-v%d.%s', $version->contract_id, $version->version_no, $format);
	}
}
