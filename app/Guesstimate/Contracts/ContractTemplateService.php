<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Auth\AccessDenied;
use App\Guesstimate\Document\Media;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Szablony umów z wersjami. Zatwierdzona wersja jest niezmienna, więc umowa zawsze wskazuje tekst, z którego powstała.
 * Treść jest zapisywana bez zmian; import wzoru i edytor to kolejne etapy.
 */
class ContractTemplateService
{
	public function __construct(private ContractPolicy $policy)
	{
	}

	public function create(string $name, string $body, ?string $description = null): ContractTemplate
	{
		$this->allow();

		return DB::transaction(function () use ($name, $body, $description) {
			$template = ContractTemplate::create(['name' => $name, 'description' => $description, 'created_by' => get_current_user_id() ?: null]);
			$this->addVersion($template, $body, 1);
			ContractEvent::record('template_created', template: $template, versionNo: 1);

			return $template;
		});
	}

	/**
	 * @param  ?string  $expected  skrót treści, którą widział użytkownik; inny skrót oznacza cudze zmiany
	 */
	public function saveDraft(ContractTemplate $template, string $body, ?string $expected = null): ContractTemplateVersion
	{
		$this->allow();

		return DB::transaction(function () use ($template, $body, $expected) {
			$version = $this->lockCurrent($template);
			$this->assertActive($template);

			if (! $version->status->isEditable()) {
				throw new InvalidArgumentException('Zatwierdzonej wersji szablonu nie można edytować. Utwórz nową wersję.');
			}

			$before = hash('sha256', (string) $version->body);
			$this->assertExpected($before, $expected);
			$after = hash('sha256', $body);

			if ($before !== $after) {
				$version->update(['body' => $body]);
				ContractEvent::record('template_draft_saved', template: $template, versionNo: $version->version_no, payload: ['before' => $before, 'after' => $after]);
			}

			return $version;
		});
	}

	/**
	 * @param  ?string  $expected  skrót treści widzianej przez zatwierdzającego
	 * @param  bool  $reviewed  potwierdzenie porównania z oryginałem, wymagane po imporcie z ostrzeżeniami
	 */
	public function approve(ContractTemplate $template, ?string $expected = null, bool $reviewed = false): ContractTemplateVersion
	{
		$this->allow();

		return DB::transaction(function () use ($template, $expected, $reviewed) {
			$version = $this->lockCurrent($template);
			$this->assertActive($template);

			if ($version->isApproved()) {
				throw new InvalidArgumentException('Ta wersja szablonu jest już zatwierdzona.');
			}

			$this->assertExpected(hash('sha256', (string) $version->body), $expected);

			if (TemplateDocument::isBlank((string) $version->body)) {
				throw new InvalidArgumentException('Nie można zatwierdzić pustego szablonu.');
			}

			if ($unknown = TemplateVariables::unknown((string) $version->body)) {
				throw new InvalidArgumentException('Szablon zawiera nieznane zmienne: ' . implode(', ', $unknown) . '.');
			}

			if (! $reviewed && $this->importWarnings($template, $version->version_no)) {
				throw new InvalidArgumentException('Ten szablon pochodzi z importu z ostrzeżeniami. Potwierdź porównanie z oryginałem.');
			}

			$hash = $version->fingerprint();
			$version->update(['status' => ContractStatus::Approved, 'content_hash' => $hash, 'approved_by' => get_current_user_id() ?: null, 'approved_at' => now()]);
			ContractEvent::record('template_approved', template: $template, versionNo: $version->version_no, payload: ['hash' => $hash]);

			return $version;
		});
	}

	public function newVersion(ContractTemplate $template): ContractTemplateVersion
	{
		$this->allow();

		return DB::transaction(function () use ($template) {
			$current = $this->lockCurrent($template);
			$this->assertActive($template);

			if (! $current->isApproved()) {
				throw new InvalidArgumentException('Ten szablon ma już szkic.');
			}

			$version = $this->addVersion($template, (string) $current->body, $current->version_no + 1);
			ContractEvent::record('template_version_created', template: $template, versionNo: $version->version_no, payload: ['from' => $current->version_no]);

			return $version;
		});
	}

	/** Nazwa i opis nie należą do treści, więc można je zmieniać także po zatwierdzeniu. */
	public function rename(ContractTemplate $template, string $name, ?string $description): void
	{
		$this->allow();

		$template->fill(['name' => $name, 'description' => $description]);

		if ($template->isDirty(['name', 'description'])) {
			DB::transaction(function () use ($template) {
				$changes = array_keys($template->getDirty());
				$template->save();
				ContractEvent::record('template_renamed', template: $template, payload: ['fields' => $changes]);
			});
		}
	}

