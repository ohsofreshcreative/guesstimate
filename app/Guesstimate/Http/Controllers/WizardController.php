<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Ai\AiService;
use App\Guesstimate\Auth\Capabilities;
use App\Guesstimate\Clients\Client;
use App\Guesstimate\Clients\ClientPresenter;
use App\Guesstimate\Clients\ClientService;
use App\Guesstimate\Clients\ClientUnavailable;
use App\Guesstimate\Document\CaseStudy;
use App\Guesstimate\Document\Contacts;
use App\Guesstimate\Document\DocumentForm;
use App\Guesstimate\Document\DocumentSettings;
use App\Guesstimate\Document\Media;
use App\Guesstimate\Document\Pages;
use App\Guesstimate\Library\Feature;
use App\Guesstimate\Library\FeatureCategory;
use App\Guesstimate\Offers\BudgetPresenter;
use App\Guesstimate\Offers\BudgetSettings;
use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferContent;
use App\Guesstimate\Offers\OfferForm;
use App\Guesstimate\Offers\OfferPolicy;
use App\Guesstimate\Offers\OfferService;
use App\Guesstimate\Offers\OfferSummary;
use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Offers\PublicationService;
use App\Guesstimate\Offers\Version\VersionCost;
use App\Guesstimate\Offers\Version\VersionFeature;
use App\Guesstimate\Offers\Version\WorkItem;
use App\Guesstimate\Offers\VersionCalculator;
use App\Guesstimate\Offers\VersionSnapshot;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Sitemap\PageList;
use App\Guesstimate\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kreator oferty (model budżetowy): 6 kroków, każdy zapisywany osobno (wersja robocza w dowolnym momencie).
 * Edytować można tylko niezablokowaną wersję bieżącą w modelu budżetowym. Wersje ze starego modelu
 * są tylko do odczytu — zmiany wymagają nowej wersji (przechodzi ona na nowy model).
 */
class WizardController extends Controller
{
	public const STEPS = [
		1 => 'Projekt',
		2 => 'Mapa strony',
		3 => 'Funkcjonalności',
		4 => 'Kalkulacja',
		5 => 'Treści oferty',
		6 => 'Dokument i publikacja',
	];

	private const SECTIONS = [1 => 'info', 2 => 'sitemap', 3 => 'features', 4 => 'calculation', 5 => 'content'];

	private const VIEWS = [1 => 'info', 2 => 'pages', 3 => 'features', 4 => 'calculation', 5 => 'content'];

	public function __construct(
		private OfferPolicy $policy,
		private OfferService $offers,
		private OfferForm $form,
		private SettingsRepository $settings,
		private VersionCalculator $calculator,
		private ClientService $clients,
		private PublicationService $publications,
	) {
	}

	public function create(Request $request)
	{
		abort_unless($this->policy->canCreate(), 403, 'Nie możesz tworzyć ofert.');

		return view('guesstimate.wizard.info', $this->infoData(null, null, ['client_id' => (int) $request->query('klient', 0)]));
	}

	public function store(Request $request)
	{
		abort_unless($this->policy->canCreate(), 403, 'Nie możesz tworzyć ofert.');
		$result = $this->form->info($request->all(), $this->settings->uxScopes());

		if ($result['errors']) {
			return $this->invalid('guesstimate.wizard.info', $this->infoData(null, null), $result['errors']);
		}

		try {
			$offer = DB::transaction(function () use ($result) {
				$data = $result['data'];

				if ($result['client']) {
					$data['client_id'] = $this->clients->create($result['client'])->id;
				}

				return $this->offers->create($data);
			});
		} catch (ClientUnavailable $e) {
			return $this->invalid('guesstimate.wizard.info', $this->infoData(null, null), ['client_id' => $e->getMessage()]);
		}

		return $this->redirectWith(route('gst.offers.wizard', ['offer' => $offer, 'step' => 2]), sprintf('Utworzono ofertę %s jako wersję roboczą.', $offer->number));
	}

	public function show(Offer $offer, int $step)
	{
		abort_unless(isset(self::STEPS[$step]), 404);
		$this->policy->authorizeEdit($offer);
		$version = $offer->currentVersion;

		if ($blocked = $this->blocked($offer, $version, $step)) {
			return $blocked;
		}

		// Krok 6 to edytor wizualny dokumentu (ten sam co „Edytuj dokument”) z publikacją.
		if ($step === 6) {
			return app(DocumentController::class)->editor($offer, 'wizard');
		}

		return view($this->viewFor($step), $this->dataFor($step, $offer, $version));
	}

