<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Pricing\Decimal;
use App\Guesstimate\Support\Format;
use App\Guesstimate\Support\Input;
use Brick\Math\RoundingMode;

/**
 * Uzgodniona kwota i harmonogram płatności umowy. Kwoty w groszach, bez WordPressa i bez bazy.
 *
 * Kwota umowy jest niezależna od widełek oferty: te służą tylko do ostrzeżenia. Nic nie jest dopisywane ani
 * dociągane: VAT liczy się od każdej pozycji (HALF_UP), a różnica staje się blokadą lub ostrzeżeniem.
 * Tryb zadatku wybiera człowiek (decyzja 5). Procent wpisany w umowie zostaje obok procentu z kwot (decyzja 6).
 *
 * Bez podwójnego liczenia: zadatek odrębny pokrywa część kwoty obok etapów, a zaliczony tylko obniża
 * dopłatę wskazanego etapu i nie zwiększa sumy. Oba tryby: wpłaty razem = etapy + zadatek odrębny.
 */
final class PaymentSchedule
{
	public const MAX_STAGES = 20;

	public const SEPARATE = 'separate';

	public const CREDITED = 'credited';

	public const MODES = [
		self::SEPARATE => 'Odrębna płatność, obok etapów',
		self::CREDITED => 'Zaliczany na poczet etapu',
	];

	private const LIMITS = ['name' => 190, 'milestone' => 500, 'due' => 190];

	/**
	 * @return array{payments: array, errors: array<string, string>}
	 */
	public static function parse(array $input): array
	{
		$errors = [];
		$stages = [];
		$rows = array_values((array) ($input['stages'] ?? []));

		if (count($rows) > self::MAX_STAGES) {
			return ['payments' => [], 'errors' => ['stages' => sprintf('Najwyżej %d etapów.', self::MAX_STAGES)]];
		}

		foreach ($rows as $row) {
			$row = (array) $row;

			if (implode('', array_map(fn ($value) => is_scalar($value) ? trim((string) $value) : '', $row)) === '') {
				continue;
			}

			$i = count($stages);
			$stage = ['net' => Input::money($row['net'] ?? null), 'percent' => null];

			foreach (self::LIMITS as $field => $limit) {
				$stage[$field] = ContractValues::text($row[$field] ?? null);

				if ($stage[$field] !== null && mb_strlen($stage[$field]) > $limit) {
					$errors["stages.{$i}.{$field}"] = sprintf('Najwyżej %d znaków.', $limit);
				}
			}

			if ($stage['name'] === null) {
				$errors["stages.{$i}.name"] = 'Podaj nazwę etapu.';
			}

			if ($stage['net'] === null) {
				$errors["stages.{$i}.net"] = 'Podaj kwotę netto, np. 1 500,50.';
			}

			if (filled($row['percent'] ?? null)) {
				$stage['percent'] = self::percent($row['percent']);

				if ($stage['percent'] === null) {
					$errors["stages.{$i}.percent"] = 'Podaj procent od 0 do 100.';
				}
			}

			$stages[] = $stage;
		}

		$agreed = filled($input['agreed_net'] ?? null) ? Input::money($input['agreed_net']) : null;

		if (filled($input['agreed_net'] ?? null) && $agreed === null) {
			$errors['agreed_net'] = 'Podaj kwotę netto, np. 12 000,00.';
		}

		$rate = self::percent($input['vat_rate'] ?? null);

		if ($rate === null) {
			$errors['vat_rate'] = 'Podaj stawkę VAT od 0 do 100.';
		}

		$deposit = null;

		if (filled($input['deposit_net'] ?? null)) {
			$mode = (string) ($input['deposit_mode'] ?? '');
			$credited = $mode === self::CREDITED;
			$deposit = [
				'net' => Input::money($input['deposit_net']),
				'mode' => isset(self::MODES[$mode]) ? $mode : null,
				'stage' => $credited ? (int) ($input['deposit_stage'] ?? 0) : null,
				'due' => ContractValues::text($input['deposit_due'] ?? null),
			];

			if ($deposit['net'] === null) {
				$errors['deposit_net'] = 'Podaj kwotę netto, np. 1 000,00.';
			}

			if ($credited && ($deposit['stage'] < 1 || $deposit['stage'] > count($stages))) {
				$errors['deposit_stage'] = 'Podaj numer istniejącego etapu.';
			}

			if ($deposit['due'] !== null && mb_strlen($deposit['due']) > self::LIMITS['due']) {
				$errors['deposit_due'] = sprintf('Najwyżej %d znaków.', self::LIMITS['due']);
			}
		}

		return ['payments' => ['agreed_net' => $agreed, 'vat_rate' => $rate, 'stages' => $stages, 'deposit' => $deposit], 'errors' => $errors];
	}

