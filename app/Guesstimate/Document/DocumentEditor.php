<?php

namespace App\Guesstimate\Document;

use App\Guesstimate\Ai\AiService;
use App\Guesstimate\Auth\Capabilities;
use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferContent;
use App\Guesstimate\Offers\OfferPolicy;
use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Support\Input;

/**
 * Edytor wizualny dokumentu (krok 6 i „Edytuj dokument”). Edytuje wyłącznie istniejące pola wersji:
 * `content` (treści, które są też w kroku 5) i `document` (teksty i wygląd stron). Kalkulacja (godziny,
 * stawki, wariant, jednostki, funkcjonalności) nie jest częścią stanu edytora — cena w dokumencie
 * zawsze pochodzi z wyniku kalkulatora wersji.
 */
final class DocumentEditor
{
	/**
	 * Powód, dla którego wersji nie można edytować (null = można).
	 */
	public static function lockReason(Offer $offer, OfferVersion $version): ?string
	{
		return match (true) {
			! $version->isBudget() => 'legacy',
			! $offer->status->isEditable() => 'cancelled',
			$version->isLocked() => 'locked',
			! app(OfferPolicy::class)->canEdit($offer) => 'forbidden',
			default => null,
		};
	}

	/**
	 * Stan edytora: dokładnie te pola, które edytor może zmienić.
	 */
	public static function state(OfferVersion $version, array $settings): array
	{
		$doc = DocumentSettings::forVersion($version, $settings);
		unset($doc['fit_report']);
		$doc['price_includes'] = implode("\n", (array) $doc['price_includes']);
		$doc['include_features'] = ! empty($doc['include_features']);
		$doc['reference_ids'] = array_map('intval', (array) $doc['reference_ids']);
		$doc['contact_user_id'] = $doc['contact_user_id'] ? (int) $doc['contact_user_id'] : null;

		foreach ($doc['pages'] as $key => $page) {
			$doc['pages'][$key] = [
				'enabled' => (bool) $page['enabled'],
				'background_id' => $page['background_id'] ? (int) $page['background_id'] : null,
				'fit' => $page['fit'],
				'layout' => Pages::layout($key, $page['layout']),
				'image_id' => $page['image_id'] ? (int) $page['image_id'] : null,
				'heading' => $page['heading'] ?? null,
			];
		}

		return ['content' => OfferContent::normalize($version->content), 'document' => $doc];
	}

	/**
	 * Stan z formularza edytora po walidacji. „draft” — oczyszczone dane także przy błędach (podgląd LIVE).
	 *
	 * @return array{content: array, document: array, draft_content: array, draft_document: array, errors: array<string, string>}
	 */
	public static function apply(OfferVersion $version, array $input, array $settings): array
	{
		$current = DocumentSettings::forVersion($version, $settings);
		$docInput = (array) ($input['document'] ?? []);

		$content = OfferContent::validate((array) ($input['content'] ?? []));
		$texts = DocumentForm::content($docInput, $current);
		$design = DocumentForm::design((array) ($docInput['pages'] ?? []), $texts['draft']);

		$errors = array_merge(
			$content['errors'],
			collect($texts['errors'])->mapWithKeys(fn ($m, $k) => ['document.' . $k => $m])->all(),
			collect($design['errors'])->mapWithKeys(fn ($m, $k) => ['document.' . $k => $m])->all(),
		);

		return [
			'content' => $content['data'],
			'document' => $design['data'],
			'draft_content' => $content['data'] ?? self::draftContent((array) ($input['content'] ?? [])),
			'draft_document' => $design['draft'],
			'errors' => $errors,
		];
	}