	public function save(Request $request, Offer $offer, int $step)
	{
		abort_unless(isset(self::STEPS[$step]), 404);
		$this->policy->authorizeEdit($offer);
		$version = $offer->currentVersion;

		if ($step === 6) {
			return redirect()->to(route('gst.offers.wizard', ['offer' => $offer, 'step' => 6]));
		}

		if ($blocked = $this->blocked($offer, $version, $step)) {
			return $blocked;
		}

		$errors = match ($step) {
			1 => $this->saveInfo($request, $version),
			2 => $this->savePages($request, $version),
			3 => $this->saveFeatures($request, $version),
			4 => $this->saveCalculation($request, $version),
			5 => $this->saveContent($request, $version),
		};

		if ($errors) {
			return $this->invalid($this->viewFor($step), $this->dataFor($step, $offer, $version->fresh()), $errors);
		}

		$this->offers->touch($offer, $version, self::SECTIONS[$step]);

		$next = $request->input('action') === 'next' ? min(6, $step + 1) : $step;
		$version->update(['wizard_step' => max($version->wizard_step, $next)]);

		$message = $next === $step
			? sprintf('Zapisano wersję roboczą o %s.', wp_date('H:i'))
			: sprintf('Zapisano krok „%s”.', self::STEPS[$step]);

		return $this->redirectWith(route('gst.offers.wizard', ['offer' => $offer, 'step' => $next]), $message);
	}

	/**
	 * Podgląd kalkulacji na żywo (krok 4, duży panel wariantów): liczy po stronie serwera, niczego nie zapisuje.
	 */
	public function preview(Request $request, Offer $offer)
	{
		$this->policy->authorizeEdit($offer);
		$version = $offer->currentVersion;
		abort_unless($version->isBudget(), 409);

		$live = $this->liveCalculation($version, 4, $request->all());

		return response()->view('guesstimate.wizard.partials.budget-preview', [
			'summary' => $live['summary'],
			'error' => $live['error'],
		]);
	}

	/**
	 * Wycena na żywo dla panelu podsumowania w każdym kroku: dane formularza bieżącego kroku nakładane
	 * na zapisaną wersję i liczone tym samym silnikiem PHP co przy zapisie. Niczego nie zapisuje.
	 */
	public function live(Request $request, Offer $offer, int $step)
	{
		$this->policy->authorizeEdit($offer);
		$version = $offer->currentVersion;
		abort_unless($version->isBudget() && isset(self::STEPS[$step]), 409);

		$live = $this->liveCalculation($version, $step, $request->except(['_gst_nonce', 'action', 'seq']));

		return response()->json([
			'seq' => (int) $request->input('seq'),
			'ok' => $live['error'] === null,
			'html' => view('guesstimate.wizard.partials.price-summary', [
				'summary' => $live['summary'],
				'error' => $live['error'],
				'live' => true,
			])->render(),
			'totals' => $live['summary'] ? [
				'hours' => $live['result']['hours'],
				'levels' => array_map(fn ($l) => $l['total'], $live['result']['levels']),
				'selected' => $live['variant'],
			] : null,
		]);
	}

