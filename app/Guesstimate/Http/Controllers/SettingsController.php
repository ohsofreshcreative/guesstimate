<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Ai\AiService;
use App\Guesstimate\Document\Background;
use App\Guesstimate\Document\CaseStudy;
use App\Guesstimate\Document\DocumentForm;
use App\Guesstimate\Document\DocumentSettings;
use App\Guesstimate\Document\Media;
use App\Guesstimate\Document\Pages;
use App\Guesstimate\Integrations\ClickUp\OAuthApp;
use App\Guesstimate\Library\FeatureService;
use App\Guesstimate\Offers\BudgetSettings;
use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Library\PageType;
use App\Guesstimate\Pricing\CalcModel;
use App\Guesstimate\Pricing\Decimal;
use App\Guesstimate\Settings\Defaults;
use App\Guesstimate\Settings\RatesValidator;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Support\Format;
use App\Guesstimate\Support\Input;
use App\Guesstimate\Support\Secrets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Panel ustawień (tylko administrator). Zmiany dotyczą wyłącznie nowych ofert —
 * zapisane oferty mają własną kopię stawek i parametrów.
 */
class SettingsController extends Controller
{
	/** Sekret poprzedniej (wspólnej) integracji ClickUp — tylko do usunięcia, nie jest używany. */
	private const LEGACY_CLICKUP_TOKEN = 'clickup_token';

	public const SECTIONS = [
		'stawki' => 'Stawki godzinowe',
		'ux' => 'Zakres UX',
		'kalkulacja' => 'Kalkulacja i rodzaje projektu',
		'ogolne' => 'Waluta, zaokrąglenia i numeracja',
		'dokument' => 'Dokument oferty',
		'tla' => 'Biblioteka teł',
		'realizacje' => 'Realizacje',
		'integracje' => 'Integracje',
	];

	/**
	 * Sekcje starego modelu kalkulacji — dostępne pod adresem, ale nieużywane przez nowe oferty.
	 */
	public const LEGACY_SECTIONS = [
		'etapy' => 'Etapy (poprzedni model)',
		'typy-podstron' => 'Typy podstron (poprzedni model)',
	];

	public const HOURS_STEPS = ['0' => 'Bez zaokrąglania', '0.25' => 'Do 15 minut (0,25 h)', '0.5' => 'Do pół godziny', '1' => 'Do pełnej godziny'];

	public const PRICE_STEPS = [1 => 'Bez zaokrąglania (co do grosza)', 100 => 'Do 1 zł', 1000 => 'Do 10 zł', 10000 => 'Do 100 zł'];

	public const CURRENCIES = ['PLN' => 'PLN (zł)', 'EUR' => 'EUR', 'USD' => 'USD'];

	public function __construct(private SettingsRepository $settings, private Secrets $secrets)
	{
	}

	public static function allSections(): array
	{
		return self::SECTIONS + self::LEGACY_SECTIONS;
	}

	public function index(Request $request, ?string $section = null)
	{
		$section = array_key_exists((string) $section, self::allSections()) ? $section : 'stawki';

		return view('guesstimate.settings.index', $this->data($section));
	}

	public function update(Request $request, string $section)
	{
		abort_unless(array_key_exists($section, self::allSections()) && ! in_array($section, ['typy-podstron', 'tla', 'realizacje'], true), 404);

		$errors = match ($section) {
			'stawki' => $this->saveRates($request),
			'ux' => $this->saveUx($request),
			'kalkulacja' => $this->saveCalculation($request),
			'etapy' => $this->saveStages($request),
			'ogolne' => $this->saveGeneral($request),
			'integracje' => $this->saveIntegrations($request),
			'dokument' => $this->saveDocument($request),
		};

		if ($errors) {
			return $this->invalid('guesstimate.settings.index', $this->data($section), $errors);
		}

		return $this->redirectWith(route('gst.settings.index', ['section' => $section]), 'Zapisano ustawienia. Obowiązują dla nowych ofert — zapisane oferty się nie zmieniają.');
	}

	public function storePageType(Request $request)
	{
		[$data, $errors] = $this->validatePageType($request->all());

		if ($errors) {
			return $this->invalid('guesstimate.settings.index', array_merge($this->data('typy-podstron'), ['openModal' => 'page-type-create']), $errors);
		}

		PageType::create(array_merge($data, ['sort' => (int) PageType::max('sort') + 1]));

		return $this->redirectWith(route('gst.settings.index', ['section' => 'typy-podstron']), sprintf('Dodano typ podstrony „%s”.', $data['name']));
	}

