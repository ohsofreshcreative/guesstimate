<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Auth\AccessDenied;
use App\Guesstimate\Offers\Offer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cykl życia umowy. Treść przechodzi bez zmian: kopia szablonu trafia do wersji co do bajtu, a usługa niczego nie
 * dopisuje ani nie poprawia, bo to tekst prawny. Nic tu nie podpisuje ani nie wysyła umowy.
 */
class ContractService
{
	public function __construct(private ContractPolicy $policy)
	{
	}

	/**
	 * Nowa umowa dla oferty: wersja 1 ze szkicem z zatwierdzonej wersji szablonu.
	 * Blokada wiersza oferty wyklucza wyścig z TrashService::forceDelete().
	 */
	public function create(Offer $offer, ContractTemplateVersion $source, string $title, array $values = []): Contract
	{
		$this->allow($this->policy->canCreate($offer));

		return DB::transaction(function () use ($offer, $source, $title, $values) {
			$offer = Offer::whereKey($offer->id)->lockForUpdate()->first();

			if (! $offer) {
				throw new InvalidArgumentException('Nie można przygotować umowy dla oferty z kosza.');
			}

			if (! $offer->client_id || ! $offer->currentVersion) {
				throw new InvalidArgumentException('Umowa wymaga oferty z klientem.');
			}

			// Stan szablonu pod blokadą: przekazany obiekt mógł zostać odczytany przed archiwizacją.
			$template = ContractTemplate::whereKey($source->template_id)->lockForUpdate()->first();
			$source = ContractTemplateVersion::find($source->id);

			if (! $source?->isApproved() || ! $template || $template->isArchived()) {
				throw new InvalidArgumentException('Umowę można utworzyć tylko z zatwierdzonej wersji aktywnego szablonu.');
			}

			$values = ContractValues::clean($values, ContractSnapshot::of($offer, $offer->currentVersion));
			$me = get_current_user_id() ?: null;
			$contract = Contract::create([
				'offer_id' => $offer->id,
				'client_id' => $offer->client_id,
				'template_id' => $source->template_id,
				'title' => $title,
				'author_id' => $me,
				'last_editor_id' => $me,
			]);

			$this->addVersion($contract, $offer, (string) $source->body, $source->id, 1, $values);
			ContractEvent::record('created', $contract, versionNo: 1, payload: ['offer_id' => $offer->id, 'template_version_id' => $source->id]);

			return $contract;
		});
	}

	/** Zapis szkicu. W dzienniku skróty przed i po, więc każda zmiana szkicu zostawia ślad. */
	public function saveDraft(Contract $contract, string $body): ContractVersion
	{
		$this->allow($this->policy->canEdit($contract));

		return DB::transaction(function () use ($contract, $body) {
			$version = $this->lockCurrent($contract);

			if (! $version->status->isEditable()) {
				throw new InvalidArgumentException('Zatwierdzonej wersji nie można edytować. Utwórz nową wersję.');
			}

			$before = hash('sha256', (string) $version->body);
			$after = hash('sha256', $body);

			if ($before !== $after) {
				$version->update(['body' => $body]);
				$contract->update(['last_editor_id' => get_current_user_id() ?: null]);
				ContractEvent::record('draft_saved', $contract, versionNo: $version->version_no, payload: ['before' => $before, 'after' => $after]);
			}

			return $version;
		});
	}

	/**
	 * Ręczna edycja wartości tokenów szkicu. Treść umowy się nie zmienia, a dane źródłowe w snapshocie zostają,
	 * więc wyczyszczenie pola przywraca wartość z oferty. W dzienniku są nazwy pól, nie wartości.
	 */
	public function saveValues(Contract $contract, array $input, string $expected): ContractVersion
	{
		$this->allow($this->policy->canEdit($contract));

		return DB::transaction(function () use ($contract, $input, $expected) {
			$version = $this->lockDraft($contract, $expected);
			$snapshot = $version->data_snapshot;
			$before = (array) ($snapshot['values'] ?? []);
			$after = ContractValues::clean($input, $snapshot);
			$changed = array_keys(array_diff_assoc($before, $after) + array_diff_assoc($after, $before));

			if ($changed) {
				$version->update(['data_snapshot' => array_replace($snapshot, ['values' => $after])]);
				$contract->update(['last_editor_id' => get_current_user_id() ?: null]);
				ContractEvent::record('values_saved', $contract, versionNo: $version->version_no, payload: ['keys' => $changed]);
			}

			return $version;
		});
	}

