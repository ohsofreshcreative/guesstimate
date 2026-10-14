<?php

namespace App\Guesstimate\Offers;

/**
 * Buduje snapshot wersji (tablice) dla VersionPricing z zapisanych wierszy wersji.
 * Korzysta wyłącznie z danych wersji — nigdy z ustawień globalnych.
 */
final class VersionSnapshot
{
	public static function of(OfferVersion $version): array
	{
		$version->loadMissing(['stages', 'templates', 'nodes', 'features', 'workItems', 'costs']);
		$settings = $version->settings_snapshot ?? [];

		return [
			'calc_model' => $version->calc_model,
			'rates' => $version->rates(),
			'ux' => ['label' => $version->ux_label, 'min' => (string) $version->ux_hours_min, 'max' => (string) $version->ux_hours_max],
			'content_stage' => $settings['content_stage'] ?? 'deploy',
			'rounding' => $settings['rounding'] ?? ['hours_step' => '0', 'price_step' => 1],
			'budget' => $version->budget ?? [],
			'stages' => $version->stages->map(fn ($stage) => [
				'key' => $stage->stage_key,
				'name' => $stage->name,
				'enabled' => $stage->enabled,
				'in_pool' => $stage->in_pool,
				'percent' => (string) $stage->percent,
				'fixed_amount' => $stage->fixed_amount,
				'manual_min' => (string) $stage->manual_min,
				'manual_max' => (string) $stage->manual_max,
			])->all(),
			'templates' => $version->templates->map(fn ($t) => [
				'id' => $t->id, 'name' => $t->name, 'kind' => $t->kind,
				'ui_min' => (string) $t->ui_min, 'ui_max' => (string) $t->ui_max,
				'dev_min' => (string) $t->dev_min, 'dev_max' => (string) $t->dev_max,
			])->all(),
			'nodes' => $version->nodes->map(fn ($n) => [
				'id' => $n->id, 'parent_id' => $n->parent_id, 'name' => $n->name, 'template_id' => $n->template_id,
				'instances' => $n->instances, 'content_entry' => $n->content_entry,
				'content_min' => (string) $n->content_min, 'content_max' => (string) $n->content_max,
				'extra_min' => (string) $n->extra_min, 'extra_max' => (string) $n->extra_max,
				'hours_ui_min' => $n->hours_ui_min, 'hours_ui_max' => $n->hours_ui_max,
				'hours_dev_min' => $n->hours_dev_min, 'hours_dev_max' => $n->hours_dev_max,
			])->all(),
			'features' => $version->features->map(fn ($f) => [
				'name' => $f->name, 'stage_key' => $f->stage_key, 'hours_min' => (string) $f->hours_min, 'hours_max' => (string) $f->hours_max,
			])->all(),
			'work_items' => $version->workItems->map(fn ($w) => [
				'name' => $w->name, 'stage_key' => $w->stage_key, 'hours_min' => (string) $w->hours_min, 'hours_max' => (string) $w->hours_max,
			])->all(),
			'costs' => $version->costs->map(fn ($c) => [
				'name' => $c->name, 'amount' => $c->amount, 'quantity' => (string) $c->quantity, 'billing' => $c->billing,
			])->all(),
		];
	}
}