	public function updatePageType(Request $request, PageType $pageType)
	{
		[$data, $errors] = $this->validatePageType($request->all());

		if ($errors) {
			return $this->invalid('guesstimate.settings.index', array_merge($this->data('typy-podstron'), ['openModal' => 'page-type-' . $pageType->id]), $errors);
		}

		$pageType->update($data);

		return $this->redirectWith(route('gst.settings.index', ['section' => 'typy-podstron']), sprintf('Zapisano typ podstrony „%s”.', $data['name']));
	}

	private function saveRates(Request $request): array
	{
		['rates' => $rates, 'errors' => $errors] = RatesValidator::validate((array) $request->input('rates', []));

		if ($errors) {
			return collect($errors)->mapWithKeys(fn ($message, $key) => ['rates.' . $key => $message])->all();
		}

		$this->settings->set('hourly_rates', $rates);

		return [];
	}

	private function saveUx(Request $request): array
	{
		$scopes = $this->settings->uxScopes();
		$errors = [];

		foreach (array_keys($scopes) as $key) {
			[$min, $max, $hoursErrors] = FeatureService::hours(
				$request->input("ux.{$key}.min"),
				$request->input("ux.{$key}.max"),
				"ux.{$key}.min",
				"ux.{$key}.max",
			);

			$errors = array_merge($errors, $hoursErrors);
			$scopes[$key]['min'] = $min;
			$scopes[$key]['max'] = $max;
		}

		if (! $errors) {
			$this->settings->set('ux_scopes', $scopes);
		}

		return $errors;
	}

	private function saveStages(Request $request): array
	{
		$stages = $this->settings->get('stages');
		$errors = [];
		$percentSum = Decimal::of(0);

		foreach ($stages as $i => $stage) {
			$key = $stage['key'];
			$name = Input::line($request->input("stages.{$key}.name"));

			if ($name === null || mb_strlen($name) > 100) {
				$errors["stages.{$key}.name"] = 'Podaj nazwę etapu (do 100 znaków).';
			}

			$stages[$i]['name'] = $name ?? $stage['name'];
			$stages[$i]['enabled'] = $key === 'ux' ? true : $request->boolean("stages.{$key}.enabled");

			if ($stage['in_pool']) {
				$percent = Input::decimal($request->input("stages.{$key}.percent"), 2);

				if ($percent === null || Decimal::of($percent)->isGreaterThan(100)) {
					$errors["stages.{$key}.percent"] = 'Podaj udział od 0 do 100%.';
				} else {
					$stages[$i]['percent'] = $percent;

					if ($stages[$i]['enabled']) {
						$percentSum = $percentSum->plus($percent);
					}
				}
			}

			if ($key === 'discovery') {
				$raw = $request->input('stages.discovery.fixed_amount');

				if ($raw === null || trim((string) $raw) === '') {
					$stages[$i]['fixed_amount'] = null;
				} else {
					$amount = Input::money($raw);

					if ($amount === null) {
						$errors['stages.discovery.fixed_amount'] = 'Podaj kwotę, np. 2000 albo 2000,50, albo zostaw puste, aby liczyć Discovery w godzinach.';
					} else {
						$stages[$i]['fixed_amount'] = $amount;
					}
				}
			}
		}

		$percentErrors = array_filter(array_keys($errors), fn ($key) => str_ends_with($key, '.percent'));

		if (! $percentErrors && ! $percentSum->isEqualTo(100)) {
			$errors['stages.percent_sum'] = sprintf('Udziały włączonych etapów UX, UI i Development muszą sumować się do 100%% (teraz: %s).', Format::percent((string) $percentSum));
		}

		$model = (string) $request->input('default_calc_model');
		$contentStage = (string) $request->input('content_stage');

		if (CalcModel::tryFrom($model) === null) {
			$errors['default_calc_model'] = 'Wybierz domyślny model kalkulacji.';
		}

		if (! in_array($contentStage, Defaults::STAGE_KEYS, true)) {
			$errors['content_stage'] = 'Wybierz etap dla wprowadzania treści.';
		}

		if (! $errors) {
			$this->settings->set('stages', $stages);
			$this->settings->set('default_calc_model', $model);
			$this->settings->set('content_stage', $contentStage);
		}

		return $errors;
	}

