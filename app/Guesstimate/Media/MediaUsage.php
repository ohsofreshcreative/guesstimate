<?php

namespace App\Guesstimate\Media;

use App\Guesstimate\Document\Background;
use App\Guesstimate\Document\CaseStudy;
use App\Guesstimate\Document\MediaRefs;
use App\Guesstimate\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relacje grafiki z aplikacją, liczone z danych źródłowych (bez osobnej tabeli, która mogłaby się rozjechać):
 * publikacje, wersje ofert (także w koszu — można je przywrócić), tła, realizacje, logo dokumentu i tła umów.
 *
 * JSON_CONTAINS zamiast REGEXP: tekst kolumny JSON zależy od silnika (MySQL dodaje spacje po dwukropku).
 */
final class MediaUsage
{
	public const NONE = ['published' => [], 'versions' => [], 'backgrounds' => [], 'cases' => [], 'logo' => false, 'contracts' => []];

	/** Załączniki w snapshocie. Sprawdzane obok gst_media_refs: starsze publikacje mogą nie mieć kompletnych referencji. */
	private const SNAPSHOT_SQL = "JSON_CONTAINS(JSON_EXTRACT(snapshot, '$.document.logo_id', '$.document.pages[*].image.id', '$.document.pages[*].background.id', '$.document.references.items[*].image.id'), ?)";

	private const VERSION_SQL = "JSON_CONTAINS(JSON_EXTRACT(document, '$.pages.*.image_id'), ?)";

	/**
	 * @return array{published: list<string>, versions: list<string>, backgrounds: list<string>, cases: list<string>, logo: bool, contracts: list<string>}
	 */
	public static function forAttachment(int $id): array
	{
		$candidate = (string) $id;
		$published = self::numbers(DB::table('gst_publications')->whereRaw(self::SNAPSHOT_SQL, [$candidate])->distinct()->pluck('offer_id')->all());
		$published = array_values(array_unique(array_merge($published, MediaRefs::offersUsing($id))));
		sort($published);

		$versions = self::numbers(DB::table('gst_offer_versions')->whereRaw(self::VERSION_SQL, [$candidate])->distinct()->pluck('offer_id')->all());
		$document = (array) app(SettingsRepository::class)->get('document');

		return [
			'published' => $published,
			'versions' => array_values(array_diff($versions, $published)),
			'backgrounds' => Background::where('attachment_id', $id)->orderBy('name')->pluck('name')->all(),
			'cases' => CaseStudy::where('attachment_id', $id)->orderBy('client_name')->pluck('client_name')->all(),
			'logo' => (int) ($document['logo_attachment_id'] ?? 0) === $id,
			'contracts' => self::contracts($id),
		];
	}

	/**
	 * Szablony umów z tym tłem i zatwierdzone umowy, które mają jego kopię. Przed migracją tła umów lista jest pusta.
	 *
	 * @return list<string>
	 */
	private static function contracts(int $id): array
	{
		if (! Schema::hasColumn('gst_contract_templates', 'background_id')) {
			return [];
		}

		$templates = DB::table('gst_contract_templates')->where('background_id', $id)->orderBy('name')->pluck('name')->all();
		$approved = DB::table('gst_contract_versions')->where('background_id', $id)->distinct()->pluck('contract_id')->all();

		return [...array_map(fn ($name) => 'szablon „' . $name . '”', $templates), ...array_map(fn ($contract) => 'umowa #' . $contract, $approved)];
	}

	public static function inUse(array $usage): bool
	{
		return (bool) array_filter(array_replace(self::NONE, $usage));
	}

	/**
	 * Opis użycia do komunikatów („używają go: …”). Pusty, gdy grafika nie jest używana.
	 */
	public static function summary(array $usage): string
	{
		$usage = array_replace(self::NONE, $usage);
		$parts = array_filter([
			self::list('opublikowane oferty', $usage['published']),
			self::list('wersje ofert', $usage['versions']),
			self::list('tła', $usage['backgrounds']),
			self::list('realizacje', $usage['cases']),
			$usage['logo'] ? 'logo dokumentu' : '',
			self::list('tła umów:', $usage['contracts']),
		]);

		return $parts ? 'używają go: ' . implode('; ', $parts) : '';
	}

	private static function list(string $label, array $items): string
	{
		if (! $items) {
			return '';
		}

		$rest = count($items) - 5;

		return $label . ' ' . implode(', ', array_slice($items, 0, 5)) . ($rest > 0 ? sprintf(' i %d więcej', $rest) : '');
	}

	/**
	 * @param  list<int>  $offerIds
	 * @return list<string>
	 */
	private static function numbers(array $offerIds): array
	{
		return $offerIds ? DB::table('gst_offers')->whereIn('id', $offerIds)->orderBy('number')->pluck('number')->all() : [];
	}
}