	/**
	 * Świadome odświeżenie: nowy snapshot z aktualnej oferty, klienta i ustawień. Wartości ręczne i harmonogram zostają.
	 * `expected` potwierdza, że człowiek widział porównanie dla tych danych.
	 */
	public function refresh(Contract $contract, string $expected): ContractVersion
	{
		$this->allow($this->policy->canEdit($contract));

		return DB::transaction(function () use ($contract, $expected) {
			$version = $this->lockDraft($contract, $expected);
			$offer = Offer::whereKey($contract->offer_id)->lockForUpdate()->first();

			if (! $offer?->currentVersion || ! $offer->client) {
				throw new InvalidArgumentException('Nie można odświeżyć danych: oferta jest w koszu albo nie ma klienta.');
			}

			$from = $version->snapshotHash();
			$version->update([
				'data_snapshot' => ContractSnapshot::of($offer, $offer->currentVersion, (array) ($version->data_snapshot['values'] ?? []), (array) ($version->data_snapshot['payments'] ?? [])),
				'offer_version_id' => $offer->current_version_id,
			]);
			$contract->update(['client_id' => $offer->client_id, 'last_editor_id' => get_current_user_id() ?: null]);
			ContractEvent::record('data_refreshed', $contract, versionNo: $version->version_no, payload: ['before' => $from, 'after' => $version->snapshotHash(), 'offer_version_id' => $offer->current_version_id]);

			return $version;
		});
	}

	/** Harmonogram szkicu. Treść umowy się nie zmienia; w dzienniku są skróty, nie kwoty. */
	public function savePayments(Contract $contract, array $payments, string $expected): ContractVersion
	{
		$this->allow($this->policy->canEdit($contract));

		return DB::transaction(function () use ($contract, $payments, $expected) {
			$version = $this->lockDraft($contract, $expected);
			$before = $version->snapshotHash();
			$version->update(['data_snapshot' => array_replace($version->data_snapshot, ['payments' => $payments])]);

			if ($before !== $version->snapshotHash()) {
				$contract->update(['last_editor_id' => get_current_user_id() ?: null]);
				ContractEvent::record('payments_saved', $contract, versionNo: $version->version_no, payload: ['before' => $before, 'after' => $version->snapshotHash()]);
			}

			return $version;
		});
	}

	/** @param string|null $expected skrót danych, które osoba zatwierdzająca widziała; inny oznacza zmianę w międzyczasie */
	public function approve(Contract $contract, ?string $expected = null): ContractVersion
	{
		$this->allow($this->policy->canApprove());

		return DB::transaction(function () use ($contract, $expected) {
			$version = $this->lockCurrent($contract);

			if ($version->isApproved()) {
				throw new InvalidArgumentException('Ta wersja jest już zatwierdzona.');
			}

			if ($expected !== null && ! hash_equals($version->snapshotHash(), $expected)) {
				throw new DraftConflict();
			}

			if (trim(strip_tags((string) $version->body)) === '') {
				throw new InvalidArgumentException('Nie można zatwierdzić pustej umowy.');
			}

			if ($missing = ContractValues::missing((string) $version->body, $version->data_snapshot)) {
				throw new InvalidArgumentException('Uzupełnij przed zatwierdzeniem: ' . implode(', ', $missing) . '.');
			}

			if ($blockers = PaymentSchedule::blockers($version->data_snapshot)) {
				throw new InvalidArgumentException(implode(' ', $blockers));
			}

			$hash = $version->fingerprint();
			$version->update([
				'status' => ContractStatus::Approved,
				'background_id' => $version->backgroundId(),
				'content_hash' => $hash,
				'approved_by' => get_current_user_id() ?: null,
				'approved_at' => now(),
			]);
			ContractEvent::record('approved', $contract, versionNo: $version->version_no, payload: ['hash' => $hash]);

			return $version;
		});
	}