	public static function isEmpty(array $payments): bool
	{
		return ($payments['agreed_net'] ?? null) === null && empty($payments['stages']) && empty($payments['deposit']);
	}

	public static function forSnapshot(array $snapshot): array
	{
		return self::summary((array) ($snapshot['payments'] ?? []), (array) ($snapshot['pricing']['amounts']['variant'] ?? []), (string) ($snapshot['offer']['currency'] ?? 'PLN'));
	}

	/**
	 * @param  array  $estimate  cena orientacyjna {min, max}, tylko do ostrzeżenia
	 * @return array{agreed: ?array, stages: list<array>, deposit: ?array, paid: array, blockers: list<string>, warnings: list<string>, currency: string}
	 */
	public static function summary(array $payments, array $estimate = [], string $currency = 'PLN'): array
	{
		$rate = (string) ($payments['vat_rate'] ?? '0');
		$money = fn (int $amount) => Format::money($amount, $currency);
		$agreed = isset($payments['agreed_net']) ? self::amounts((int) $payments['agreed_net'], $rate) : null;
		$stages = [];

		foreach ((array) ($payments['stages'] ?? []) as $stage) {
			$amounts = self::amounts((int) $stage['net'], $rate);
			$stages[] = array_merge($stage, $amounts, [
				'share' => $agreed && $agreed['gross'] > 0 ? self::share($amounts['gross'], $agreed['gross']) : null,
				'credit' => 0,
				'payable' => $amounts['gross'],
			]);
		}

		$deposit = ! empty($payments['deposit']) ? array_merge($payments['deposit'], self::amounts((int) $payments['deposit']['net'], $rate)) : null;
		$blockers = [];
		$warnings = [];

		if (! $agreed) {
			$blockers[] = 'Podaj uzgodnioną kwotę umowy.';
		}

		if ($agreed && ! $stages) {
			$blockers[] = 'Dodaj co najmniej jeden etap płatności.';
		}

		if ($deposit && ($deposit['mode'] ?? null) === null) {
			$blockers[] = 'Wybierz, czy zadatek jest odrębną płatnością, czy zalicza się na poczet etapu.';
		}

		$separate = $deposit && ($deposit['mode'] ?? null) === self::SEPARATE;

		if ($deposit && ($deposit['mode'] ?? null) === self::CREDITED) {
			$target = (int) ($deposit['stage'] ?? 0) - 1;

			if (! isset($stages[$target])) {
				$blockers[] = 'Zadatek wskazuje etap, którego nie ma w harmonogramie.';
			} elseif ($deposit['gross'] > $stages[$target]['gross']) {
				$blockers[] = sprintf('Zadatek (%s brutto) jest wyższy niż etap %d (%s brutto).', $money($deposit['gross']), $target + 1, $money($stages[$target]['gross']));
			} else {
				$stages[$target]['credit'] = $deposit['gross'];
				$stages[$target]['payable'] -= $deposit['gross'];
			}
		}

		$paid = [
			'net' => array_sum(array_column($stages, 'net')) + ($separate ? $deposit['net'] : 0),
			'gross' => array_sum(array_column($stages, 'gross')) + ($separate ? $deposit['gross'] : 0),
		];

		if ($agreed && $stages) {
			if ($paid['net'] !== $agreed['net']) {
				$blockers[] = sprintf('Płatności dają %s netto, a uzgodniona kwota to %s (różnica %s).', $money($paid['net']), $money($agreed['net']), $money(abs($paid['net'] - $agreed['net'])));
			} elseif ($paid['gross'] !== $agreed['gross']) {
				$warnings[] = sprintf('VAT liczony od każdej pozycji daje %s brutto, a od całej kwoty %s (różnica %s). Sprawdź, jak fakturować.', $money($paid['gross']), $money($agreed['gross']), $money(abs($paid['gross'] - $agreed['gross'])));
			}
		}

		$warnings = array_merge($warnings, self::percentWarnings($stages), self::estimateWarnings($agreed, $estimate, $money));

		return ['agreed' => $agreed, 'stages' => $stages, 'deposit' => $deposit, 'paid' => $paid, 'blockers' => $blockers, 'warnings' => $warnings, 'currency' => $currency];
	}

	/** Blokady zatwierdzenia. Umowa bez harmonogramu i bez tokenów kwot nie ma czego sprawdzać. */
	public static function blockers(array $snapshot): array
	{
		return self::isEmpty((array) ($snapshot['payments'] ?? [])) ? [] : self::forSnapshot($snapshot)['blockers'];
	}

