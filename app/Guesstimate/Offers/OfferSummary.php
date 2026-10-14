<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Http\Controllers\OfferController;
use App\Guesstimate\Offers\Version\VersionCost;
use App\Guesstimate\Sitemap\SitemapStats;
use App\Guesstimate\Pricing\RiskLevel;
use App\Guesstimate\Support\Format;

/**
 * Dane do widoków ofert (lista, szczegóły, podsumowanie kreatora) — gotowe teksty, bez logiki w Blade.
 */
final class OfferSummary
{
	/**
	 * @param  list<object>  $rows  wiersze z OfferQuery
	 */
	public static function rows(array $rows): array
	{
		return array_map(function ($row) {
			$budget = $row->calc_model === BudgetPricing::MODEL;
			$variant = $budget ? RiskLevel::tryFrom((string) $row->selected_variant) : null;
			$status = PublicationStatus::tryFrom((string) $row->publication_status) ?? PublicationStatus::Draft;
			$display = OfferDisplayStatus::resolve(OfferStatus::from($row->status), $status);
			// Link do skopiowania tylko przy aktywnej publikacji (nie po wycofaniu ani anulowaniu oferty).
			$live = $status === PublicationStatus::Published && $row->status !== OfferStatus::Cancelled->value && $row->public_token;

			return [
				'id' => $row->id,
				'number' => $row->number,
				'client' => $row->client_name ?? '—',
				'client_id' => $row->client_id,
				'project' => $row->project_name,
				'types' => $budget && $row->budget
					? (json_decode($row->budget, true)['project_type_label'] ?? (OfferVersion::PROJECT_TYPES[$row->project_type] ?? ''))
					: implode(', ', array_map(fn ($t) => OfferVersion::PROJECT_TYPES[$t] ?? $t, json_decode($row->project_types, true) ?: [])),
				'range' => $row->result_error ? 'Do poprawy' : Format::moneyRange($row->low_min, $row->high_max, $row->currency),
				'range_error' => (bool) $row->result_error,
				'variant' => $variant ? [
					'key' => $variant->value,
					'label' => $variant->label(),
					'range' => $row->result_error ? '—' : Format::moneyRange($row->{$variant->value . '_min'}, $row->{$variant->value . '_max'}, $row->currency),
				] : null,
				'status' => OfferStatus::from($row->status),
				'display' => $display,
				'publication' => $status,
				'public_url' => $live ? home_url('/offer/' . $row->public_token . '/') : null,
				'unpublished_changes' => $live && self::hasUnpublishedChanges($row),
				'first_view' => $row->first_viewed_at ? Format::date($row->first_viewed_at, true) : null,
				'last_view' => $row->last_viewed_at ? Format::date($row->last_viewed_at, true) : null,
				'views' => (int) $row->view_count,
				'author' => $row->author_name ?? '—',
				'created' => Format::date($row->created_at),
				'version' => (int) $row->version_no,
				// Edycja z listy tylko dla wersji edytowalnej; wysłane oferty edytuje się przez „Nową wersję” w szczegółach.
				'can_trash' => current_user_can(\App\Guesstimate\Auth\Capabilities::CREATE_OFFERS) && (current_user_can(\App\Guesstimate\Auth\Capabilities::EDIT_ALL_OFFERS) || (int) $row->author_id === get_current_user_id()),
				'editable' => $budget && ($row->status === OfferStatus::Draft->value || $row->status === OfferStatus::Prepared->value),
				// Edytor dokumentu: także dla wysłanych ofert (zamrożona wersja → wersja robocza w edytorze).
				'can_document' => $budget && $row->status !== OfferStatus::Cancelled->value && current_user_can(\App\Guesstimate\Auth\Capabilities::CREATE_OFFERS) && (current_user_can(\App\Guesstimate\Auth\Capabilities::EDIT_ALL_OFFERS) || (int) $row->author_id === get_current_user_id()),
			];
		}, $rows);
	}

