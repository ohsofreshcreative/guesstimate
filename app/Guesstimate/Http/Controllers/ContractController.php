<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Contracts\Contract;
use App\Guesstimate\Contracts\ContractEvent;
use App\Guesstimate\Contracts\ContractExport;
use App\Guesstimate\Contracts\ContractHtml;
use App\Guesstimate\Contracts\ContractPolicy;
use App\Guesstimate\Contracts\ContractQuery;
use App\Guesstimate\Contracts\ContractService;
use App\Guesstimate\Contracts\ContractSnapshot;
use App\Guesstimate\Contracts\ContractStatus;
use App\Guesstimate\Contracts\ContractTemplate;
use App\Guesstimate\Contracts\ContractValues;
use App\Guesstimate\Contracts\ContractVersion;
use App\Guesstimate\Contracts\PaymentSchedule;
use App\Guesstimate\Contracts\TemplateDocument;
use App\Guesstimate\Document\Media;
use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferPolicy;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Support\Format;
use App\Guesstimate\Support\Input;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Generowanie umowy z oferty i dane wersji roboczej. Nic tu nie podpisuje ani nie wysyła umowy.
 */
class ContractController extends Controller
{
	public function __construct(private ContractService $contracts, private ContractPolicy $policy)
	{
	}

	public function index(Request $request, ContractQuery $query)
	{
		$result = $query->search($request->query());
		$authors = $query->authors();

		return view('guesstimate.contracts.index', [
			'result' => $result,
			'rows' => $result['rows'],
			'filters' => $result['filters'],
			'statuses' => array_column(array_map(fn ($status) => ['value' => $status->value, 'label' => $status->label()], ContractStatus::cases()), 'label', 'value'),
			'authors' => $authors,
			'authorFilter' => app(OfferPolicy::class)->viewsAll() ? $authors : [],
			'canManageTemplates' => $this->policy->canManageTemplates(),
		]);
	}

	/** Przed wygenerowaniem: wybór szablonu i lista pól, których brakuje. */
	public function create(Request $request, Offer $offer)
	{
		abort_unless($this->policy->canCreate($offer), 403);

		if (! $offer->client_id || ! $offer->currentVersion) {
			return $this->redirectWith(route('gst.offers.show', $offer), 'Umowa wymaga oferty z klientem.', 'error');
		}

		return view('guesstimate.contracts.create', $this->createData($offer, (int) $request->query('szablon')));
	}

	public function store(Request $request, Offer $offer)
	{
		abort_unless($this->policy->canCreate($offer), 403);

		$source = $this->templates()->firstWhere('id', (int) $request->input('template_id'))?->latestApproved;
		$title = Input::line($request->input('title'));
		$errors = [];

		if (! $source) {
			$errors['template_id'] = 'Wybierz zatwierdzony szablon.';
		}

		if ($title === null || mb_strlen($title) > 190) {
			$errors['title'] = 'Podaj nazwę umowy (do 190 znaków).';
		}

		if (! $errors) {
			try {
				$contract = $this->contracts->create($offer, $source, $title, (array) $request->input('values'));

				return $this->redirectWith(route('gst.contracts.show', $contract), 'Wygenerowano szkic umowy. Dane są teraz zamrożone w tej wersji.');
			} catch (InvalidArgumentException $e) {
				$errors['values'] = $e->getMessage();
			}
		}

		return $this->invalid('guesstimate.contracts.create', $this->createData($offer, (int) $request->input('template_id')), $errors);
	}

	public function show(Request $request, Contract $contract)
	{
		abort_unless($this->policy->canView($contract), 403);

		$versions = $contract->versions()->get();
		$version = $versions->firstWhere('version_no', (int) $request->query('wersja')) ?? $versions->firstWhere('id', $contract->current_version_id);
		abort_unless($version, 404);

		$snapshot = (array) $version->data_snapshot;
		$body = (string) $version->body;
		$editable = $version->id === $contract->current_version_id && $version->status === ContractStatus::Draft && $this->policy->canEdit($contract);
		$current = $version->id === $contract->current_version_id && $version->status === ContractStatus::Draft;
		$offer = Offer::find($contract->offer_id);
		$changes = [];

		if ($editable && $offer?->currentVersion && $offer->client) {
			$changes = ContractValues::diff($body, $snapshot, ContractSnapshot::of($offer, $offer->currentVersion));
		}

		return view('guesstimate.contracts.show', [
			'contract' => $contract,
			'offer' => $offer,
			'version' => $version,
			'versions' => $versions,
			'editable' => $editable,
			'regions' => array_map(fn ($part) => ContractValues::render(ContractHtml::clean($part), $snapshot), TemplateDocument::parse($body)),
			'background' => Media::image($version->backgroundId(), 'large')['url'] ?? null,
			'fields' => ContractValues::fields($body, $snapshot),
			'missing' => ContractValues::missing($body, $snapshot),
			'changes' => $changes,
			'payments' => PaymentSchedule::isEmpty((array) ($snapshot['payments'] ?? [])) ? null : PaymentSchedule::forSnapshot($snapshot),
			'expected' => $version->snapshotHash(),
			'draft' => $current,
			'canApprove' => $current && $this->policy->canApprove(),
			'blockers' => $current ? array_merge(ContractValues::missing($body, $snapshot) ? ['Uzupełnij puste pola umowy.'] : [], PaymentSchedule::blockers($snapshot)) : [],
		]);
	}