	/** Tabela do treści umowy. Wszystkie teksty są escapowane; procent to zapis z umowy, a bez niego wynik z kwot. */
	public static function table(array $summary): string
	{
		$cell = fn (?string $text, string $tag = 'td') => "<{$tag}>" . htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</{$tag}>";
		$money = fn (int $amount) => Format::money($amount, $summary['currency']);
		$credited = array_sum(array_column($summary['stages'], 'credit')) > 0;
		$heads = ['Etap', 'Kamień milowy', 'Netto', 'VAT', 'Brutto', '%', 'Termin'];
		$rows = [];

		foreach ($summary['stages'] as $stage) {
			$percent = $stage['percent'] ?? $stage['share'];
			$rows[] = [$stage['name'], $stage['milestone'], $money($stage['net']), $money($stage['vat']), $money($stage['gross']), $percent === null ? '' : Format::percent($percent), $stage['due'], $money($stage['payable'])];
		}

		if ($deposit = $summary['deposit']) {
			$rows[] = ['Zadatek', '', $money($deposit['net']), $money($deposit['vat']), $money($deposit['gross']), '', $deposit['due'], $money($deposit['gross'])];
		}

		$html = '<table><thead><tr>' . implode('', array_map(fn ($head) => $cell($head, 'th'), $credited ? [...$heads, 'Do zapłaty'] : $heads)) . '</tr></thead><tbody>';

		foreach ($rows as $row) {
			$html .= '<tr>' . implode('', array_map($cell, $credited ? $row : array_slice($row, 0, 7))) . '</tr>';
		}

		return $html . '</tbody></table>';
	}

	/** Wersja do tokenu wstawionego w środku zdania, gdzie tabela nie jest poprawnym HTML. */
	public static function line(array $summary): ?string
	{
		$items = array_map(fn ($stage) => sprintf('%s: %s netto', $stage['name'], Format::money($stage['net'], $summary['currency'])), $summary['stages']);

		return $items ? implode('; ', $items) : null;
	}

	private static function amounts(int $net, string $rate): array
	{
		$vat = Decimal::of($net)->multipliedBy($rate)->dividedBy(100, 0, RoundingMode::HALF_UP)->toInt();

		return ['net' => $net, 'vat' => $vat, 'gross' => $net + $vat];
	}

	private static function share(int $gross, int $total): string
	{
		return (string) Decimal::of($gross)->multipliedBy(100)->dividedBy($total, 2, RoundingMode::HALF_UP);
	}

	private static function percent(mixed $value): ?string
	{
		$percent = Input::decimal($value, 2);

		return $percent !== null && Decimal::of($percent)->isLessThanOrEqualTo(100) ? $percent : null;
	}

	/** Tolerancja: 0,01 punktu na pozycję, bo 33,33% trzy razy to poprawny zapis. */
	private static function percentWarnings(array $stages): array
	{
		$warnings = [];
		$sum = Decimal::of(0);
		$declared = array_filter($stages, fn ($stage) => ($stage['percent'] ?? null) !== null);

		foreach ($declared as $i => $stage) {
			$sum = $sum->plus($stage['percent']);

			if ($stage['share'] !== null && Decimal::of($stage['percent'])->minus($stage['share'])->abs()->isGreaterThan('0.01')) {
				$warnings[] = sprintf('Etap %d: zapisano %s, a z kwot wynika %s brutto. Oba zapisy zostają, rozstrzygnij je przed zatwierdzeniem.', $i + 1, Format::percent($stage['percent']), Format::percent($stage['share']));
			}
		}

		if ($declared && $sum->minus(100)->abs()->isGreaterThan(Decimal::of('0.01')->multipliedBy(count($declared)))) {
			$warnings[] = sprintf('Zapisane procenty sumują się do %s, a nie do %s.', Format::percent((string) $sum), Format::percent('100'));
		}

		return $warnings;
	}

	private static function estimateWarnings(?array $agreed, array $estimate, callable $money): array
	{
		if (! $agreed || ! isset($estimate['min'], $estimate['max']) || ($agreed['net'] >= $estimate['min'] && $agreed['net'] <= $estimate['max'])) {
			return [];
		}

		return [sprintf('Uzgodniona kwota %s jest poza ceną orientacyjną wybranego wariantu (%s–%s). To dozwolone, ale sprawdź, czy jest celowe.', $money($agreed['net']), $money((int) $estimate['min']), $money((int) $estimate['max']))];
	}
}
