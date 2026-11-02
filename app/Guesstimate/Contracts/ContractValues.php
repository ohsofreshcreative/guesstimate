<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Support\Format;
use InvalidArgumentException;

/**
 * Wartości tokenów umowy. Źródłem jest wyłącznie snapshot wersji, nigdy żywa oferta. Treść umowy zostaje kopią
 * szablonu z tokenami, a podstawienie odbywa się przy wyświetlaniu, dzięki czemu tekst klauzul się nie zmienia.
 *
 * Tokeny MANUAL nie mają źródła w danych aplikacji i zawsze wpisuje je człowiek. Ręczna wartość tokenu
 * automatycznego wygrywa z danymi oferty. Tokeny DERIVED wynikają z harmonogramu płatności i nie mają wartości
 * ręcznych, bo kwota w treści nie może rozjechać się z tabelą. Widełki to nie kwota kontraktowa (decyzja 4).
 */
final class ContractValues
{
	public const MANUAL = ['client.address', 'client.representative', 'terms.signed_at', 'terms.place', 'terms.start', 'terms.end'];

	public const DERIVED = ['contract.amount', 'contract.amount_vat', 'contract.amount_gross', 'contract.vat_rate', 'contract.deposit', 'payments.table'];

	public const MAX_LENGTH = 2000;

	private const TOKEN = '~<span\b[^>]*\bdata-var="([^"]*)"[^>]*>.*?</span>~s';

	/** Token tabeli sam w akapicie: tabela zastępuje cały akapit, bo `<table>` w `<p>` to niepoprawny HTML. */
	private const TABLE = '~<p\b[^>]*>\s*<span\b[^>]*\bdata-var="payments\.table"[^>]*>.*?</span>(?:\s|&nbsp;|\x{00A0})*</p>~su';

	/**
	 * Dane ze snapshotu jako tekst. Brak wartości to zawsze null.
	 *
	 * @return array<string, ?string>
	 */
	public static function automatic(array $snapshot): array
	{
		$offer = $snapshot['offer'] ?? [];
		$pricing = $snapshot['pricing'] ?? [];
		$currency = (string) ($offer['currency'] ?? 'PLN');
		$values = [];

		foreach (ContractSnapshot::CLIENT_FIELDS as $field) {
			$values["client.{$field}"] = $snapshot['client'][$field] ?? null;
		}

		foreach (ContractSnapshot::CONTRACTOR_FIELDS as $field) {
			$values["contractor.{$field}"] = $snapshot['contractor'][$field] ?? null;
		}

		foreach (['number', 'title', 'date', 'project_type', 'version_no', 'description', 'ux_scope', 'pages', 'features', 'variant'] as $field) {
			$values["offer.{$field}"] = $offer[$field] ?? null;
		}

		$values['offer.amount_variant'] = self::range($pricing['amounts']['variant'] ?? null, $currency);
		$values['offer.amount_range'] = self::range($pricing['amounts']['range'] ?? null, $currency);

		return array_map([self::class, 'text'], $values + self::payments($snapshot, $currency));
	}

	/**
	 * @return array<string, ?string>
	 */
	public static function resolved(array $snapshot): array
	{
		$manual = array_diff_key((array) ($snapshot['values'] ?? []), array_flip(self::DERIVED));

		return array_replace(array_fill_keys(self::MANUAL, null), self::automatic($snapshot), array_map([self::class, 'text'], $manual));
	}

	/**
	 * Tokeny użyte w treści, w kolejności wystąpienia. Numery stron liczy dopiero podział na strony.
	 *
	 * @return list<array{key: string, label: string, value: ?string, source: ?string, manual: bool, overridden: bool, missing: bool}>
	 */
	public static function fields(string $body, array $snapshot): array
	{
		$labels = TemplateVariables::all();
		$automatic = self::automatic($snapshot);
		$resolved = self::resolved($snapshot);
		$fields = [];

		foreach (TemplateVariables::used($body) as $key) {
			if (str_starts_with($key, 'page.')) {
				continue;
			}

			$value = $resolved[$key] ?? null;
			$fields[] = [
				'key' => $key,
				'label' => $labels[$key] ?? $key,
				'value' => $value,
				'source' => $automatic[$key] ?? null,
				'manual' => in_array($key, self::MANUAL, true),
				'derived' => in_array($key, self::DERIVED, true),
				'overridden' => isset($snapshot['values'][$key]) && ! in_array($key, self::DERIVED, true),
				'missing' => $value === null,
			];
		}

		return $fields;
	}

	/**
	 * @return list<string> etykiety pól bez wartości
	 */
	public static function missing(string $body, array $snapshot): array
	{
		return array_column(array_filter(self::fields($body, $snapshot), fn ($field) => $field['missing']), 'label');
	}