	/**
	 * Snapshot wersji z nałożonymi (niezapisanymi) danymi kroku + wynik silnika.
	 *
	 * @return array{summary: array|null, result: array|null, error: string|null, variant: string}
	 */
	private function liveCalculation(OfferVersion $version, int $step, array $input): array
	{
		$snapshot = VersionSnapshot::of($version);
		$variant = $version->selected_variant;
		$overridden = OfferSummary::overriddenRates($version);
		$fail = fn (array $errors) => ['summary' => null, 'result' => null, 'error' => 'Nie można przeliczyć — ' . (string) reset($errors), 'variant' => $variant];

		switch ($step) {
			case 1:
				$scopes = $version->settings_snapshot['ux_scopes'] ?? $this->settings->uxScopes();
				$ux = (string) ($input['ux_scope'] ?? $version->ux_scope);
				$type = (string) ($input['project_type'] ?? $version->project_type);

				if (isset($scopes[$ux])) {
					$snapshot['ux'] = ['label' => $scopes[$ux]['label'], 'min' => (string) $scopes[$ux]['min'], 'max' => (string) $scopes[$ux]['max']];
				}

				if (array_key_exists($type, OfferVersion::PROJECT_TYPES)) {
					$custom = null;

					if ($type === OfferVersion::CUSTOM_UNITS_TYPE) {
						$raw = trim((string) ($input['custom_units'] ?? '0'));

						if ($raw !== '' && (! preg_match('/^\d{1,3}$/', $raw))) {
							return $fail(['Podaj liczbę całkowitą od 0 do 999.']);
						}

						$custom = (string) (int) $raw;
					}

					$snapshot['budget'] = BudgetSettings::budget($version->settings_snapshot, $type, $version->budget, $custom);
				}

				break;

			case 2:
				$rows = json_decode((string) ($input['payload'] ?? ''), true);

				if (! is_array($rows)) {
					return $fail(['Nie udało się odczytać mapy strony.']);
				}

				$parsed = PageList::parse($rows, $version->budget['page_hours'] ?? []);

				if ($parsed['errors']) {
					return $fail($parsed['errors']);
				}

				$snapshot['nodes'] = $parsed['pages'];

				break;

			case 3:
				// Zapis do biblioteki nie wpływa na cenę — w podglądzie go pomijamy.
				$rows = array_map(fn ($row) => array_diff_key((array) $row, ['save_to_library' => 1, 'library_category_id' => 1]), (array) ($input['features'] ?? []));
				$result = $this->form->features($rows);

				if ($result['errors']) {
					return $fail($result['errors']);
				}

				$snapshot['features'] = array_map(fn ($row) => [
					'name' => $row['name'], 'stage_key' => $row['stage_key'], 'hours_min' => $row['hours_min'], 'hours_max' => $row['hours_max'],
				], $result['rows']);

				break;

			case 4:
				$result = $this->form->calculation($input);

				if ($result['errors']) {
					return $fail($result['errors']);
				}

				$data = $result['data'];
				$snapshot['rates'] = $data['rates'];
				$snapshot['budget'] = array_merge($snapshot['budget'], [
					'workshops' => $data['workshops'], 'split' => $data['split'], 'page_hours' => $data['page_hours'], 'type_units' => $data['type_units'],
				]);
				$snapshot['work_items'] = $data['work_items'];
				$snapshot['costs'] = $data['costs'];
				$variant = $data['selected_variant'];
				$global = $version->settings_snapshot['hourly_rates'] ?? [];
				$overridden = array_keys(array_filter($data['rates'], fn ($rate, $level) => isset($global[$level]) && (int) $global[$level] !== $rate, ARRAY_FILTER_USE_BOTH));

				break;
		}

		[$calc, $error] = $this->calculator->preview($snapshot);

		return [
			'summary' => $calc ? BudgetPresenter::present($calc, $version->currency, $variant, $overridden) : null,
			'result' => $calc,
			'error' => $error,
			'variant' => $variant,
		];
	}

	private function saveInfo(Request $request, OfferVersion $version): array
	{
		$scopes = $version->settings_snapshot['ux_scopes'] ?? $this->settings->uxScopes();
		$result = $this->form->info($request->all(), $scopes, $version->project_type);

		if ($result['errors']) {
			return $result['errors'];
		}

		try {
			$this->saveInfoData($result, $version, $scopes);
		} catch (ClientUnavailable $e) {
			return ['client_id' => $e->getMessage()];
		}

		return [];
	}

	private function saveInfoData(array $result, OfferVersion $version, array $scopes): void
	{
		DB::transaction(function () use ($result, $version, $scopes) {
			$data = $result['data'];

			if ($result['client']) {
				$data['client_id'] = $this->clients->create($result['client'])->id;
			}

			// Blokada klienta na czas zmiany oferty (zob. ClientService::delete).
			ClientService::lockActive((int) $data['client_id']);

			$scope = $scopes[$data['ux_scope']];
			$version->offer->update(['client_id' => $data['client_id']]);
			$version->update([
				'project_name' => $data['project_name'],
				'project_type' => $data['project_type'],
				'project_types' => [$data['project_type']],
				'budget' => BudgetSettings::budget($version->settings_snapshot, $data['project_type'], $version->budget, $data['custom_units']),
				'internal_notes' => $data['internal_notes'],
				'ux_scope' => $data['ux_scope'],
				'ux_label' => $scope['label'],
				'ux_hours_min' => $scope['min'],
				'ux_hours_max' => $scope['max'],
			]);
		});
	}

