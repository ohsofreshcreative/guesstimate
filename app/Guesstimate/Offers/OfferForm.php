<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Auth\Capabilities;
use App\Guesstimate\Clients\Client;
use App\Guesstimate\Clients\ClientService;
use App\Guesstimate\Library\FeatureCategory;
use App\Guesstimate\Library\FeatureService;
use App\Guesstimate\Offers\Version\VersionCost;
use App\Guesstimate\Pricing\Decimal;
use App\Guesstimate\Settings\Defaults;
use App\Guesstimate\Settings\RatesValidator;
use App\Guesstimate\Support\Input;

/**
 * Walidacja kroków kreatora oferty. Zwraca znormalizowane dane albo błędy (klucz pola => komunikat).
 */
class OfferForm
{
	public const MAX_ROWS = 200;

	public function __construct(private ClientService $clients)
	{
	}

	/**
	 * Krok 1: projekt — klient (lub nowy klient), nazwa, jeden rodzaj projektu, zakres UX, notatki wewnętrzne.
	 *
	 * @return array{data: array|null, client: array|null, errors: array<string, string>}
	 */
	public function info(array $input, array $uxScopes, ?string $currentType = null): array
	{
		$errors = [];
		$client = null;

		if (! empty($input['new_client'])) {
			if (! current_user_can(Capabilities::CREATE_CLIENTS)) {
				$errors['client_id'] = 'Nie możesz dodawać klientów.';
			} else {
				['data' => $client, 'errors' => $clientErrors] = $this->clients->validate($input, 'client_');
				$errors = array_merge($errors, $clientErrors);
			}
		} elseif (! Client::whereKey((int) ($input['client_id'] ?? 0))->exists()) {
			$errors['client_id'] = 'Wybierz klienta albo dodaj nowego.';
		}

		$name = Input::line($input['project_name'] ?? null);

		if ($name === null || mb_strlen($name) > 190) {
			$errors['project_name'] = 'Podaj nazwę projektu (do 190 znaków).';
		}

		$type = (string) ($input['project_type'] ?? '');

		// Wycofany rodzaj (np. „Aplikacja webowa”) można zachować tylko w ofercie, która już go ma.
		if (! in_array($type, OfferVersion::SELECTABLE_TYPES, true) && ! ($currentType !== null && $type === $currentType && array_key_exists($type, OfferVersion::PROJECT_TYPES))) {
			$errors['project_type'] = 'Wybierz rodzaj projektu.';
		}

		$customUnits = null;

		if ($type === OfferVersion::CUSTOM_UNITS_TYPE) {
			$raw = trim((string) ($input['custom_units'] ?? '0'));
			$raw = $raw === '' ? '0' : $raw;

			if (! preg_match('/^\d{1,3}$/', $raw) || (int) $raw > OfferVersion::MAX_CUSTOM_UNITS) {
				$errors['custom_units'] = sprintf('Podaj liczbę całkowitą od 0 do %d.', OfferVersion::MAX_CUSTOM_UNITS);
			} else {
				$customUnits = (string) (int) $raw;
			}
		}

		$ux = (string) ($input['ux_scope'] ?? '');

		if (! array_key_exists($ux, $uxScopes)) {
			$errors['ux_scope'] = 'Wybierz zakres UX — każda oferta musi go mieć.';
		}

		$data = [
			'client_id' => (int) ($input['client_id'] ?? 0),
			'project_name' => $name,
			'project_type' => $type,
			'custom_units' => $customUnits,
			'internal_notes' => self::longText($input['internal_notes'] ?? null),
			'ux_scope' => $ux,
		];

		return ['data' => $errors ? null : $data, 'client' => $client, 'errors' => $errors];
	}

	/**
	 * Krok 3: funkcjonalności oferty (kopie z biblioteki i własne pozycje).
	 *
	 * @return array{rows: list<array>|null, library: list<array>, errors: array<string, string>}
	 */
	public function features(array $rows): array
	{
		$errors = [];
		$clean = [];
		$library = [];

		if (count($rows) > self::MAX_ROWS) {
			return ['rows' => null, 'library' => [], 'errors' => ['features' => 'Za dużo pozycji.']];
		}

		foreach (array_values($rows) as $i => $row) {
			$prefix = "features.{$i}.";
			$name = Input::line($row['name'] ?? null);

			if ($name === null || mb_strlen($name) > 190) {
				$errors[$prefix . 'name'] = 'Podaj nazwę funkcjonalności.';
			}

			[$min, $max, $hoursErrors] = FeatureService::hours($row['hours_min'] ?? null, $row['hours_max'] ?? null, $prefix . 'hours_min', $prefix . 'hours_max');
			$errors = array_merge($errors, $hoursErrors);
			$stage = (string) ($row['stage_key'] ?? 'dev');

			if (! in_array($stage, Defaults::STAGE_KEYS, true)) {
				$errors[$prefix . 'stage_key'] = 'Wybierz etap.';
			}

			$featureId = (int) ($row['feature_id'] ?? 0) ?: null;
			$item = [
				'feature_id' => $featureId,
				'category_name' => Input::line($row['category_name'] ?? null),
				'name' => $name,
				'description' => self::longText($row['description'] ?? null),
				'notes' => self::longText($row['notes'] ?? null),
				'hours_min' => $min,
				'hours_max' => $max,
				'stage_key' => $stage,
				'is_custom' => $featureId === null,
				'sort' => $i,
			];

			if ($featureId === null && ! empty($row['save_to_library'])) {
				$categoryId = (int) ($row['library_category_id'] ?? 0);

				if (! FeatureCategory::whereKey($categoryId)->exists()) {
					$errors[$prefix . 'library_category_id'] = 'Wybierz kategorię, w której zapisać funkcjonalność.';
				} else {
					$library[] = ['index' => $i, 'category_id' => $categoryId];
				}
			}

			$clean[] = $item;
		}

		return ['rows' => $errors ? null : $clean, 'library' => $library, 'errors' => $errors];
	}

