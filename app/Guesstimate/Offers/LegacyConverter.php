<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Offers\Version\SitemapNode;
use App\Guesstimate\Offers\Version\VersionStage;
use App\Guesstimate\Offers\Version\VersionTemplate;
use App\Guesstimate\Offers\Version\WorkItem;
use App\Guesstimate\Pricing\Decimal;

/**
 * Przenosi kopię wersji ze starego modelu (hourly / percent) do modelu budżetowego.
 * Wywoływane wyłącznie dla NOWEJ wersji lub duplikatu — wersja źródłowa zostaje bez zmian.
 *
 * - zachowuje stawki, zakres UX, funkcjonalności, prace dodatkowe i koszty,
 * - mapa strony staje się płaską listą podstron z domyślnymi godzinami na podstronę,
 * - ręczne godziny etapów i wprowadzanie treści przechodzą do prac dodatkowych (nic nie znika z wyceny po cichu),
 * - kwota stała Discovery i udziały UX/UI/Dev (model procentowy) są przejmowane, jeśli były ustawione.
 */
final class LegacyConverter
{
	public static function convert(OfferVersion $copy, array $settings): OfferVersion
	{
		$copy->load(['stages', 'nodes', 'templates']);
		$snapshot = array_merge(BudgetSettings::snapshot($settings), [
			'hourly_rates' => $copy->settings_snapshot['hourly_rates'] ?? $settings['hourly_rates'],
			'ux_scopes' => $copy->settings_snapshot['ux_scopes'] ?? $settings['ux_scopes'],
		]);

		$type = $copy->project_types[0] ?? 'website';
		$type = array_key_exists($type, OfferVersion::PROJECT_TYPES) ? $type : 'other';
		$budget = BudgetSettings::budget($snapshot, $type);
		$stages = $copy->stages->keyBy('stage_key');

		if ($stages->has('discovery') && $stages['discovery']->enabled && $stages['discovery']->fixed_amount !== null) {
			$budget['workshops'] = (int) $stages['discovery']->fixed_amount;
		}

		if ($copy->calc_model === 'percent' && $stages->has('ux') && $stages->has('ui') && $stages->has('dev')) {
			$split = ['ux' => self::n($stages['ux']->percent), 'ui' => self::n($stages['ui']->percent), 'dev' => self::n($stages['dev']->percent)];

			if (Decimal::of($split['ux'])->plus($split['ui'])->plus($split['dev'])->isEqualTo(100)) {
				$budget['split'] = $split;
			}
		}

		$sort = (int) $copy->workItems()->max('sort');

		foreach ($copy->stages as $stage) {
			if ((float) $stage->manual_max > 0 || (float) $stage->manual_min > 0) {
				WorkItem::create([
					'version_id' => $copy->id, 'name' => $stage->name, 'stage_key' => 'other',
					'hours_min' => $stage->manual_min, 'hours_max' => $stage->manual_max, 'sort' => ++$sort,
				]);
			}
		}

		[$contentMin, $contentMax] = [Decimal::of('0'), Decimal::of('0')];
		$position = 0;
		$nodes = self::preorder($copy->nodes);

		foreach ($nodes as $node) {
			if ($node->content_entry) {
				$contentMin = $contentMin->plus(Decimal::of((string) $node->content_min)->multipliedBy(max(1, $node->instances)));
				$contentMax = $contentMax->plus(Decimal::of((string) $node->content_max)->multipliedBy(max(1, $node->instances)));
			}

			$node->update([
				'parent_id' => null, 'position' => $position++, 'template_id' => null, 'instances' => 1,
				'content_entry' => false, 'content_min' => 0, 'content_max' => 0, 'extra_min' => 0, 'extra_max' => 0,
			]);
		}

		if ($contentMax->isPositive()) {
			WorkItem::create([
				'version_id' => $copy->id, 'name' => 'Wprowadzanie treści', 'stage_key' => 'other',
				'hours_min' => Decimal::format($contentMin), 'hours_max' => Decimal::format($contentMax), 'sort' => ++$sort,
			]);
		}

		VersionTemplate::where('version_id', $copy->id)->delete();
		VersionStage::where('version_id', $copy->id)->delete();

		$copy->update([
			'calc_model' => BudgetPricing::MODEL,
			'project_type' => $type,
			'project_types' => [$type],
			'budget' => $budget,
			'settings_snapshot' => $snapshot,
		]);

		return $copy->fresh();
	}

	/**
	 * Węzły drzewa w kolejności wyświetlania (rodzic, potem dzieci).
	 */
	private static function preorder($nodes): array
	{
		$byParent = $nodes->sortBy('position')->groupBy(fn (SitemapNode $n) => $n->parent_id ?? 0);
		$out = [];
		$walk = function ($parent) use (&$walk, &$out, $byParent) {
			foreach ($byParent->get($parent, collect()) as $node) {
				$out[] = $node;
				$walk($node->id);
			}
		};
		$walk(0);

		return $out;
	}

	private static function n(mixed $value): string
	{
		return rtrim(rtrim(Decimal::format(Decimal::of((string) $value)), '0'), '.');
	}
}