	/**
	 * Aktywna publikacja, a wersja robocza ma zmiany, których klient jeszcze nie widzi (inna wersja albo inna
	 * treść). Szybki test na kolumnach; pełne porównanie snapshotu (PublicationService::outdated) tylko wtedy,
	 * gdy wersja była zmieniana po publikacji.
	 */
	private static function hasUnpublishedChanges(object $row): bool
	{
		if (! $row->publication_version_id) {
			return false;
		}

		if ((int) $row->publication_version_id !== (int) $row->current_version_id) {
			return true;
		}

		if ($row->version_updated_at && $row->published_at && strtotime($row->version_updated_at) < strtotime($row->published_at)) {
			return false;
		}

		$offer = Offer::with('currentVersion')->find($row->id);
		$publication = $offer ? Publication::find($offer->publication_id) : null;

		return $offer && $publication && PublicationService::outdated($offer, $publication);
	}

	public static function version(OfferVersion $version): array
	{
		if ($version->isBudget()) {
			return self::budgetVersion($version);
		}

		$version->loadMissing(['stages', 'templates', 'nodes', 'features', 'workItems', 'costs']);
		$currency = $version->currency;
		$stageNames = $version->stages->pluck('name', 'stage_key')->all();
		$globalRates = $version->settings_snapshot['hourly_rates'] ?? [];
		$overridden = array_keys(array_filter($version->rates(), fn ($rate, $level) => isset($globalRates[$level]) && (int) $globalRates[$level] !== $rate, ARRAY_FILTER_USE_BOTH));

		$templates = $version->templates->keyBy('id');
		$usage = $version->nodes->groupBy('template_id')->map->count();

		return [
			'budget' => false,
			'project_name' => $version->project_name,
			'current_url' => $version->current_url,
			'types' => $version->projectTypeLabels(),
			'description' => $version->description,
			'internal_notes' => $version->internal_notes,
			'ux' => sprintf('%s (%s)', $version->ux_label, Format::hoursRange(['min' => (string) $version->ux_hours_min, 'max' => (string) $version->ux_hours_max])),
			'model' => $version->calc_model === 'percent' ? 'Procentowy (jak w Excelu)' : 'Godzinowy',
			'locked' => $version->isLocked(),
			'locked_at' => Format::date($version->locked_at, true),
			'version_no' => $version->version_no,
			'error' => $version->result_error,
			'result' => ResultPresenter::present($version->result, $currency, $overridden),
			'rates_overridden' => (bool) $overridden,
			'stats' => SitemapStats::compute(
				$version->templates->map(fn ($t) => ['id' => $t->id, 'kind' => $t->kind])->all(),
				$version->nodes->map(fn ($n) => ['template_id' => $n->template_id, 'instances' => $n->instances, 'content_entry' => $n->content_entry])->all(),
			),
			'sitemap' => self::tree($version, $templates),
			'templates' => $version->templates->map(fn ($t) => [
				'name' => $t->name,
				'type' => $t->page_type_name,
				'kind' => $t->kind === 'unique' ? 'Indywidualna' : 'Szablon',
				'uses' => (int) ($usage[$t->id] ?? 0),
				'ui' => Format::hoursRange(['min' => (string) $t->ui_min, 'max' => (string) $t->ui_max]),
				'dev' => Format::hoursRange(['min' => (string) $t->dev_min, 'max' => (string) $t->dev_max]),
			])->all(),
			'features' => $version->features->map(fn ($f) => [
				'name' => $f->name,
				'category' => $f->category_name ?? ($f->is_custom ? 'Indywidualna' : '—'),
				'hours' => Format::hoursRange(['min' => (string) $f->hours_min, 'max' => (string) $f->hours_max]),
				'stage' => $stageNames[$f->stage_key] ?? $f->stage_key,
				'notes' => $f->notes,
				'custom' => $f->is_custom,
			])->all(),
			'work_items' => $version->workItems->map(fn ($w) => [
				'name' => $w->name,
				'hours' => Format::hoursRange(['min' => (string) $w->hours_min, 'max' => (string) $w->hours_max]),
				'stage' => $stageNames[$w->stage_key] ?? $w->stage_key,
				'notes' => $w->notes,
			])->all(),
			'costs' => $version->costs->map(fn ($c) => [
				'name' => $c->name,
				'type' => VersionCost::TYPES[$c->type] ?? $c->type,
				'billing' => VersionCost::BILLING[$c->billing] ?? $c->billing,
				'recurring' => $c->billing !== 'one_time',
				'amount' => Format::money($c->amount, $currency),
				'quantity' => Format::hours((string) $c->quantity, false),
				'notes' => $c->notes,
			])->all(),
			'stages_disabled' => $version->stages->where('enabled', false)->pluck('name')->all(),
		];
	}

