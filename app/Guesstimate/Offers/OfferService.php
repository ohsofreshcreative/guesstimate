<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Clients\ClientService;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Document\DocumentSettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cykl życia oferty: utworzenie, wersje, statusy, duplikowanie i historia zdarzeń.
 */
class OfferService
{
	public function __construct(
		private SettingsRepository $settings,
		private OfferNumberGenerator $numbers,
		private VersionCalculator $calculator,
	) {
	}

	/**
	 * Nowa oferta z wersją 1 w modelu budżetowym. Stawki, zakres UX, rodzaje projektu, godziny na podstronę,
	 * warsztaty, podział i zaokrąglenia są kopiowane z ustawień w tej chwili i od tej pory należą do oferty.
	 *
	 * @param  array{client_id: int, project_name: string, project_type: string, internal_notes: ?string, ux_scope: string}  $data
	 */
	public function create(array $data): Offer
	{
		$settings = $this->settings->all();
		$scope = $settings['ux_scopes'][$data['ux_scope']] ?? throw new InvalidArgumentException('Nieznany zakres UX.');
		$userId = get_current_user_id();
		$snapshot = BudgetSettings::snapshot($settings);

		$offer = DB::transaction(function () use ($data, $settings, $scope, $userId, $snapshot) {
			// Klient nie może zostać usunięty w trakcie tworzenia oferty (blokada wiersza, zob. ClientService::delete).
			ClientService::lockActive((int) $data['client_id']);

			$offer = Offer::create([
				'number' => $this->numbers->next(),
				'client_id' => $data['client_id'],
				'status' => OfferStatus::Draft,
				'author_id' => $userId,
				'last_editor_id' => $userId,
			]);

			$version = OfferVersion::create([
				'offer_id' => $offer->id,
				'version_no' => 1,
				'project_name' => $data['project_name'],
				'current_url' => null,
				'project_type' => $data['project_type'],
				'project_types' => [$data['project_type']],
				'description' => null,
				'internal_notes' => $data['internal_notes'],
				'calc_model' => BudgetPricing::MODEL,
				'ux_scope' => $data['ux_scope'],
				'ux_label' => $scope['label'],
				'ux_hours_min' => $scope['min'],
				'ux_hours_max' => $scope['max'],
				'rate_low' => $settings['hourly_rates']['low'],
				'rate_mid' => $settings['hourly_rates']['mid'],
				'rate_high' => $settings['hourly_rates']['high'],
				'rates_overridden' => false,
				'currency' => $settings['currency'],
				'settings_snapshot' => $snapshot,
				'budget' => BudgetSettings::budget($snapshot, $data['project_type'], null, $data['custom_units'] ?? null),
				'selected_variant' => 'mid',
				'content' => OfferContent::blank(),
				'document' => DocumentSettings::initial($settings, $userId),
				'wizard_step' => 2,
				'created_by' => $userId,
				'updated_by' => $userId,
			]);

			$offer->update(['current_version_id' => $version->id]);
			$this->log($offer, 'created', $version->id, ['number' => $offer->number]);

			return $offer;
		});

		$this->calculator->recalculate($offer->currentVersion);

		return $offer->fresh();
	}

	/**
	 * Nowa wersja = pełna kopia bieżącej wersji (wszystkie parametry kalkulacji).
	 * Edycja wysłanej oferty wraca do statusu „Robocza” do czasu ponownej wysyłki.
	 */
	public function createVersion(Offer $offer, ?string $note = null): OfferVersion
	{
		$version = DB::transaction(function () use ($offer, $note) {
			$source = $offer->currentVersion;
			$next = (int) $offer->versions()->max('version_no') + 1;
			$copy = VersionCopier::copy($source, $offer->id, $next, get_current_user_id());
			$copy->update(['change_note' => $note, 'wizard_step' => 1]);
			$copy = $this->upgrade($offer, $copy);

			$from = $offer->status;
			$offer->update([
				'current_version_id' => $copy->id,
				'status' => $from->locksVersion() ? OfferStatus::Draft : $from,
				'last_editor_id' => get_current_user_id(),
			]);

			$this->log($offer, 'version_created', $copy->id, ['version_no' => $next, 'from_version_no' => $source->version_no, 'note' => $note]);

			if ($from !== $offer->status) {
				$this->log($offer, 'status_changed', $copy->id, ['from' => $from->value, 'to' => $offer->status->value]);
			}

			return $copy;
		});

		return $this->calculator->recalculate($version);
	}