	/**
	 * Krok 4: etapy, prace dodatkowe i koszty.
	 *
	 * @param  list<array>  $versionStages  aktualne etapy wersji (stage_key, in_pool)
	 * @return array{data: array|null, errors: array<string, string>}
	 */
	public function scope(array $input, array $versionStages, string $calcModel): array
	{
		$errors = [];
		$stages = [];
		$percentSum = Decimal::of(0);

		foreach ($versionStages as $stage) {
			$key = $stage['stage_key'];
			$raw = $input['stages'][$key] ?? [];
			$enabled = $key === 'ux' ? true : ! empty($raw['enabled']);
			[$min, $max, $hoursErrors] = FeatureService::hours($raw['manual_min'] ?? '0', $raw['manual_max'] ?? '0', "stages.{$key}.manual_min", "stages.{$key}.manual_max");
			$errors = array_merge($errors, $hoursErrors);

			$entry = ['enabled' => $enabled, 'manual_min' => $min, 'manual_max' => $max];

			if ($stage['in_pool']) {
				$percent = Input::decimal($raw['percent'] ?? null, 2);

				if ($percent === null || Decimal::of($percent)->isGreaterThan(100)) {
					$errors["stages.{$key}.percent"] = 'Podaj udział od 0 do 100%.';
				} else {
					$entry['percent'] = $percent;
					$percentSum = $enabled ? $percentSum->plus($percent) : $percentSum;
				}
			}

			if ($key === 'discovery') {
				$fixed = trim((string) ($raw['fixed_amount'] ?? ''));

				if ($fixed === '') {
					$entry['fixed_amount'] = null;
				} elseif (($amount = Input::money($fixed)) === null) {
					$errors['stages.discovery.fixed_amount'] = 'Podaj kwotę, np. 2000, albo zostaw puste, aby liczyć Discovery w godzinach.';
				} else {
					$entry['fixed_amount'] = $amount;
				}
			}

			$stages[$key] = $entry;
		}

		$hasPercentErrors = (bool) array_filter(array_keys($errors), fn ($key) => str_ends_with($key, '.percent'));

		if ($calcModel === 'percent' && ! $hasPercentErrors && ! $percentSum->isEqualTo(100)) {
			$errors['stages.percent_sum'] = 'W modelu procentowym udziały włączonych etapów UX, UI i Development muszą sumować się do 100%.';
		}

		[$work, $costs] = $this->workAndCosts($input, $errors);

		if (count($work) > self::MAX_ROWS || count($costs) > self::MAX_ROWS) {
			$errors['work_items'] = 'Za dużo pozycji.';
		}

		return ['data' => $errors ? null : ['stages' => $stages, 'work_items' => $work, 'costs' => $costs], 'errors' => $errors];
	}