	/**
	 * Tło stron (papier firmowy) to wygląd, nie treść: nie zmienia skrótu wersji. Zatwierdzone umowy mają własną kopię.
	 */
	public function setBackground(ContractTemplate $template, ?int $attachmentId): void
	{
		$this->allow();

		if ($attachmentId !== null && ! Media::isImage($attachmentId)) {
			throw new InvalidArgumentException('Tło: wybierz obraz JPG, PNG lub WebP z biblioteki grafik.');
		}

		$template->fill(['background_id' => $attachmentId]);

		if ($template->isDirty('background_id')) {
			DB::transaction(function () use ($template, $attachmentId) {
				$template->save();
				ContractEvent::record('template_background', template: $template, payload: ['attachment_id' => $attachmentId]);
			});
		}
	}

	/**
	 * Kopia to nowy, niezależny szablon ze szkicem w wersji 1. Oryginał i jego historia zostają bez zmian.
	 * Ostrzeżenia z importu idą za treścią, żeby kopia nie omijała potwierdzenia porównania.
	 */
	public function duplicate(ContractTemplateVersion $source, string $name): ContractTemplate
	{
		$this->allow();

		return DB::transaction(function () use ($source, $name) {
			$original = $source->template;
			$copy = ContractTemplate::create(['name' => $name, 'description' => $original->description, 'background_id' => $original->background_id, 'created_by' => get_current_user_id() ?: null]);
			$this->addVersion($copy, (string) $source->body, 1);
			ContractEvent::record('template_duplicated', template: $copy, versionNo: 1, payload: ['from_template' => $original->id, 'from_version' => $source->version_no]);

			if (! $source->isApproved() && $warnings = $this->importWarnings($original, $source->version_no)) {
				ContractEvent::record('template_imported', template: $copy, versionNo: 1, payload: ['warnings' => $warnings]);
			}

			return $copy;
		});
	}

	/**
	 * Szablon z pliku DOCX: zawsze szkic do przejrzenia. Do dziennika trafia skrót pliku, a nie jego treść.
	 */
	public function import(string $name, string $body, array $warnings, string $fileHash): ContractTemplate
	{
		$this->allow();

		return DB::transaction(function () use ($name, $body, $warnings, $fileHash) {
			$template = ContractTemplate::create(['name' => $name, 'created_by' => get_current_user_id() ?: null]);
			$this->addVersion($template, $body, 1);
			ContractEvent::record('template_imported', template: $template, versionNo: 1, payload: ['file' => $fileHash, 'warnings' => $warnings]);

			return $template;
		});
	}

	/**
	 * @return list<string>
	 */
	public function importWarnings(ContractTemplate $template, int $versionNo): array
	{
		$event = ContractEvent::where('template_id', $template->id)->where('version_no', $versionNo)->where('type', 'template_imported')->latest('id')->first();

		return array_values((array) ($event?->payload['warnings'] ?? []));
	}

	/** Szablonu nie można usunąć: umowy wskazują jego wersje. Zarchiwizowany nie służy do tworzenia nowych umów. */
	public function archive(ContractTemplate $template): void
	{
		$this->allow();

		DB::transaction(function () use ($template) {
			$template->update(['archived_at' => now(), 'archived_by' => get_current_user_id() ?: null]);
			ContractEvent::record('template_archived', template: $template);
		});
	}

	private function assertActive(ContractTemplate $template): void
	{
		if (ContractTemplate::whereKey($template->id)->value('archived_at') !== null) {
			throw new InvalidArgumentException('Ten szablon jest w archiwum. Utwórz jego kopię, żeby dalej nad nim pracować.');
		}
	}

	private function assertExpected(string $current, ?string $expected): void
	{
		if ($expected !== null && ! hash_equals($current, $expected)) {
			throw new DraftConflict();
		}
	}

	private function addVersion(ContractTemplate $template, string $body, int $number): ContractTemplateVersion
	{
		$version = ContractTemplateVersion::create([
			'template_id' => $template->id,
			'version_no' => $number,
			'status' => ContractStatus::Draft,
			'body' => $body,
			'created_by' => get_current_user_id() ?: null,
		]);

		$template->update(['current_version_id' => $version->id]);

		return $version;
	}

	private function lockCurrent(ContractTemplate $template): ContractTemplateVersion
	{
		$locked = ContractTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();

		return ContractTemplateVersion::whereKey($locked->current_version_id)->lockForUpdate()->firstOrFail();
	}

	private function allow(): void
	{
		if (! $this->policy->canManageTemplates()) {
			throw new AccessDenied('Szablony umów może zmieniać tylko manager lub administrator.');
		}
	}
}
