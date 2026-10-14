<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Pricing\RiskLevel;
use App\Guesstimate\Pricing\Stage;
use App\Guesstimate\Support\Format;

/**
 * Zamienia wynik silnika na gotowe do wyświetlenia wiersze (widoki nie liczą ani nie formatują).
 */
final class ResultPresenter
{
	public static function present(?array $result, string $currency = 'PLN', array $overriddenRates = []): ?array
	{
		if (! $result) {
			return null;
		}

		$levels = [];

		foreach (RiskLevel::cases() as $level) {
			$entry = $result['levels'][$level->value];
			$levels[] = [
				'key' => $level->value,
				'label' => $level->label(),
				'hours' => Format::hoursRange($entry['hours']),
				'rate' => Format::rate($entry['rate'], $currency),
				'rate_overridden' => in_array($level->value, $overriddenRates, true),
				'total' => Format::moneyRange($entry['total']['min'], $entry['total']['max'], $currency),
				'total_min' => Format::money($entry['total']['min'], $currency),
				'total_max' => Format::money($entry['total']['max'], $currency),
				'labor' => Format::moneyRange($entry['labor']['min'], $entry['labor']['max'], $currency),
				'rounding' => $entry['rounding']['min'] !== 0 || $entry['rounding']['max'] !== 0
					? Format::moneyRange($entry['rounding']['min'], $entry['rounding']['max'], $currency)
					: null,
			];
		}

		$stages = [];

		foreach ($result['stages'] as $stage) {
			$amounts = [];

			foreach (RiskLevel::cases() as $level) {
				$amount = $stage['amounts'][$level->value];
				$amounts[$level->value] = Format::moneyRange($amount['min'], $amount['max'], $currency);
			}

			$stages[] = [
				'key' => $stage['key'],
				'name' => $stage['name'],
				'enabled' => $stage['enabled'],
				'source' => match ($stage['source']) {
					Stage::SOURCE_FIXED => 'Kwota stała',
					Stage::SOURCE_PERCENT => Format::percent($stage['percent']) . ' puli',
					default => 'Godziny',
				},
				'hours_min' => $stage['hours'] ? Format::hours($stage['hours']['min']) : '—',
				'hours_max' => $stage['hours'] ? Format::hours($stage['hours']['max']) : '—',
				'amounts' => $amounts,
			];
		}

		$costs = $result['costs'];

		return [
			'model' => $result['model'],
			'hours' => Format::hoursRange($result['hours']),
			'pool_hours' => $result['pool_hours'] ? Format::hoursRange($result['pool_hours']) : null,
			'levels' => $levels,
			'stages' => $stages,
			'range' => Format::moneyRange($result['range']['min'], $result['range']['max'], $currency),
			'one_time_costs' => $costs['one_time'] > 0 ? Format::money($costs['one_time'], $currency) : null,
			'recurring' => $costs['monthly'] > 0 || $costs['yearly'] > 0 ? [
				'monthly' => Format::money($costs['monthly'], $currency),
				'yearly' => Format::money($costs['yearly'], $currency),
				'annual' => Format::money($costs['annual_recurring'], $currency),
			] : null,
			'warnings' => $result['warnings'],
			'engine_version' => $result['engine_version'],
		];
	}
}