	/**
	 * Krok 4 (model budżetowy): wariant, prace dodatkowe, koszty i ustawienia zaawansowane.
	 *
	 * @return array{data: array|null, errors: array<string, string>}
	 */
	public function calculation(array $input): array
	{
		$errors = [];
		[$work, $costs] = $this->workAndCosts($input, $errors);

		if (count($work) > self::MAX_ROWS || count($costs) > self::MAX_ROWS) {
			$errors['work_items'] = 'Za dużo pozycji.';
		}

		$variant = (string) ($input['selected_variant'] ?? '');

		if (! in_array($variant, ['low', 'mid', 'high'], true)) {
			$errors['selected_variant'] = 'Wybierz wariant cenowy.';
		}

		['rates' => $rates, 'errors' => $rateErrors] = RatesValidator::validate((array) ($input['rates'] ?? []));

		foreach ($rateErrors as $key => $message) {
			$errors['rates.' . $key] = $message;
		}

		$workshops = Input::money($input['workshops'] ?? null);

		if ($workshops === null) {
			$errors['workshops'] = 'Podaj kwotę warsztatów (0, jeśli bez warsztatów).';
		}

		$split = [];
		$sum = Decimal::of(0);

		foreach (['ux', 'ui', 'dev'] as $key) {
			$value = Input::decimal($input['split'][$key] ?? null, 2);

			if ($value === null || Decimal::of($value)->isGreaterThan(100)) {
				$errors['split.' . $key] = 'Podaj udział od 0 do 100%.';
				continue;
			}

			$split[$key] = rtrim(rtrim($value, '0'), '.') ?: '0';
			$sum = $sum->plus($value);
		}

		if (count($split) === 3 && ! $sum->isEqualTo(100)) {
			$errors['split'] = sprintf('Udziały UX, UI i Development muszą sumować się do 100%% (teraz %s%%).', rtrim(rtrim(Decimal::format($sum), '0'), '.'));
		} elseif (count($split) === 3 && Decimal::of($split['dev'])->isZero()) {
			$errors['split.dev'] = 'Udział Development musi być większy od zera.';
		}

		$hours = [];

		foreach (['ui', 'dev'] as $part) {
			[$min, $max, $hoursErrors] = FeatureService::hours($input['page_hours'][$part . '_min'] ?? null, $input['page_hours'][$part . '_max'] ?? null, "page_hours.{$part}_min", "page_hours.{$part}_max");
			$errors = array_merge($errors, $hoursErrors);
			$hours[$part . '_min'] = $min;
			$hours[$part . '_max'] = $max;
		}

		$units = Input::hours($input['type_units'] ?? null);

		if ($units === null) {
			$errors['type_units'] = 'Podaj liczbę jednostek (0 lub więcej).';
		}

		return ['data' => $errors ? null : [
			'selected_variant' => $variant,
			'rates' => $rates,
			'workshops' => $workshops,
			'split' => $split,
			'page_hours' => $hours,
			'type_units' => $units,
			'work_items' => $work,
			'costs' => $costs,
		], 'errors' => $errors];
	}

	/**
	 * Prace dodatkowe i koszty (wspólne dla obu modeli). Błędy dopisywane do $errors.
	 *
	 * @return array{0: list<array>, 1: list<array>}
	 */
	private function workAndCosts(array $input, array &$errors): array
	{
		$work = [];

		foreach (array_values((array) ($input['work_items'] ?? [])) as $i => $row) {
			$prefix = "work_items.{$i}.";
			$name = Input::line($row['name'] ?? null);

			if ($name === null || mb_strlen($name) > 190) {
				$errors[$prefix . 'name'] = 'Podaj nazwę pracy.';
			}

			[$min, $max, $hoursErrors] = FeatureService::hours($row['hours_min'] ?? null, $row['hours_max'] ?? null, $prefix . 'hours_min', $prefix . 'hours_max');
			$errors = array_merge($errors, $hoursErrors);
			$stage = (string) ($row['stage_key'] ?? 'other');

			if (! in_array($stage, Defaults::STAGE_KEYS, true)) {
				$errors[$prefix . 'stage_key'] = 'Wybierz etap.';
			}

			$work[] = ['name' => $name, 'stage_key' => $stage, 'hours_min' => $min, 'hours_max' => $max, 'notes' => self::longText($row['notes'] ?? null), 'sort' => $i];
		}

		$costs = [];

		foreach (array_values((array) ($input['costs'] ?? [])) as $i => $row) {
			$prefix = "costs.{$i}.";
			$name = Input::line($row['name'] ?? null);

			if ($name === null || mb_strlen($name) > 190) {
				$errors[$prefix . 'name'] = 'Podaj nazwę kosztu.';
			}

			$amount = Input::money($row['amount'] ?? null);

			if ($amount === null) {
				$errors[$prefix . 'amount'] = 'Podaj kwotę netto, np. 499 albo 499,99.';
			}

			$quantity = Input::decimal($row['quantity'] ?? '1', 2);

			if ($quantity === null || Decimal::of($quantity)->isZero()) {
				$errors[$prefix . 'quantity'] = 'Podaj ilość większą od zera.';
			}

			$type = (string) ($row['type'] ?? 'other');
			$billing = (string) ($row['billing'] ?? 'one_time');

			if (! array_key_exists($type, VersionCost::TYPES)) {
				$errors[$prefix . 'type'] = 'Wybierz rodzaj kosztu.';
			}

			if (! array_key_exists($billing, VersionCost::BILLING)) {
				$errors[$prefix . 'billing'] = 'Wybierz sposób rozliczenia.';
			}

			$costs[] = ['name' => $name, 'type' => $type, 'amount' => $amount, 'quantity' => $quantity, 'billing' => $billing, 'notes' => self::longText($row['notes'] ?? null), 'sort' => $i];
		}

		return [$work, $costs];
	}

	private static function longText(mixed $value): ?string
	{
		$value = Input::text($value);

		return $value === null ? null : mb_substr($value, 0, 5000);
	}
}