	private function saveGeneral(Request $request): array
	{
		$errors = [];
		$currency = (string) $request->input('currency');
		$hoursStep = (string) $request->input('hours_step');
		$priceStep = (int) $request->input('price_step');
		$vat = Input::decimal($request->input('vat_rate'), 2);
		$prefix = strtoupper(trim((string) $request->input('prefix')));
		$padding = (int) $request->input('padding');

		if (! array_key_exists($currency, self::CURRENCIES)) {
			$errors['currency'] = 'Wybierz walutę.';
		}

		if (! array_key_exists($hoursStep, self::HOURS_STEPS)) {
			$errors['hours_step'] = 'Wybierz sposób zaokrąglania godzin.';
		}

		if (! array_key_exists($priceStep, self::PRICE_STEPS)) {
			$errors['price_step'] = 'Wybierz sposób zaokrąglania ceny.';
		}

		if ($vat === null || Decimal::of($vat)->isGreaterThan(100)) {
			$errors['vat_rate'] = 'Podaj stawkę VAT od 0 do 100%.';
		}

		if (! preg_match('/^[A-Z0-9\-]{1,10}$/', $prefix)) {
			$errors['prefix'] = 'Prefiks może mieć do 10 znaków: wielkie litery, cyfry i myślnik.';
		}

		if ($padding < 3 || $padding > 6) {
			$errors['padding'] = 'Liczba cyfr numeru musi wynosić od 3 do 6.';
		}

		if (! $errors) {
			$this->settings->set('currency', $currency);
			$this->settings->set('vat_rate', $vat);
			$partStep = (int) $request->input('part_step', 100);
			$this->settings->set('rounding', ['hours_step' => $hoursStep, 'price_step' => $priceStep, 'part_step' => array_key_exists($partStep, self::PRICE_STEPS) ? $partStep : 100]);
			$this->settings->set('numbering', ['prefix' => $prefix, 'padding' => $padding]);
		}

		return $errors;
	}

	/**
	 * @return array{0: array|null, 1: array<string, string>}
	 */
	private function validatePageType(array $input): array
	{
		$errors = [];
		$name = Input::line($input['name'] ?? null);

		if ($name === null || mb_strlen($name) > 150) {
			$errors['name'] = 'Podaj nazwę typu podstrony (do 150 znaków).';
		}

		$kind = (string) ($input['kind'] ?? '');

		if (! array_key_exists($kind, PageType::KINDS)) {
			$errors['kind'] = 'Wybierz rodzaj typu podstrony.';
		}

		$data = ['name' => $name, 'kind' => $kind, 'is_active' => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN)];

		foreach (['ui', 'dev', 'content'] as $group) {
			[$min, $max, $hoursErrors] = FeatureService::hours($input[$group . '_min'] ?? null, $input[$group . '_max'] ?? null, $group . '_min', $group . '_max');
			$errors = array_merge($errors, $hoursErrors);
			$data[$group . '_min'] = $min;
			$data[$group . '_max'] = $max;
		}