	private function savePages(Request $request, OfferVersion $version): array
	{
		$rows = json_decode((string) $request->input('payload'), true);

		if (! is_array($rows)) {
			return ['payload' => 'Nie udało się odczytać mapy strony. Odśwież stronę i spróbuj ponownie.'];
		}

		$parsed = PageList::parse($rows, $version->budget['page_hours'] ?? []);

		if ($parsed['errors']) {
			return $parsed['errors'];
		}

		PageList::save($version, $parsed['pages']);

		return [];
	}

	private function saveFeatures(Request $request, OfferVersion $version): array
	{
		$result = $this->form->features((array) $request->input('features', []));

		if ($result['errors']) {
			return $result['errors'];
		}

		DB::transaction(function () use ($result, $version) {
			VersionFeature::where('version_id', $version->id)->delete();

			foreach ($result['rows'] as $i => $row) {
				$libraryEntry = collect($result['library'])->firstWhere('index', $i);

				// Zapis do biblioteki tylko dla osób, które nią zarządzają; dla pozostałych pozycja zostaje własną pozycją oferty.
				if ($libraryEntry && current_user_can(Capabilities::MANAGE_LIBRARY)) {
					$category = FeatureCategory::find($libraryEntry['category_id']);
					$feature = Feature::create([
						'category_id' => $category->id,
						'name' => $row['name'],
						'description' => $row['description'],
						'hours_min' => $row['hours_min'],
						'hours_max' => $row['hours_max'],
						'stage_key' => $row['stage_key'],
						'is_active' => true,
						'sort' => (int) Feature::where('category_id', $category->id)->max('sort') + 1,
						'created_by' => get_current_user_id(),
					]);
					$row['feature_id'] = $feature->id;
					$row['category_name'] = $category->name;
					$row['is_custom'] = false;
				}

				VersionFeature::create(array_merge($row, ['version_id' => $version->id]));
			}
		});

		return [];
	}

	private function saveCalculation(Request $request, OfferVersion $version): array
	{
		$result = $this->form->calculation($request->all());

		if ($result['errors']) {
			return $result['errors'];
		}

		$data = $result['data'];
		$global = array_map('intval', $version->settings_snapshot['hourly_rates'] ?? $this->settings->get('hourly_rates'));

		DB::transaction(function () use ($data, $version, $global) {
			$version->update([
				'rate_low' => $data['rates']['low'],
				'rate_mid' => $data['rates']['mid'],
				'rate_high' => $data['rates']['high'],
				'rates_overridden' => $data['rates'] !== $global,
				'selected_variant' => $data['selected_variant'],
				'budget' => array_merge($version->budget ?? [], [
					'workshops' => $data['workshops'],
					'split' => $data['split'],
					'page_hours' => $data['page_hours'],
					'type_units' => $data['type_units'],
				]),
			]);

			WorkItem::where('version_id', $version->id)->delete();
			VersionCost::where('version_id', $version->id)->delete();

			foreach ($data['work_items'] as $row) {
				WorkItem::create(array_merge($row, ['version_id' => $version->id]));
			}

			foreach ($data['costs'] as $row) {
				VersionCost::create(array_merge($row, ['version_id' => $version->id]));
			}
		});

		return [];
	}

	private function saveContent(Request $request, OfferVersion $version): array
	{
		$result = OfferContent::validate($request->all());
		$current = DocumentSettings::forVersion($version, $this->settings->all());
		// Pola dokumentu są w zwijanej sekcji; gdy formularz ich nie wysłał, zostają bez zmian.
		$document = $request->has('document')
			? DocumentForm::content((array) $request->input('document', []), $current)
			: ['data' => $current, 'errors' => []];
		$errors = array_merge($result['errors'], collect($document['errors'])->mapWithKeys(fn ($m, $k) => ['document.' . $k => $m])->all());

		if ($errors) {
			return $errors;
		}

		$version->update(['content' => $result['data'], 'document' => $document['data']]);

		return [];
	}