	/**
	 * Wersja w modelu budżetowym (silnik 2.x).
	 */
	private static function budgetVersion(OfferVersion $version): array
	{
		$version->loadMissing(['nodes', 'features', 'workItems', 'costs']);
		$currency = $version->currency;
		$budget = $version->budget ?? [];
		$overridden = self::overriddenRates($version);
		$snapshot = VersionSnapshot::of($version);
		$defaults = $budget['page_hours'] ?? [];

		return [
			'budget' => true,
			'project_name' => $version->project_name,
			'type' => $version->projectTypeLabel(),
			'type_units' => $budget['type_units'] ?? '0',
			'ux' => sprintf('%s (%s)', $version->ux_label, Format::hoursRange(['min' => (string) $version->ux_hours_min, 'max' => (string) $version->ux_hours_max])),
			'internal_notes' => $version->internal_notes,
			'locked' => $version->isLocked(),
			'locked_at' => Format::date($version->locked_at, true),
			'version_no' => $version->version_no,
			'error' => $version->result_error,
			'variant' => $version->selected_variant,
			'variant_label' => RiskLevel::tryFrom($version->selected_variant)?->label() ?? '—',
			'result' => BudgetPresenter::present($version->result, $currency, $version->selected_variant, $overridden),
			'rates_overridden' => (bool) $overridden,
			'stats' => BudgetPricing::stats($snapshot),
			'page_hours' => sprintf('UI %s · Dev %s', Format::hoursRange(['min' => $defaults['ui_min'] ?? '0', 'max' => $defaults['ui_max'] ?? '0']), Format::hoursRange(['min' => $defaults['dev_min'] ?? '0', 'max' => $defaults['dev_max'] ?? '0'])),
			'pages' => $version->nodes->sortBy('position')->map(fn ($n) => [
				'name' => $n->name,
				'custom' => $n->hours_ui_min !== null || $n->hours_ui_max !== null || $n->hours_dev_min !== null || $n->hours_dev_max !== null,
			])->values()->all(),
			'features' => $version->features->map(fn ($f) => [
				'name' => $f->name,
				'category' => $f->category_name ?? ($f->is_custom ? 'Własna' : '—'),
				'description' => $f->description,
				'hours' => Format::hoursRange(['min' => (string) $f->hours_min, 'max' => (string) $f->hours_max]),
				'notes' => $f->notes,
				'custom' => $f->is_custom,
			])->all(),
			'work_items' => $version->workItems->map(fn ($w) => [
				'name' => $w->name,
				'hours' => Format::hoursRange(['min' => (string) $w->hours_min, 'max' => (string) $w->hours_max]),
				'notes' => $w->notes,
			])->all(),
			'costs' => $version->costs->map(fn ($c) => [
				'name' => $c->name,
				'type' => VersionCost::TYPES[$c->type] ?? $c->type,
				'billing' => VersionCost::BILLING[$c->billing] ?? $c->billing,
				'recurring' => $c->billing !== 'one_time',
				'amount' => Format::money($c->amount, $currency),
				'quantity' => Format::hours((string) $c->quantity, false),
				'notes' => $c->notes,
			])->all(),
			'content' => OfferContent::normalize($version->content),
			'content_missing' => OfferContent::missing($version->content),
		];
	}

	public static function overriddenRates(OfferVersion $version): array
	{
		$globalRates = $version->settings_snapshot['hourly_rates'] ?? [];

		return array_keys(array_filter($version->rates(), fn ($rate, $level) => isset($globalRates[$level]) && (int) $globalRates[$level] !== $rate, ARRAY_FILTER_USE_BOTH));
	}