		return [$errors ? null : $data, $errors];
	}

	/**
	 * Model budżetowy: warsztaty, podział UX/UI/Dev, godziny na podstronę, jednostki rodzajów projektu.
	 */
	private function saveCalculation(Request $request): array
	{
		$errors = [];
		$workshops = Input::money($request->input('workshops_amount'));

		if ($workshops === null) {
			$errors['workshops_amount'] = 'Podaj kwotę warsztatów (0, jeśli bez warsztatów).';
		}

		$split = [];
		$sum = Decimal::of(0);

		foreach (['ux', 'ui', 'dev'] as $key) {
			$value = Input::decimal($request->input("split.{$key}"), 2);

			if ($value === null || Decimal::of($value)->isGreaterThan(100)) {
				$errors["split.{$key}"] = 'Podaj udział od 0 do 100%.';

				continue;
			}

			$split[$key] = rtrim(rtrim($value, '0'), '.');
			$sum = $sum->plus($value);
		}

		if (count($split) === 3 && ! $sum->isEqualTo(100)) {
			$errors['split'] = sprintf('Udziały UX, UI i Development muszą sumować się do 100%% (teraz: %s).', Format::percent((string) $sum));
		} elseif (count($split) === 3 && Decimal::of($split['dev'])->isZero()) {
			$errors['split.dev'] = 'Udział Development musi być większy od zera.';
		}

		$hours = [];

		foreach (['ui', 'dev'] as $part) {
			[$min, $max, $hoursErrors] = FeatureService::hours($request->input("page_hours.{$part}_min"), $request->input("page_hours.{$part}_max"), "page_hours.{$part}_min", "page_hours.{$part}_max");
			$errors = array_merge($errors, $hoursErrors);
			$hours[$part . '_min'] = $min;
			$hours[$part . '_max'] = $max;
		}

		$types = BudgetSettings::types($this->settings->all());

		foreach ($types as $key => $type) {
			$label = Input::line($request->input("project_types.{$key}.label"));
			$units = $key === OfferVersion::CUSTOM_UNITS_TYPE ? '0' : Input::hours($request->input("project_types.{$key}.units"));

			if ($label === null || mb_strlen($label) > 60) {
				$errors["project_types.{$key}.label"] = 'Podaj nazwę rodzaju projektu (do 60 znaków).';
			}

			if ($units === null) {
				$errors["project_types.{$key}.units"] = 'Podaj liczbę jednostek (0 lub więcej).';
			}

			$types[$key] = ['label' => $label ?? $type['label'], 'units' => $units !== null ? rtrim(rtrim($units, '0'), '.') : $type['units']];
		}

		if (! $errors) {
			$this->settings->set('workshops_amount', $workshops);
			$this->settings->set('budget_split', $split);
			$this->settings->set('page_hours', $hours);
			// Scalamy z zapisanymi wartościami, żeby nie zgubić ustawień wycofanych rodzajów (np. „Aplikacja webowa”).
			$this->settings->set('project_types', array_replace((array) $this->settings->get('project_types'), $types));
		}

		return $errors;
	}

	/**
	 * OpenAI, ClickUp i oferta online. Sekrety zapisujemy tylko, gdy wpisano nową wartość; pole „usuń” je kasuje.
	 */
	private function saveIntegrations(Request $request): array
	{
		$errors = [];

		$aiKey = trim((string) $request->input('openai_api_key'));

		if ($aiKey !== '' && ! preg_match('/^sk-[A-Za-z0-9_\-]{20,200}$/', $aiKey)) {
			$errors['openai_api_key'] = 'Klucz OpenAI zaczyna się od „sk-”. Sprawdź, czy skopiowano go w całości.';
		}

		$model = trim((string) $request->input('ai.model'));

		if (! preg_match('/^[A-Za-z0-9._:\-]{2,80}$/', $model)) {
			$errors['ai.model'] = 'Podaj nazwę modelu, np. gpt-4.1-mini.';
		}

		$instructions = Input::text($request->input('ai.instructions')) ?? '';

		if (mb_strlen($instructions) > 6000) {
			$errors['ai.instructions'] = 'Instrukcje mogą mieć do 6000 znaków.';
		}

		$limit = (int) $request->input('ai.daily_limit');
		$timeout = (int) $request->input('ai.timeout');

		if ($limit < 1 || $limit > 500) {
			$errors['ai.daily_limit'] = 'Limit dzienny: od 1 do 500 zapytań na osobę.';
		}

		if ($timeout < 10 || $timeout > 120) {
			$errors['ai.timeout'] = 'Limit czasu: od 10 do 120 sekund.';
		}

		if ($errors) {
			return $errors;
		}

		if ($aiKey !== '') {
			$this->secrets->set(AiService::KEY, $aiKey);
		} elseif ($request->boolean('openai_api_key_remove')) {
			$this->secrets->set(AiService::KEY, null);
		}

		// Stary wspólny Personal API Token ClickUp nie jest już używany (OAuth per użytkownik) — usuwa go administrator.
		if ($request->boolean('clickup_legacy_token_remove')) {
			$this->secrets->set(self::LEGACY_CLICKUP_TOKEN, null);
		}

		$this->settings->set('ai', ['model' => $model, 'instructions' => $instructions, 'daily_limit' => $limit, 'timeout' => $timeout]);

		return [];
	}

	/**
	 * Domyślne treści i tła dokumentu oferty. Dotyczą nowych ofert — istniejące mają własną kopię.
	 */
	private function saveDocument(Request $request): array
	{
		$current = (array) $this->settings->get('document');
		$result = DocumentForm::content((array) $request->input('document', []), DocumentSettings::initial($this->settings->all()));
		$errors = collect($result['errors'])->except(['reference_ids', 'contact_user_id'])->mapWithKeys(fn ($m, $k) => ['document.' . $k => $m])->all();

		$website = mb_substr((string) Input::line($request->input('website')), 0, 120);
		$validity = (int) $request->input('validity_days');

		if ($validity < 0 || $validity > 365) {
			$errors['validity_days'] = 'Ważność oferty: od 0 (nie pokazuj) do 365 dni.';
		}

		$contact = [];

		foreach (['name' => 120, 'role' => 120, 'phone' => 40, 'email' => 190] as $field => $max) {
			$contact[$field] = mb_substr((string) Input::line($request->input("contact.{$field}")), 0, $max);
		}

		if ($contact['email'] !== '' && ! is_email($contact['email'])) {
			$errors['contact.email'] = 'Podaj poprawny adres e-mail.';
		}

		$backgrounds = [];

		foreach (array_keys(Pages::TYPES) as $key) {
			$id = (int) $request->input("backgrounds.{$key}");

			if ($id && Background::whereKey($id)->exists()) {
				$backgrounds[$key] = $id;
			}
		}

		$logo = (int) $request->input('logo_attachment_id');

		if (! empty($_FILES['logo']['name'])) {
			[$uploaded, $error] = Media::upload('logo');
			if ($error) {
				$errors['logo'] = $error;
			} else {
				$logo = $uploaded;
			}
		} elseif ($request->boolean('logo_remove')) {
			$logo = 0;
		} elseif ($logo && ! Media::isImage($logo)) {
			$logo = (int) ($current['logo_attachment_id'] ?? 0);
		}

		if ($errors) {
			return $errors;
		}

		$data = $result['data'];
		$this->settings->set('document', array_replace($current, [
			'cover_heading' => $data['cover_heading'],
			'cover_lead' => $data['cover_lead'],
			'price_includes' => $data['price_includes'],
			'extras_intro' => $data['extras_intro'],
			'extras' => $data['extras'],
			'references_intro' => $data['references_intro'],
			'next_steps' => $data['next_steps'],
			'next_steps_note' => $data['next_steps_note'],
			'website' => $website,
			'validity_days' => $validity,
			'contact' => $contact,
			'backgrounds' => $backgrounds,
			'logo_attachment_id' => $logo ?: null,
		]));

		return [];
	}

	private function data(string $section): array
	{
		$settings = $this->settings->all();
		unset($settings['secrets']);

		return [
			'section' => $section,
			'sections' => self::SECTIONS,
			'legacySections' => self::LEGACY_SECTIONS,
			'settings' => $settings,
			'projectTypes' => BudgetSettings::types($settings),
			'secrets' => [
				'openai' => $this->secrets->describe(AiService::KEY),
				'clickup_legacy' => $this->secrets->describe(self::LEGACY_CLICKUP_TOKEN),
			],
			'clickupApp' => OAuthApp::configuration(),
			'docPages' => Pages::TYPES,
			'backgrounds' => in_array($section, ['tla', 'dokument'], true) ? Background::orderBy('sort')->orderBy('name')->get()->map(fn (Background $b) => [
				'id' => $b->id, 'name' => $b->name, 'fit' => $b->fit, 'thumb' => Media::thumb($b->attachment_id),
				'size' => $b->width && $b->height ? $b->width . '×' . $b->height : '—', 'warning' => $b->ratioWarning(),
				'usage' => $section === 'tla' ? DocumentLibraryController::usage($b) : null,
				'defaults' => array_keys(array_filter((array) ($settings['document']['backgrounds'] ?? []), fn ($id) => (int) $id === $b->id)),
			])->all() : [],
			'recentMedia' => $section === 'tla' ? Media::recent(36, Background::pluck('attachment_id')->all()) : [],
			'cases' => $section === 'realizacje' ? CaseStudy::orderBy('sort')->orderBy('id')->get()->map(fn (CaseStudy $c) => [
				'id' => $c->id, 'client_name' => $c->client_name, 'url' => $c->url, 'description' => $c->description,
				'metrics' => array_pad((array) $c->metrics, CaseStudy::MAX_METRICS, ['value' => '', 'label' => '']),
				'is_active' => $c->is_active, 'thumb' => Media::thumb($c->attachment_id),
			])->all() : [],
			'logoThumb' => Media::thumb((int) ($settings['document']['logo_attachment_id'] ?? 0)),
			'fits' => Background::FITS,
			'aiRequests' => $section === 'integracje' ? DB::table('gst_ai_requests')->where('created_at', '>=', now()->subDays(30))->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all() : [],
			'pageTypes' => $section === 'typy-podstron' ? PageType::orderBy('sort')->orderBy('name')->get() : collect(),
			'hoursSteps' => self::HOURS_STEPS,
			'priceSteps' => self::PRICE_STEPS,
			'currencies' => self::CURRENCIES,
			'calcModels' => collect(CalcModel::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all(),
		];
	}
}