	/**
	 * Kopia wersji ze starego modelu przechodzi na model budżetowy (decyzja: „stary model do nowej wersji”).
	 * Wersja źródłowa i jej wycena zostają bez zmian.
	 */
	private function upgrade(Offer $offer, OfferVersion $copy): OfferVersion
	{
		if ($copy->isBudget()) {
			return $copy;
		}

		$copy = LegacyConverter::convert($copy, $this->settings->all());
		$copy->update([
			'content' => $copy->content ?? OfferContent::blank(),
			'document' => $copy->document ?? DocumentSettings::initial($this->settings->all(), $offer->author_id),
		]);
		$this->log($offer, 'model_upgraded', $copy->id, ['version_no' => $copy->version_no]);

		return $copy;
	}

	public function changeStatus(Offer $offer, OfferStatus $to, ?string $note = null): void
	{
		$from = $offer->status;

		if (! $from->canTransitionTo($to)) {
			throw new InvalidArgumentException(sprintf('Nie można zmienić statusu z „%s” na „%s”.', $from->label(), $to->label()));
		}

		$version = $offer->currentVersion;

		if ($to === OfferStatus::Prepared && $version->result_error) {
			throw new InvalidArgumentException('Popraw kalkulację, zanim oznaczysz ofertę jako przygotowaną: ' . $version->result_error);
		}

		DB::transaction(function () use ($offer, $from, $to, $note, $version) {
			if ($to->locksVersion() && ! $version->isLocked()) {
				$version->update(['locked_at' => now()]);
			}

			$offer->update(['status' => $to, 'last_editor_id' => get_current_user_id()]);
			$this->log($offer, 'status_changed', $version->id, ['from' => $from->value, 'to' => $to->value, 'note' => $note]);
		});
	}

	public function duplicate(Offer $source): Offer
	{
		$offer = DB::transaction(function () use ($source) {
			$userId = get_current_user_id();
			$offer = Offer::create([
				'number' => $this->numbers->next(),
				'client_id' => $source->client_id,
				'status' => OfferStatus::Draft,
				'author_id' => $userId,
				'last_editor_id' => $userId,
				'duplicated_from_id' => $source->id,
			]);

			$copy = VersionCopier::copy($source->currentVersion, $offer->id, 1, $userId);
			$copy->update(['wizard_step' => 1]);
			$copy = $this->upgrade($offer, $copy);
			$offer->update(['current_version_id' => $copy->id]);

			$this->log($offer, 'duplicated_from', $copy->id, ['number' => $source->number, 'offer_id' => $source->id]);
			$this->log($source, 'duplicated_to', $source->current_version_id, ['number' => $offer->number, 'offer_id' => $offer->id]);

			return $offer;
		});

		$this->calculator->recalculate($offer->currentVersion);

		return $offer->fresh();
	}

	/**
	 * Zapis zmian w edytowalnej wersji: ostatni edytujący, data edycji i przeliczenie.
	 * Zapis dokumentu (teksty, wygląd stron) nie przelicza kalkulacji — nie ma na nią wpływu.
	 */
	public function touch(Offer $offer, OfferVersion $version, string $section, bool $recalculate = true): void
	{
		$userId = get_current_user_id();
		$version->update(['updated_by' => $userId]);
		$offer->update(['last_editor_id' => $userId, 'updated_at' => now()]);

		if ($recalculate) {
			$this->calculator->recalculate($version->fresh());
		}

		$this->log($offer, 'edited', $version->id, ['section' => $section], dedupeMinutes: 10);
	}

	public function log(Offer $offer, string $type, ?int $versionId = null, array $payload = [], int $dedupeMinutes = 0): void
	{
		$userId = get_current_user_id() ?: null;

		if ($dedupeMinutes > 0) {
			$recent = OfferEvent::where('offer_id', $offer->id)
				->where('type', $type)
				->where('user_id', $userId)
				->where('version_id', $versionId)
				->where('created_at', '>=', now()->subMinutes($dedupeMinutes))
				->latest('id')
				->first();

			if ($recent) {
				$sections = array_values(array_unique(array_merge((array) ($recent->payload['sections'] ?? [$recent->payload['section'] ?? null]), [$payload['section'] ?? null])));
				$recent->update(['payload' => ['sections' => array_filter($sections)], 'created_at' => now()]);

				return;
			}
		}

		OfferEvent::create([
			'offer_id' => $offer->id,
			'version_id' => $versionId,
			'user_id' => $userId,
			'type' => $type,
			'payload' => $payload,
			'created_at' => now(),
		]);
	}
}
