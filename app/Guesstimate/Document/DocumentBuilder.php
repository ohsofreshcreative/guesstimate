<?php

namespace App\Guesstimate\Document;

use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferContent;
use App\Guesstimate\Offers\OfferVersion;

/**
 * Część dokumentowa snapshotu publikacji: kolejność i tła stron, okładka, usługi dodatkowe, realizacje,
 * kolejne kroki i kontakt — rozwiązane w chwili publikacji (adresy obrazów, dane osoby kontaktowej).
 * Późniejsze zmiany ustawień, biblioteki teł czy realizacji nie zmieniają opublikowanej oferty.
 */
final class DocumentBuilder
{
	/**
	 * @param  bool  $editor  tryb edytora: wszystkie strony (także wyłączone i puste — z oznaczeniem „hidden”)
	 */
	public static function build(Offer $offer, OfferVersion $version, array $settings, array $content, bool $editor = false): array
	{
		$doc = DocumentSettings::forVersion($version, $settings);
		$global = (array) ($settings['document'] ?? []);
		$version->loadMissing('features');

		$includes = $doc['price_includes'];

		if (! empty($doc['include_features'])) {
			foreach ($version->features as $feature) {
				if (! in_array($feature->name, $includes, true)) {
					$includes[] = $feature->name;
				}
			}
		}

		$references = CaseStudy::whereIn('id', $doc['reference_ids'] ?: [0])->get()->keyBy('id');
		$referenceItems = [];
		$referenceCrops = PagePolish::normalize($doc['pages']['references']['polish'] ?? null, 'references')['image_crops'] ?? [];

		foreach ($doc['reference_ids'] as $id) {
			if ($ref = $references->get($id)) {
				$image = Media::image($ref->attachment_id, 'large');
				$referenceItems[] = [
					'name' => $ref->client_name,
					'url' => $ref->url,
					'url_label' => $ref->url ? preg_replace('#^https?://#i', '', rtrim($ref->url, '/')) : null,
					'description' => $ref->description,
					'metrics' => array_slice(array_values(array_filter((array) $ref->metrics, fn ($m) => ($m['value'] ?? '') !== '')), 0, CaseStudy::MAX_METRICS),
					'image' => $image,
				] + ($image && isset($referenceCrops[$id]) ? ['image_crop' => $referenceCrops[$id]] : []);
			}
		}

		$has = [
			'cover' => true,
			'situation' => ! OfferContent::isEmpty($content['situation'] ?? ''),
			'solution' => (bool) array_filter($content['blocks'] ?? [], fn ($b) => ($b['title'] ?? '') !== ''),
			'pricing' => true,
			'extras' => (bool) $doc['extras'],
			'references' => (bool) $referenceItems,
			'next_steps' => (bool) $doc['next_steps'],
			'contact' => true,
		];

		$backgrounds = Background::whereIn('id', array_filter(array_column($doc['pages'], 'background_id')) ?: [0])->get()->keyBy('id');
		$pages = [];

		foreach ($doc['page_order'] as $key) {
			$page = $doc['pages'][$key] ?? null;

			if (! $page) {
				continue;
			}

			$type = Pages::type($key, $page);
			$extra = Pages::isExtra($key);
			$filled = $type === Pages::CUSTOM
				? trim((string) ($page['heading'] ?? '')) !== '' || ! OfferContent::isEmpty((string) ($page['html'] ?? ''))
				: $has[$type];
			$hidden = match (true) {
				($extra || in_array($type, Pages::OPTIONAL, true)) && empty($page['enabled']) => 'disabled',
				! $filled => 'empty',
				default => null,
			};

			if ($hidden && ! $editor) {
				continue;
			}

			$background = $page['background_id'] ? $backgrounds->get($page['background_id']) : null;
			$layout = Pages::layout($type, $page['layout'] ?? null);
			$image = Pages::hasMedia($layout) && ! empty($page['image_id']) ? Media::image((int) $page['image_id'], 'large') : null;
			$crop = $image ? ImageCrop::normalize($page['image_crop'] ?? null) : null;
			$fields = DocumentSettings::activeFields($doc, $key);
			$polish = (array) PagePolish::normalize($page['polish'] ?? null, $type);
			unset($polish['image_crops']); // w snapshocie kadr należy do realizacji

			if (! $image && $type !== 'references') {
				unset($polish['image_fit']);
			}

			$pages[] = [
				'key' => $key,
				'label' => Pages::label($key, $page),
				'background' => $background ? Media::image($background->attachment_id) : null,
				'background_id' => $background?->id,
				'fit' => DocumentSettings::fit($page, $background),
				'layout' => $layout,
				// Zdjęcie strony: adresy i wymiary zapisane w snapshocie — późniejsze zmiany w bibliotece mediów
				// nie zmieniają opublikowanej oferty.
				'image' => $image,
				'heading' => self::heading($type, $page['heading'] ?? null, $version),
				'hidden' => $hidden,
				// Klucze tylko przy ustawionych wartościach: inaczej każda aktywna publikacja wyglądałaby na nieaktualną.
			] + ($extra ? ['type' => $type] : []) + ($type === Pages::CUSTOM ? ['html' => OfferContent::clean($page['html'] ?? '')] : []) + ($crop ? ['image_crop' => $crop] : []) + ($fields ? ['fields' => $fields] : []) + ($polish ? ['polish' => $polish] : []);
		}

		$logoId = (int) ($global['logo_attachment_id'] ?? 0);
		$logo = $logoId ? Media::image($logoId, 'medium') : null;

		return [
			'pages' => $pages,
			'logo' => $logo['url'] ?? (($settings['public_offer']['logo_url'] ?? null) ?: null),
			'logo_id' => $logo ? $logoId : null,
			'website' => (string) ($global['website'] ?? ''),
			'validity_days' => (int) ($global['validity_days'] ?? 0),
			'cover' => [
				'heading' => Markup::parts($doc['cover_heading']),
				'lead' => $doc['cover_lead'],
				'date' => wp_date('Y-m-d'),
			],
			'includes' => $includes,
			'extras' => ['intro' => $doc['extras_intro'], 'items' => $doc['extras']],
			'references' => ['intro' => Markup::parts($doc['references_intro']), 'items' => $referenceItems],
			'next_steps' => ['items' => $doc['next_steps'], 'note' => Markup::parts($doc['next_steps_note'])],
			'contact' => Contacts::resolve($doc['contact_user_id'] ?? $offer->author_id, $settings),
		];
	}

	/**
	 * Nagłówek strony jako fragmenty Markup. Domyślny nagłówek wyceny dopisuje rodzaj projektu.
	 *
	 * @return list<array{0: string, 1: bool}>
	 */
	public static function heading(string $key, ?string $custom, ?OfferVersion $version = null): array
	{
		if ($key === 'cover') {
			return [];
		}

		if ($custom !== null && $custom !== '') {
			return Markup::parts($custom);
		}

		$parts = Markup::parts(Pages::HEADINGS[$key] ?? '');

		if ($key === 'pricing' && $version) {
			$parts[] = [' ' . mb_strtolower($version->projectTypeLabel()), false];
		}

		return $parts;
	}
}