	/**
	 * Zablokowana, anulowana lub zapisana w starym modelu wersja: zamiast formularza strona z wyjaśnieniem.
	 * Krok 6 (podgląd i publikacja) jest dostępny także dla zamrożonej wersji — publikacja nie zmienia wyceny.
	 */
	private function blocked(Offer $offer, OfferVersion $version, int $step)
	{
		$reason = match (true) {
			! $version->isBudget() => 'legacy',
			! $offer->status->isEditable() && $step !== 6 => 'cancelled',
			$version->isLocked() && $step !== 6 => 'locked',
			default => null,
		};

		if ($reason === null) {
			return null;
		}

		return response(view('guesstimate.wizard.locked', ['offer' => $offer, 'version' => $version, 'reason' => $reason])->render(), 409);
	}

	private function viewFor(int $step): string
	{
		return 'guesstimate.wizard.' . self::VIEWS[$step];
	}

	private function dataFor(int $step, Offer $offer, OfferVersion $version): array
	{
		$base = [
			'offer' => $offer,
			'version' => $version,
			'step' => $step,
			'steps' => self::STEPS,
			'maxStep' => max(1, (int) $version->wizard_step),
			'sidebarSummary' => BudgetPresenter::present($version->result, $version->currency, $version->selected_variant),
			'sidebarError' => $version->result_error,
		];

		return array_merge($base, match ($step) {
			1 => $this->infoData($offer, $version),
			2 => $this->pagesData($version),
			3 => $this->featuresData($version),
			4 => $this->calculationData($version),
			5 => $this->contentData($version),
		});
	}

	private function infoData(?Offer $offer, ?OfferVersion $version, array $defaults = []): array
	{
		$scopes = BudgetSettings::uxScopes((array) ($version?->settings_snapshot['ux_scopes'] ?? $this->settings->uxScopes()));
		$types = BudgetSettings::types($version?->settings_snapshot ?? $this->settings->all(), $version?->project_type);
		// Po błędzie walidacji zachowujemy klienta wybranego w formularzu.
		$clientId = (int) (request()->input('client_id') ?: ($offer?->client_id ?? ($defaults['client_id'] ?? 0))) ?: null;
		$client = $clientId ? Client::find($clientId) : null;

		return [
			'offer' => $offer,
			'version' => $version,
			'step' => 1,
			'steps' => self::STEPS,
			'maxStep' => $version ? max(1, (int) $version->wizard_step) : 1,
			'sidebarSummary' => $version ? BudgetPresenter::present($version->result, $version->currency, $version->selected_variant) : null,
			'sidebarError' => $version?->result_error,
			'client' => $client ? ClientPresenter::card($client) : null,
			'clientSearchUrl' => route('gst.clients.search'),
			'clickupImportUrl' => route('gst.clients.clickup-import'),
			'canCreateClient' => current_user_can(Capabilities::CREATE_CLIENTS),
			'projectTypes' => collect($types)->map(fn ($t, $key) => [
				'label' => $t['label'],
				'units' => $key === OfferVersion::CUSTOM_UNITS_TYPE ? 'własna liczba jednostek' : Format::units($t['units']),
			])->all(),
			'uxScopes' => collect($scopes)->map(fn ($s) => ['label' => $s['label'], 'hours' => Format::hoursRange($s)])->all(),
			'values' => [
				'client_id' => $clientId,
				'project_name' => $version?->project_name,
				// Nowa oferta: domyślnie „Strona internetowa” i „Zoptymalizowany UX”; edycja zachowuje zapisany wybór.
				'project_type' => $version?->project_type ?? OfferVersion::DEFAULT_TYPE,
				'custom_units' => $version && $version->project_type === OfferVersion::CUSTOM_UNITS_TYPE ? (string) (int) ($version->budget['type_units'] ?? 0) : '0',
				'internal_notes' => $version?->internal_notes,
				'ux_scope' => $version?->ux_scope ?? (array_key_exists('optimized', $scopes) ? 'optimized' : array_key_first($scopes)),
			],
		];
	}

	private function pagesData(OfferVersion $version): array
	{
		$budget = $version->budget ?? [];

		return [
			'pages' => PageList::editorState($version),
			'pageHours' => array_map(fn ($h) => Format::hoursInput($h), $budget['page_hours'] ?? []),
			'typeLabel' => $version->projectTypeLabel(),
			'typeUnits' => Format::hoursInput($budget['type_units'] ?? '0'),
		];
	}