	/** Zatwierdzenie zamraża wersję. Nie wysyła i nie podpisuje umowy. */
	public function approve(Request $request, Contract $contract)
	{
		abort_unless($this->policy->canView($contract) && $this->policy->canApprove(), 403);
		$target = route('gst.contracts.show', $contract);

		try {
			$this->contracts->approve($contract, (string) $request->input('expected'));
		} catch (InvalidArgumentException $e) {
			return $this->redirectWith($target, $e->getMessage(), 'error');
		}

		return $this->redirectWith($target, 'Zatwierdzono umowę. Ta wersja jest już niezmienna i można ją pobrać.');
	}

	public function payments(Request $request, Contract $contract)
	{
		abort_unless($this->policy->canView($contract), 403);

		return view('guesstimate.contracts.payments', $this->paymentsData($contract));
	}

	public function savePayments(Request $request, Contract $contract)
	{
		abort_unless($this->policy->canEdit($contract), 403);

		$parsed = PaymentSchedule::parse($request->all());

		if ($parsed['errors']) {
			return $this->invalid('guesstimate.contracts.payments', $this->paymentsData($contract), $parsed['errors']);
		}

		$target = route('gst.contracts.payments', $contract);

		try {
			$this->contracts->savePayments($contract, $parsed['payments'], (string) $request->input('expected'));
		} catch (InvalidArgumentException $e) {
			return $this->redirectWith($target, $e->getMessage(), 'error');
		}

		return $this->redirectWith($target, 'Zapisano harmonogram płatności.');
	}

	/** Pobranie zatwierdzonej wersji. Dostęp jak do podglądu; każde pobranie zostaje w dzienniku. */
	public function export(Request $request, Contract $contract, string $format)
	{
		abort_unless($this->policy->canView($contract), 403);

		$version = $contract->versions()->where('version_no', (int) $request->query('wersja'))->first() ?? ContractVersion::find($contract->current_version_id);
		abort_unless($version, 404);

		try {
			$bytes = ContractExport::file($version, $format, $contract->title);
		} catch (InvalidArgumentException $e) {
			return $this->redirectWith(route('gst.contracts.show', $contract), $e->getMessage(), 'error');
		}

		ContractEvent::record('exported', $contract, versionNo: $version->version_no, payload: ['format' => $format, 'hash' => $version->content_hash]);

		return response($bytes, 200, [
			'Content-Type' => ContractExport::FORMATS[$format],
			'Content-Disposition' => 'attachment; filename="' . ContractExport::filename($version, $format) . '"',
			'Content-Length' => (string) strlen($bytes),
			'X-Content-Type-Options' => 'nosniff',
		]);
	}

	public function saveValues(Request $request, Contract $contract)
	{
		return $this->act($contract, fn () => $this->contracts->saveValues($contract, (array) $request->input('values'), (string) $request->input('expected')), 'Zapisano wartości.');
	}

	public function refresh(Request $request, Contract $contract)
	{
		return $this->act($contract, fn () => $this->contracts->refresh($contract, (string) $request->input('expected')), 'Odświeżono dane z oferty. Wartości wpisane ręcznie zostały.');
	}

	private function act(Contract $contract, callable $action, string $message)
	{
		abort_unless($this->policy->canEdit($contract), 403);
		$target = route('gst.contracts.show', $contract);

		try {
			$action();
		} catch (InvalidArgumentException $e) {
			return $this->redirectWith($target, $e->getMessage(), 'error');
		}

		return $this->redirectWith($target, $message);
	}

	private function paymentsData(Contract $contract): array
	{
		$version = ContractVersion::findOrFail($contract->current_version_id);
		$snapshot = (array) $version->data_snapshot;
		$payments = (array) ($snapshot['payments'] ?? []);
		$deposit = (array) ($payments['deposit'] ?? []);

		return [
			'contract' => $contract,
			'version' => $version,
			'editable' => $version->status === ContractStatus::Draft && $this->policy->canEdit($contract),
			'expected' => $version->snapshotHash(),
			'summary' => PaymentSchedule::forSnapshot($snapshot),
			'estimate' => ContractValues::automatic($snapshot)['offer.amount_variant'],
			'form' => [
				'agreed_net' => Format::moneyInput($payments['agreed_net'] ?? null),
				'vat_rate' => $payments['vat_rate'] ?? app(SettingsRepository::class)->get('vat_rate'),
				'deposit_net' => Format::moneyInput($deposit['net'] ?? null),
				'deposit_mode' => $deposit['mode'] ?? '',
				'deposit_stage' => $deposit['stage'] ?? '',
				'deposit_due' => $deposit['due'] ?? '',
				'stages' => array_map(fn ($stage) => array_merge($stage, ['net' => Format::moneyInput($stage['net']), 'percent' => $stage['percent'] ?? '', 'milestone' => $stage['milestone'] ?? '', 'due' => $stage['due'] ?? '']), (array) ($payments['stages'] ?? [])),
			],
		];
	}

	private function templates()
	{
		return ContractTemplate::whereNull('archived_at')->with('latestApproved')->orderBy('name')->get()->filter(fn ($template) => $template->latestApproved)->values();
	}

	private function createData(Offer $offer, int $templateId): array
	{
		$templates = $this->templates();
		$selected = $templates->firstWhere('id', $templateId) ?? ($templates->count() === 1 ? $templates->first() : null);
		$snapshot = ContractSnapshot::of($offer, $offer->currentVersion);
		$body = (string) $selected?->latestApproved->body;

		return [
			'offer' => $offer,
			'templates' => $templates,
			'selected' => $selected,
			'fields' => ContractValues::fields($body, $snapshot),
			'missing' => ContractValues::missing($body, $snapshot),
			'defaultTitle' => sprintf('Umowa – %s', $offer->currentVersion->project_name),
		];
	}
}
