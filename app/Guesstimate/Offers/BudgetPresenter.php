<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Pricing\BudgetEngine;
use App\Guesstimate\Pricing\RiskLevel;
use App\Guesstimate\Support\Format;

/**
 * Wynik modelu budżetowego jako gotowe teksty dla widoków (panel wewnętrzny i oferta publiczna).
 */
final class BudgetPresenter
{
	public static function present(?array $result, string $currency = 'PLN', string $selected = 'mid', array $overriddenRates = []): ?array
	{
		if (! $result) {
			return null;
		}

		$levels = [];

		foreach (RiskLevel::cases() as $level) {
			$entry = $result['levels'][$level->value];
			$levels[$level->value] = [
				'key' => $level->value,
				'label' => $level->label(),
				'selected' => $level->value === $selected,
				'hours' => Format::hoursRange($entry['hours']),
				'rate' => Format::rate($entry['rate'], $currency),
				'rate_overridden' => in_array($level->value, $overriddenRates, true),
				'total' => Format::moneyRange($entry['total']['min'], $entry['total']['max'], $currency),
				'rounding' => $entry['rounding']['min'] !== 0 || $entry['rounding']['max'] !== 0
					? Format::moneyRange($entry['rounding']['min'], $entry['rounding']['max'], $currency)
					: null,
				'parts' => self::parts($entry, $currency),
			];
		}

		$costs = $result['costs'];

		return [
			'model' => 'budget',
			'hours' => Format::hoursRange($result['hours']),
			'levels' => $levels,
			'selected' => $levels[$selected] ?? $levels['mid'],
			'groups' => array_map(fn ($g) => [
				'label' => $g['label'],
				'count' => $g['count'],
				'hours' => Format::hoursRange($g['hours']),
			], $result['groups']),
			'lines' => array_map(fn ($l) => [
				'group' => BudgetEngine::GROUPS[$l['group']] ?? $l['group'],
				'label' => $l['label'],
				'hours' => Format::hoursRange($l['hours']),
			], $result['lines']),
			'split' => sprintf('UX %s · UI %s · Development %s', Format::percent($result['split']['ux']), Format::percent($result['split']['ui']), Format::percent($result['split']['dev'])),
			'workshops' => $result['workshops'] > 0 ? Format::money($result['workshops'], $currency) : null,
			'range' => Format::moneyRange($result['range']['min'], $result['range']['max'], $currency),
			'one_time_costs' => $costs['one_time'] > 0 ? Format::money($costs['one_time'], $currency) : null,
			'recurring' => $costs['monthly'] > 0 || $costs['yearly'] > 0 ? [
				'monthly' => Format::money($costs['monthly'], $currency),
				'yearly' => Format::money($costs['yearly'], $currency),
				'annual' => Format::money($costs['annual_recurring'], $currency),
			] : null,
			'engine_version' => $result['engine_version'],
		];
	}

	/**
	 * Wiersze podziału ceny: warsztaty (jeśli są), UX, UI, Development; suma = cena.
	 *
	 * @return list<array{key: string, label: string, amount: string}>
	 */
	public static function parts(array $entry, string $currency): array
	{
		$rows = [];

		if ($entry['workshops'] > 0) {
			$rows[] = ['key' => 'workshops', 'label' => 'Warsztaty Discovery', 'amount' => Format::money($entry['workshops'], $currency)];
		}

		foreach (BudgetEngine::PARTS as $key => $label) {
			$rows[] = ['key' => $key, 'label' => $label, 'amount' => Format::moneyRange($entry['parts'][$key]['min'], $entry['parts'][$key]['max'], $currency)];
		}

		return $rows;
	}
}