	/**
	 * Poprawka zatwierdzonej umowy: nowy szkic z kopią treści i aktualnym snapshotem. Poprzednia wersja zostaje.
	 * Najwyżej jeden szkic naraz.
	 */
	public function newVersion(Contract $contract): ContractVersion
	{
		$this->allow($this->policy->canEdit($contract));

		return DB::transaction(function () use ($contract) {
			$current = $this->lockCurrent($contract);

			if (! $current->isApproved()) {
				throw new InvalidArgumentException('Ta umowa ma już szkic.');
			}

			$offer = Offer::whereKey($contract->offer_id)->lockForUpdate()->first();

			if (! $offer || ! $offer->currentVersion) {
				throw new InvalidArgumentException('Nie można utworzyć nowej wersji, gdy oferta jest w koszu.');
			}

			$version = $this->addVersion($contract, $offer, (string) $current->body, $current->template_version_id, $current->version_no + 1, (array) ($current->data_snapshot['values'] ?? []), (array) ($current->data_snapshot['payments'] ?? []));
			ContractEvent::record('version_created', $contract, versionNo: $version->version_no, payload: ['from' => $current->version_no, 'offer_version_id' => $version->offer_version_id]);

			return $version;
		});
	}

	/**
	 * Usunięcie szkicu umowy (soft delete). Oferta i klient zostają. Umowa z zatwierdzoną wersją zostaje w całości.
	 */
	public function delete(Contract $contract): void
	{
		$this->allow($this->policy->canEdit($contract));

		DB::transaction(function () use ($contract) {
			$this->lockCurrent($contract);

			if ($contract->hasApprovedVersion()) {
				throw new InvalidArgumentException('Umowy z zatwierdzoną wersją nie można usunąć.');
			}

			$contract->update(['deleted_by' => get_current_user_id() ?: null]);
			$contract->delete();
			ContractEvent::record('deleted', $contract);
		});
	}

	private function addVersion(Contract $contract, Offer $offer, string $body, int $templateVersionId, int $number, array $values, array $payments = []): ContractVersion
	{
		$version = ContractVersion::create([
			'contract_id' => $contract->id,
			'version_no' => $number,
			'status' => ContractStatus::Draft,
			'template_version_id' => $templateVersionId,
			'offer_version_id' => $offer->current_version_id,
			'body' => $body,
			'data_snapshot' => ContractSnapshot::of($offer, $offer->currentVersion, $values, $payments),
			'created_by' => get_current_user_id() ?: null,
		]);

		$contract->update(['current_version_id' => $version->id]);

		return $version;
	}

	private function lockDraft(Contract $contract, string $expected): ContractVersion
	{
		$version = $this->lockCurrent($contract);

		if (! $version->status->isEditable()) {
			throw new InvalidArgumentException('Zatwierdzonej wersji nie można edytować. Utwórz nową wersję.');
		}

		if (! hash_equals($version->snapshotHash(), $expected)) {
			throw new DraftConflict();
		}

		return $version;
	}

	private function lockCurrent(Contract $contract): ContractVersion
	{
		$locked = Contract::whereKey($contract->id)->lockForUpdate()->first();

		if (! $locked) {
			throw new InvalidArgumentException('Ta umowa została usunięta.');
		}

		return ContractVersion::whereKey($locked->current_version_id)->lockForUpdate()->firstOrFail();
	}

	private function allow(bool $allowed): void
	{
		if (! $allowed) {
			throw new AccessDenied('Nie masz uprawnień do tej operacji na umowie.');
		}
	}
}
