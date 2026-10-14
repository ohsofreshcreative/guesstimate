<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Document\DocumentBuilder;
use App\Guesstimate\Offers\Version\VersionCost;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Pricing\Decimal;
use App\Guesstimate\Pricing\RiskLevel;
use App\Guesstimate\Support\Format;

/**
 * Snapshot publicznej wersji oferty: wyłącznie to, co widzi klient.
 * Bez godzin, stawek, innych wariantów, notatek wewnętrznych i historii kalkulacji.
 * Kwoty są zapisane w snapshocie, więc późniejsze zmiany w ofercie nie zmieniają opublikowanej strony.
 */
final class PublicOffer
{
	public const SCHEMA = 2;

	/**
	 * @param  bool  $editor  podgląd w edytorze: dokument ze wszystkimi stronami (także ukrytymi)
	 */
	public static function build(Offer $offer, OfferVersion $version, ?array $settings = null, bool $editor = false): array
	{
		$settings ??= app(SettingsRepository::class)->all();
		$version->loadMissing(['nodes', 'features', 'workItems', 'costs']);
		$result = $version->result;
		$variant = RiskLevel::tryFrom($version->selected_variant) ?? RiskLevel::Mid;
		$level = $result['levels'][$variant->value];
		$currency = $version->currency;
		$content = OfferContent::normalize($version->content);
		// Treść jest czyszczona przy zapisie; czyścimy ponownie, bo trafia na publiczną stronę jako HTML.
		$content['situation'] = OfferContent::clean($content['situation']);
		$content['blocks'] = array_map(fn ($b) => ['title' => $b['title'], 'html' => OfferContent::clean($b['html'])], $content['blocks']);

		$parts = [];

		if ($level['workshops'] > 0) {
			$parts[] = ['label' => 'Warsztaty Discovery', 'min' => $level['workshops'], 'max' => $level['workshops']];
		}

		foreach (['ux' => 'UX Design', 'ui' => 'UI Design', 'dev' => 'Development i wdrożenie'] as $key => $label) {
			$parts[] = ['label' => $label, 'min' => $level['parts'][$key]['min'], 'max' => $level['parts'][$key]['max']];
		}

		return [
			'schema' => self::SCHEMA,
			'number' => $offer->number,
			'title' => $version->project_name,
			'client' => $offer->client?->company_name,
			'project_type' => $version->projectTypeLabel(),
			'currency' => $currency,
			'content' => $content,
			'price' => [
				'parts' => array_map(fn ($p) => $p + ['text' => Format::moneyRange($p['min'], $p['max'], $currency)], $parts),
				'total' => ['min' => $level['total']['min'], 'max' => $level['total']['max'], 'text' => Format::moneyRange($level['total']['min'], $level['total']['max'], $currency)],
			],
			'summary' => [
				'ux' => $version->ux_label,
				'pages' => $version->nodes->sortBy('position')->pluck('name')->values()->all(),
				'features' => $version->features->map(fn ($f) => array_filter(['name' => $f->name, 'description' => $f->description]))->values()->all(),
				'work' => $version->workItems->pluck('name')->values()->all(),
			],
			'document' => DocumentBuilder::build($offer, $version, $settings, $content, $editor),
			'costs' => $version->costs->map(fn ($c) => [
				'name' => $c->name,
				'billing' => VersionCost::BILLING_PUBLIC[$c->billing] ?? '',
				'amount' => Format::money(Decimal::money(Decimal::of((string) $c->quantity), (int) $c->amount), $currency),
				'quantity' => (float) $c->quantity !== 1.0 ? Format::hours((string) $c->quantity, false) : null,
			])->values()->all(),
		];
	}
}