	/**
	 * Treść do podglądu, gdy walidacja nie przeszła (np. 2 bloki zamiast 3) — czyszczona jak przy zapisie.
	 */
	private static function draftContent(array $input): array
	{
		$blocks = array_map(fn ($b) => [
			'title' => mb_substr((string) (Input::line($b['title'] ?? null) ?? ''), 0, 190),
			'html' => OfferContent::clean($b['html'] ?? ''),
		], array_slice(array_values((array) ($input['blocks'] ?? [])), 0, OfferContent::MAX_BLOCKS));

		return ['situation' => OfferContent::clean($input['situation'] ?? ''), 'blocks' => $blocks];
	}

	/**
	 * Dane widoku edytora.
	 */
	public static function data(Offer $offer, OfferVersion $version, SettingsRepository $settings, string $mode): array
	{
		$all = $settings->all();
		$lock = self::lockReason($offer, $version);
		$defaults = (array) ($all['document']['backgrounds'] ?? []);

		$pages = [];

		foreach (Pages::TYPES as $key => $label) {
			$pages[] = [
				'key' => $key,
				'label' => $label,
				'optional' => in_array($key, Pages::OPTIONAL, true),
				'fit' => in_array($key, Pages::FIT, true),
				'layouts' => array_map(fn ($l) => ['key' => $l, 'label' => Pages::LAYOUTS[$l], 'media' => Pages::hasMedia($l)], Pages::PAGE_LAYOUTS[$key]),
				'default_heading' => Pages::HEADINGS[$key] ?? null,
				'default_background_id' => isset($defaults[$key]) ? (int) $defaults[$key] : null,
			];
		}

		$state = self::state($version, $all);
		$imageIds = array_filter(array_column($state['document']['pages'], 'image_id'));

		return [
			'offer' => $offer,
			'version' => $version,
			'mode' => $mode,
			'lock' => $lock,
			'config' => [
				'editable' => $lock === null,
				'state' => $state,
				'pages' => $pages,
				'library' => DocumentDesigner::library(),
				'fits' => Background::FITS,
				// Miniatury wybranych zdjęć (pozostałe edytor pobiera z biblioteki mediów na żądanie).
				'images' => collect($imageIds)->mapWithKeys(fn ($id) => [$id => self::imageCard((int) $id)])->all(),
				'caseStudies' => CaseStudy::where('is_active', true)->orderBy('sort')->orderBy('id')->get()->map(fn (CaseStudy $c) => [
					'id' => $c->id, 'name' => $c->client_name, 'thumb' => Media::thumb($c->attachment_id),
				])->values()->all(),
				'team' => collect(Contacts::team())->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])->values()->all(),
				'maxReferences' => Pages::MAX_REFERENCES,
				'minBlocks' => OfferContent::MIN_BLOCKS,
				'maxBlocks' => OfferContent::MAX_BLOCKS,
				'ai' => AiService::status($settings),
				'canUpload' => current_user_can('upload_files'),
				'fitReport' => DocumentSettings::forVersion($version, $all)['fit_report']['situation'] ?? null,
				'urls' => [
					'frame' => route('gst.offers.document.frame', $offer),
					'preview' => route('gst.offers.document.preview', $offer),
					'save' => route('gst.offers.document.save', $offer),
					'fit' => route('gst.offers.document.fit', $offer),
					'media' => route('gst.media.index'),
					'upload' => route('gst.media.store'),
					'ai' => route('gst.ai.content', $offer),
					'calculation' => route('gst.offers.wizard', ['offer' => $offer, 'step' => 4]),
					'library' => current_user_can(Capabilities::MANAGE_SETTINGS) ? route('gst.settings.index', ['section' => 'tla']) : null,
				],
			],
		];
	}

	/**
	 * @return array{id: int, name: string, thumb: string|null, missing: bool}
	 */
	public static function imageCard(int $id): array
	{
		$exists = Media::isImage($id);

		return [
			'id' => $id,
			'name' => $exists ? (get_the_title($id) ?: basename((string) get_attached_file($id))) : 'Zdjęcie usunięte z biblioteki',
			'thumb' => $exists ? Media::thumb($id) : null,
			'missing' => ! $exists,
		];
	}
}
