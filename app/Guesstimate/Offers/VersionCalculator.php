<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Pricing\BudgetEngine;
use App\Guesstimate\Pricing\PricingEngine;
use App\Guesstimate\Pricing\PricingException;

/**
 * Przelicza wersję oferty na serwerze i zapisuje wynik (pełny JSON + kolumny do list i statystyk).
 * Zablokowanych wersji nie przeliczamy — ich wynik jest historyczny.
 */
class VersionCalculator
{
	public function __construct(private PricingEngine $engine, private BudgetEngine $budget)
	{
	}

	/**
	 * Wynik bez zapisu (podgląd). Zwraca [wynik|null, komunikat błędu|null].
	 *
	 * @return array{0: array|null, 1: string|null}
	 */
	public function preview(array $snapshot): array
	{
		try {
			if (($snapshot['calc_model'] ?? null) === BudgetPricing::MODEL) {
				return [$this->budget->calculate(BudgetPricing::toInput($snapshot)), null];
			}

			return [$this->engine->calculate(VersionPricing::toInput($snapshot)), null];
		} catch (PricingException $e) {
			return [null, $e->getMessage()];
		}
	}

	public function recalculate(OfferVersion $version): OfferVersion
	{
		if ($version->isLocked()) {
			return $version;
		}

		$version->unsetRelations();
		[$result, $error] = $this->preview(VersionSnapshot::of($version));

		$version->fill([
			'result' => $result,
			'result_error' => $error,
			'engine_version' => $version->isBudget() ? BudgetEngine::VERSION : PricingEngine::VERSION,
			'hours_min' => $result['hours']['min'] ?? null,
			'hours_max' => $result['hours']['max'] ?? null,
			'low_min' => $result['levels']['low']['total']['min'] ?? null,
			'low_max' => $result['levels']['low']['total']['max'] ?? null,
			'mid_min' => $result['levels']['mid']['total']['min'] ?? null,
			'mid_max' => $result['levels']['mid']['total']['max'] ?? null,
			'high_min' => $result['levels']['high']['total']['min'] ?? null,
			'high_max' => $result['levels']['high']['total']['max'] ?? null,
			'recurring_monthly' => $result['costs']['monthly'] ?? 0,
			'recurring_yearly' => $result['costs']['yearly'] ?? 0,
		])->save();

		return $version;
	}
}
