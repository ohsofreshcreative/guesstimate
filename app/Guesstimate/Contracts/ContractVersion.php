<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Support\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wersja umowy: treść (kopia szablonu lub poprawki) i snapshot danych z chwili jej utworzenia.
 * Zmiany w ofercie i u klienta jej nie dotyczą. Po zatwierdzeniu niezmienna.
 */
class ContractVersion extends Model
{
	use FreezesOnApproval;

	protected $table = 'gst_contract_versions';

	protected $casts = [
		'status' => ContractStatus::class,
		'contract_id' => 'integer',
		'version_no' => 'integer',
		'template_version_id' => 'integer',
		'offer_version_id' => 'integer',
		'data_snapshot' => 'array',
		'background_id' => 'integer',
		'created_by' => 'integer',
		'approved_by' => 'integer',
		'approved_at' => 'datetime',
	];

	public function contract(): BelongsTo
	{
		return $this->belongsTo(Contract::class, 'contract_id')->withTrashed();
	}

	/**
	 * Tło stron: zatwierdzona wersja ma własną kopię z chwili zatwierdzenia, szkic pokazuje bieżące tło szablonu.
	 */
	public function backgroundId(): ?int
	{
		if ($this->isApproved()) {
			return $this->background_id ?: null;
		}

		return $this->contract?->template?->background_id ?: null;
	}

	public function templateVersion(): BelongsTo
	{
		return $this->belongsTo(ContractTemplateVersion::class, 'template_version_id');
	}

	/** Skrót zapisanych bajtów, a nie zdekodowanych danych: każda późniejsza zmiana treści lub snapshotu go unieważnia. */
	public function fingerprint(): string
	{
		return self::hash((string) $this->body, (string) $this->getAttributes()['data_snapshot']);
	}

	/** Skrót danych, które człowiek widział na ekranie: wykrywa zmianę w drugiej karcie przed zapisem. */
	public function snapshotHash(): string
	{
		return hash('sha256', (string) $this->getAttributes()['data_snapshot']);
	}

	public static function hash(string $body, string $snapshot): string
	{
		return hash('sha256', $body . "\0" . $snapshot);
	}
}
