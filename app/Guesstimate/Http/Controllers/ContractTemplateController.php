<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Contracts\ContractHtml;
use App\Guesstimate\Contracts\ContractStatus;
use App\Guesstimate\Contracts\ContractTemplate;
use App\Guesstimate\Contracts\ContractTemplateService;
use App\Guesstimate\Contracts\ContractTemplateVersion;
use App\Guesstimate\Contracts\DocxImporter;
use App\Guesstimate\Contracts\DraftConflict;
use App\Guesstimate\Contracts\TemplateDocument;
use App\Guesstimate\Contracts\TemplateForm;
use App\Guesstimate\Contracts\TemplateVariables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Szablony umów (manager, administrator; uprawnienie na trasie, a usługa sprawdza je ponownie).
 * Treść przychodzi z przeglądarki, więc jest czyszczona tutaj przez TemplateForm, a przy wyświetlaniu ponownie.
 */
class ContractTemplateController extends Controller
{
	public function __construct(private ContractTemplateService $templates, private DocxImporter $importer)
	{
	}

	public function index()
	{
		return view('guesstimate.contract-templates.index', $this->indexData());
	}

	public function store(Request $request)
	{
		$meta = TemplateForm::meta($request->all());

		if ($meta['errors']) {
			return $this->invalid('guesstimate.contract-templates.index', array_merge($this->indexData(), ['openModal' => 'template-create']), $meta['errors']);
		}

		$template = $this->templates->create($meta['name'], TemplateDocument::compose('', '<p><br></p>', ''), $meta['description']);

		return $this->redirectWith(route('gst.contract-templates.edit', $template), 'Utworzono szablon.');
	}

	public function import(Request $request)
	{
		$meta = TemplateForm::meta($request->all());
		$file = $request->file('docx');
		$errors = $meta['errors'];

		if (! $file || ! $file->isValid()) {
			$errors['docx'] = 'Wybierz plik DOCX.';
		} elseif (strtolower($file->getClientOriginalExtension()) !== 'docx') {
			$errors['docx'] = 'Obsługiwane są tylko pliki DOCX.';
		}

		$read = null;

		if (! $errors) {
			try {
				$read = $this->importer->read($file->getRealPath());
			} catch (InvalidArgumentException $e) {
				$errors['docx'] = $e->getMessage();
			}
		}

		if ($errors) {
			return $this->invalid('guesstimate.contract-templates.index', array_merge($this->indexData(), ['openModal' => 'template-import']), $errors);
		}

		$body = TemplateDocument::compose('', $read['html'], '');
		$template = $this->templates->import($meta['name'], $body, $read['warnings'], hash_file('sha256', $file->getRealPath()));

		return $this->redirectWith(route('gst.contract-templates.edit', $template), 'Zaimportowano szkic. Porównaj go z oryginałem przed zatwierdzeniem.');
	}

	public function edit(Request $request, ContractTemplate $template)
	{
		$versions = $template->versions()->get();
		$version = $versions->firstWhere('version_no', (int) $request->query('wersja')) ?? $versions->firstWhere('id', $template->current_version_id);
		abort_unless($version, 404);

		$isCurrent = $version->id === $template->current_version_id;
		$editable = $isCurrent && $version->status === ContractStatus::Draft && ! $template->isArchived();
		$regions = array_map([ContractHtml::class, 'clean'], TemplateDocument::parse((string) $version->body));

		return view('guesstimate.contract-templates.edit', [
			'template' => $template,
			'version' => $version,
			'versions' => $versions,
			'isCurrent' => $isCurrent,
			'editable' => $editable,
			'regions' => $regions,
			'warnings' => $this->templates->importWarnings($template, $version->version_no),
			'variables' => TemplateVariables::GROUPS,
			'config' => [
				'editable' => $editable,
				'hash' => hash('sha256', (string) $version->body),
				'name' => $template->name,
				'description' => (string) $template->description,
				'labels' => TemplateVariables::all(),
				'urls' => [
					'save' => route('gst.contract-templates.save', $template),
					'approve' => route('gst.contract-templates.approve', $template),
				],
			],
		]);
	}

	public function save(Request $request, ContractTemplate $template): JsonResponse
	{
		$input = $request->all();
		$meta = TemplateForm::meta($input);
		$document = TemplateForm::document($input);

		if ($errors = array_merge($meta['errors'], $document['errors'])) {
			return response()->json(['error' => reset($errors), 'errors' => $errors], 422);
		}

		try {
			$version = $this->templates->saveDraft($template, $document['body'], $this->expected($request));
			$this->templates->rename($template, $meta['name'], $meta['description']);
		} catch (DraftConflict $e) {
			return response()->json(['error' => $e->getMessage()], 409);
		} catch (InvalidArgumentException $e) {
			return response()->json(['error' => $e->getMessage()], 422);
		}

		return response()->json(['hash' => hash('sha256', (string) $version->body), 'regions' => TemplateDocument::parse((string) $version->body)]);
	}

	public function approve(Request $request, ContractTemplate $template): JsonResponse
	{
		try {
			$this->templates->approve($template, $this->expected($request), (bool) $request->input('reviewed'));
		} catch (DraftConflict $e) {
			return response()->json(['error' => $e->getMessage()], 409);
		} catch (InvalidArgumentException $e) {
			return response()->json(['error' => $e->getMessage()], 422);
		}

		return response()->json(['redirect' => route('gst.contract-templates.edit', $template)]);
	}

	public function newVersion(ContractTemplate $template)
	{
		try {
			$this->templates->newVersion($template);
		} catch (InvalidArgumentException $e) {
			return $this->redirectWith(route('gst.contract-templates.edit', $template), $e->getMessage(), 'error');
		}

		return $this->redirectWith(route('gst.contract-templates.edit', $template), 'Utworzono nową wersję roboczą.');
	}

	public function duplicate(Request $request, ContractTemplate $template)
	{
		$source = ContractTemplateVersion::where('template_id', $template->id)->where('version_no', (int) $request->input('version_no'))->first() ?? $template->currentVersion;
		$copy = $this->templates->duplicate($source, mb_substr('Kopia: ' . $template->name, 0, 190));

		return $this->redirectWith(route('gst.contract-templates.edit', $copy), 'Utworzono kopię szablonu.');
	}

	public function archive(ContractTemplate $template)
	{
		$this->templates->archive($template);

		return $this->redirectWith(route('gst.contract-templates.index'), 'Szablon przeniesiono do archiwum.');
	}

	private function expected(Request $request): ?string
	{
		$hash = $request->input('hash');

		return is_string($hash) ? $hash : null;
	}

	private function indexData(): array
	{
		return [
			'templates' => ContractTemplate::with(['currentVersion', 'latestApproved'])->orderByRaw('archived_at is not null')->orderBy('name')->get(),
		];
	}
}