	/**
	 * Drzewo mapy strony jako płaska lista z głębokością (do wcięć w widoku).
	 */
	private static function tree(OfferVersion $version, $templates): array
	{
		$byParent = $version->nodes->sortBy('position')->groupBy(fn ($n) => $n->parent_id ?? 0);
		$flat = [];
		$walk = function ($parentId, int $depth) use (&$walk, &$flat, $byParent, $templates) {
			foreach ($byParent->get($parentId, collect()) as $node) {
				$template = $templates->get($node->template_id);
				$flat[] = [
					'name' => $node->name,
					'depth' => $depth,
					'type' => $template?->page_type_name ?? '—',
					'template' => $template?->name,
					'kind' => $template?->kind,
					'instances' => $node->instances,
					'content' => $node->content_entry,
				];
				$walk($node->id, $depth + 1);
			}
		};
		$walk(0, 0);

		return $flat;
	}

	public static function versions(Offer $offer): array
	{
		return $offer->versions->map(fn (OfferVersion $v) => [
			'no' => $v->version_no,
			'current' => $v->id === $offer->current_version_id,
			'locked' => $v->isLocked(),
			'range' => $v->result_error ? 'Do poprawy' : Format::moneyRange($v->low_min, $v->high_max, $v->currency),
			'levels' => $v->result_error ? null : [
				'low' => Format::moneyRange($v->low_min, $v->low_max, $v->currency),
				'mid' => Format::moneyRange($v->mid_min, $v->mid_max, $v->currency),
				'high' => Format::moneyRange($v->high_min, $v->high_max, $v->currency),
			],
			'hours' => $v->hours_min !== null ? Format::hoursRange(['min' => (string) $v->hours_min, 'max' => (string) $v->hours_max]) : '—',
			'author' => OfferController::userName($v->created_by),
			'created' => Format::date($v->created_at, true),
			'note' => $v->change_note,
		])->all();
	}

	public static function events(Offer $offer): array
	{
		$sections = ['info' => 'informacje', 'sitemap' => 'mapa strony', 'features' => 'funkcjonalności', 'scope' => 'zakres prac', 'calculation' => 'kalkulacja', 'content' => 'treści oferty', 'appearance' => 'wygląd stron', 'document' => 'dokument oferty'];

		return $offer->events()->limit(100)->get()->map(function (OfferEvent $event) use ($sections) {
			$p = $event->payload ?? [];
			$text = match ($event->type) {
				'created' => 'Utworzono ofertę',
				'edited' => 'Zmieniono: ' . implode(', ', array_map(fn ($s) => $sections[$s] ?? $s, (array) ($p['sections'] ?? [$p['section'] ?? '']))),
				'status_changed' => sprintf('Status: %s → %s', OfferStatus::tryFrom($p['from'] ?? '')?->label() ?? '—', OfferStatus::tryFrom($p['to'] ?? '')?->label() ?? '—'),
				'version_created' => sprintf('Utworzono wersję %d (z wersji %d)', $p['version_no'] ?? 0, $p['from_version_no'] ?? 0),
				'duplicated_from' => 'Utworzono jako kopię oferty ' . ($p['number'] ?? ''),
				'duplicated_to' => 'Skopiowano do oferty ' . ($p['number'] ?? ''),
				'model_upgraded' => sprintf('Wersja %d przeniesiona do nowego modelu kalkulacji', $p['version_no'] ?? 0),
				'published' => sprintf('Opublikowano ofertę online (publikacja %d, wersja %d)', $p['number'] ?? 0, $p['version_no'] ?? 0),
				'withdrawn' => 'Wycofano ofertę online',
				'trashed' => 'Przeniesiono ofertę do kosza' . (! empty($p['was_published']) ? ' (publiczny link wyłączony)' : ''),
				'restored' => 'Przywrócono ofertę z kosza',
				'republished' => sprintf('Przywrócono publikację %d', $p['number'] ?? 0),
				default => $event->type,
			};

			return [
				'text' => $text,
				'note' => $p['note'] ?? null,
				'user' => OfferController::userName($event->user_id),
				'date' => Format::date($event->created_at, true),
				'link' => in_array($event->type, ['duplicated_from', 'duplicated_to'], true) && ! empty($p['offer_id']) ? route('gst.offers.show', $p['offer_id']) : null,
			];
		})->all();
	}
}