	/**
	 * Podstawia tokeny w jednym przebiegu: wstawiona wartość nie jest ponownie analizowana, a bajty poza tokenami
	 * zostają nietknięte. Wartości to zwykły tekst i zawsze są escapowane.
	 */
	public static function render(string $html, array $snapshot): string
	{
		$labels = TemplateVariables::all();
		$resolved = self::resolved($snapshot);

		if ($resolved['payments.table'] ?? null) {
			// Teksty w tabeli są escapowane, więc poniższy przebieg nie znajdzie w niej tokenów.
			$html = (string) preg_replace_callback(self::TABLE, fn () => PaymentSchedule::table(PaymentSchedule::forSnapshot($snapshot)), $html);
		}

		return (string) preg_replace_callback(self::TOKEN, function ($match) use ($labels, $resolved) {
			$key = $match[1];

			if (str_starts_with($key, 'page.')) {
				return '<span data-var="' . self::escape($key) . '">' . self::escape($labels[$key] ?? $key) . '</span>';
			}

			$value = $resolved[$key] ?? null;

			return $value === null
				? '<span class="ct-missing">[brak: ' . self::escape($labels[$key] ?? $key) . ']</span>'
				: self::escape($value);
		}, $html);
	}

	/**
	 * Formularz: pusta wartość albo równa danym ze snapshotu usuwa wpis ręczny.
	 *
	 * @return array<string, string> nowe wartości ręczne
	 *
	 * @throws InvalidArgumentException
	 */
	public static function clean(array $input, array $snapshot): array
	{
		$labels = TemplateVariables::all();
		$automatic = self::automatic($snapshot);
		$values = (array) ($snapshot['values'] ?? []);

		foreach ($input as $key => $raw) {
			// Puste pole formularza przychodzi jako null (ConvertEmptyStringsToNull) i oznacza powrót do danych.
			if (! isset($labels[$key]) || str_starts_with($key, 'page.') || in_array($key, self::DERIVED, true) || ! ($raw === null || is_scalar($raw))) {
				continue;
			}

			$text = self::text($raw);

			if ($text !== null && mb_strlen($text) > self::MAX_LENGTH) {
				throw new InvalidArgumentException(sprintf('%s: najwyżej %d znaków.', $labels[$key], self::MAX_LENGTH));
			}

			if ($text === null || $text === ($automatic[$key] ?? null)) {
				unset($values[$key]);
			} else {
				$values[$key] = $text;
			}
		}

		return $values;
	}

	/**
	 * Co zmieniłoby odświeżenie. Dotyczy tylko tokenów użytych w treści.
	 *
	 * @return list<array{key: string, label: string, old: ?string, new: ?string, overridden: bool}>
	 */
	public static function diff(string $body, array $old, array $new): array
	{
		$before = self::automatic($old);
		$after = self::automatic($new);
		$rows = [];

		foreach (self::fields($body, $old) as $field) {
			$key = $field['key'];

			if ($field['derived']) {
				continue;
			}

			if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
				$rows[] = ['key' => $key, 'label' => $field['label'], 'old' => $before[$key] ?? null, 'new' => $after[$key] ?? null, 'overridden' => $field['overridden']];
			}
		}

		return $rows;
	}

	/** @return array<string, ?string> */
	private static function payments(array $snapshot, string $currency): array
	{
		$summary = PaymentSchedule::forSnapshot($snapshot);
		$agreed = $summary['agreed'];
		$money = fn (?int $amount) => $amount === null ? null : Format::money($amount, $currency);

		return [
			'contract.amount' => $money($agreed['net'] ?? null),
			'contract.amount_vat' => $money($agreed['vat'] ?? null),
			'contract.amount_gross' => $money($agreed['gross'] ?? null),
			'contract.vat_rate' => $agreed ? Format::percent((string) ($snapshot['payments']['vat_rate'] ?? '')) : null,
			'contract.deposit' => $money($summary['deposit']['gross'] ?? null),
			'payments.table' => PaymentSchedule::line($summary),
		];
	}

	private static function range(?array $range, string $currency): ?string
	{
		return isset($range['min'], $range['max']) ? Format::moneyRange((int) $range['min'], (int) $range['max'], $currency) : null;
	}

	/** Jedna linia zwykłego tekstu; pusto to null, żeby do umowy nie trafiło puste miejsce ani „null”. */
	public static function text(mixed $value): ?string
	{
		if (is_array($value)) {
			$value = implode(', ', array_filter(array_map('strval', $value), 'strlen'));
		}

		$text = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value));

		return $text === '' ? null : $text;
	}

	private static function escape(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
