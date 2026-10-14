<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Pricing\CalcModel;
use App\Guesstimate\Pricing\Cost;
use App\Guesstimate\Pricing\Decimal;
use App\Guesstimate\Pricing\LineItem;
use App\Guesstimate\Pricing\PricingInput;
use App\Guesstimate\Pricing\Rounding;
use App\Guesstimate\Pricing\Stage;

/**
 * Zamienia snapshot wersji oferty (tablice z bazy) na PricingInput.
 *
 * Tu zapadają reguły domenowe mapy strony:
 * - szablon liczy się raz, niezależnie od liczby podstron przypisanych do niego,
 * - podstrona indywidualna ma własny (dedykowany) szablon,
 * - wprowadzanie treści = liczba sztuk × godziny na sztukę, w osobnym etapie,
 * - godziny UX pochodzą wyłącznie z wybranego zakresu UX (szablony mają tylko UI i Dev).
 */
final class VersionPricing
{
	public const UX_STAGE = 'ux';
	public const UI_STAGE = 'ui';
	public const DEV_STAGE = 'dev';

	public static function toInput(array $snapshot): PricingInput
	{
		return new PricingInput(
			model: CalcModel::from($snapshot['calc_model']),
			rates: array_map('intval', $snapshot['rates']),
			stages: array_map(fn (array $stage) => new Stage(
				key: $stage['key'],
				name: $stage['name'],
				enabled: (bool) $stage['enabled'],
				inPool: (bool) ($stage['in_pool'] ?? false),
				percent: (string) ($stage['percent'] ?? '0'),
				fixedAmount: isset($stage['fixed_amount']) && $stage['fixed_amount'] !== '' ? (int) $stage['fixed_amount'] : null,
			), $snapshot['stages']),
			items: self::items($snapshot),
			costs: array_map(fn (array $cost) => new Cost(
				name: $cost['name'],
				amount: (int) $cost['amount'],
				quantity: (string) $cost['quantity'],
				billing: $cost['billing'],
			), $snapshot['costs'] ?? []),
			rounding: new Rounding(
				hoursStep: (string) ($snapshot['rounding']['hours_step'] ?? '0'),
				priceStep: (int) ($snapshot['rounding']['price_step'] ?? 1),
			),
		);
	}

	/**
	 * @return list<LineItem>
	 */
	public static function items(array $snapshot): array
	{
		$items = [];

		if (! empty($snapshot['ux'])) {
			$items[] = new LineItem(LineItem::SOURCE_UX, $snapshot['ux']['label'], self::UX_STAGE, self::h($snapshot['ux']['min']), self::h($snapshot['ux']['max']));
		}

		foreach ($snapshot['stages'] as $stage) {
			if (self::positive($stage['manual_min'] ?? null) || self::positive($stage['manual_max'] ?? null)) {
				$items[] = new LineItem(LineItem::SOURCE_STAGE, $stage['name'], $stage['key'], self::h($stage['manual_min'] ?? 0), self::h($stage['manual_max'] ?? 0));
			}
		}

		$nodes = $snapshot['nodes'] ?? [];
		$usedTemplates = array_flip(array_filter(array_column($nodes, 'template_id')));

		foreach ($snapshot['templates'] ?? [] as $template) {
			if (! isset($usedTemplates[$template['id']])) {
				continue;
			}

			$label = 'Szablon: ' . $template['name'];
			$items[] = new LineItem(LineItem::SOURCE_TEMPLATE, $label, self::UI_STAGE, self::h($template['ui_min']), self::h($template['ui_max']));
			$items[] = new LineItem(LineItem::SOURCE_TEMPLATE, $label, self::DEV_STAGE, self::h($template['dev_min']), self::h($template['dev_max']));
		}

		$contentStage = $snapshot['content_stage'] ?? 'deploy';

		foreach ($nodes as $node) {
			$instances = max(1, (int) $node['instances']);

			if (! empty($node['content_entry'])) {
				$items[] = new LineItem(
					LineItem::SOURCE_CONTENT,
					sprintf('Treść: %s × %d', $node['name'], $instances),
					$contentStage,
					Decimal::format(Decimal::of(self::h($node['content_min']))->multipliedBy($instances)),
					Decimal::format(Decimal::of(self::h($node['content_max']))->multipliedBy($instances)),
				);
			}

			if (self::positive($node['extra_min'] ?? null) || self::positive($node['extra_max'] ?? null)) {
				$items[] = new LineItem(LineItem::SOURCE_PAGE_EXTRA, 'Dodatkowo: ' . $node['name'], self::DEV_STAGE, self::h($node['extra_min'] ?? 0), self::h($node['extra_max'] ?? 0));
			}
		}

		foreach ($snapshot['features'] ?? [] as $feature) {
			$items[] = new LineItem(LineItem::SOURCE_FEATURE, $feature['name'], $feature['stage_key'], self::h($feature['hours_min']), self::h($feature['hours_max']));
		}

		foreach ($snapshot['work_items'] ?? [] as $work) {
			$items[] = new LineItem(LineItem::SOURCE_WORK, $work['name'], $work['stage_key'], self::h($work['hours_min']), self::h($work['hours_max']));
		}

		return $items;
	}

	/**
	 * Godziny z bazy (DECIMAL) lub formularza jako napis z 2 miejscami po przecinku.
	 */
	private static function h(mixed $value): string
	{
		return Decimal::format(Decimal::of(($value === null || $value === '') ? '0' : (string) $value));
	}

	private static function positive(mixed $value): bool
	{
		return $value !== null && $value !== '' && Decimal::of((string) $value)->isPositive();
	}
}