	private function featuresData(OfferVersion $version): array
	{
		$selected = $version->features()->get();

		return [
			'rows' => $selected->map(fn (VersionFeature $f) => [
				'feature_id' => $f->feature_id,
				'category_name' => $f->category_name,
				'name' => $f->name,
				'description' => (string) $f->description,
				'notes' => (string) $f->notes,
				'hours_min' => Format::hoursInput($f->hours_min),
				'hours_max' => Format::hoursInput($f->hours_max),
				'stage_key' => $f->stage_key,
				'save_to_library' => false,
				'library_category_id' => '',
			])->values()->all(),
			'library' => FeatureCategory::with(['features' => fn ($q) => $q->where('is_active', true)->orderBy('sort')->orderBy('name')])->orderBy('sort')->get()
				->filter(fn ($c) => $c->features->isNotEmpty())
				->map(fn ($c) => [
					'id' => $c->id,
					'name' => $c->name,
					'features' => $c->features->map(fn (Feature $f) => [
						'feature_id' => $f->id,
						'category_name' => $c->name,
						'name' => $f->name,
						'description' => (string) $f->description,
						'notes' => '',
						'hours_min' => Format::hoursInput($f->hours_min),
						'hours_max' => Format::hoursInput($f->hours_max),
						'stage_key' => $f->stage_key,
						'save_to_library' => false,
						'library_category_id' => '',
					])->values()->all(),
				])->values()->all(),
			'categories' => FeatureCategory::orderBy('sort')->pluck('name', 'id')->all(),
			'canSaveToLibrary' => current_user_can(Capabilities::MANAGE_LIBRARY),
			'ai' => AiService::status($this->settings),
			'aiUrl' => route('gst.ai.estimate', $version->offer_id),
		];
	}

	private function calculationData(OfferVersion $version): array
	{
		$budget = $version->budget ?? [];
		$global = $version->settings_snapshot['hourly_rates'] ?? [];

		return [
			'summary' => OfferSummary::version($version),
			'form' => [
				'selected_variant' => $version->selected_variant,
				'rates' => array_map(fn ($rate) => Format::moneyInput($rate), $version->rates()),
				'workshops' => Format::moneyInput((int) ($budget['workshops'] ?? 0)),
				'split' => array_map(fn ($p) => Format::hoursInput($p), $budget['split'] ?? []),
				'page_hours' => array_map(fn ($h) => Format::hoursInput($h), $budget['page_hours'] ?? []),
				'type_units' => Format::hoursInput($budget['type_units'] ?? '0'),
			],
			'workItems' => $version->workItems()->get()->map(fn ($w) => [
				'name' => $w->name,
				'hours_min' => Format::hoursInput($w->hours_min), 'hours_max' => Format::hoursInput($w->hours_max),
				'notes' => (string) $w->notes,
			])->values()->all(),
			'costs' => $version->costs()->get()->map(fn ($c) => [
				'name' => $c->name, 'type' => $c->type, 'amount' => Format::moneyInput($c->amount),
				'quantity' => Format::hoursInput($c->quantity), 'billing' => $c->billing, 'notes' => (string) $c->notes,
			])->values()->all(),
			'costTypes' => VersionCost::TYPES,
			'billing' => VersionCost::BILLING,
			'globalRates' => array_map(fn ($rate) => Format::rate((int) $rate, $version->currency), $global),
			'typeLabel' => $version->projectTypeLabel(),
		];
	}

	private function contentData(OfferVersion $version): array
	{
		$doc = DocumentSettings::forVersion($version, $this->settings->all());

		return [
			'document' => $doc,
			'caseStudies' => CaseStudy::where('is_active', true)->orderBy('sort')->orderBy('id')->get()->map(fn (CaseStudy $c) => [
				'id' => $c->id, 'name' => $c->client_name, 'url' => $c->url, 'thumb' => Media::thumb($c->attachment_id),
				'metrics' => collect((array) $c->metrics)->pluck('value')->filter()->implode(' · '),
			])->all(),
			'team' => Contacts::team(),
			'maxReferences' => Pages::MAX_REFERENCES,
			'content' => OfferContent::normalize($version->content),
			'ai' => AiService::status($this->settings),
			'aiUrl' => route('gst.ai.content', $version->offer_id),
		];
	}
}
