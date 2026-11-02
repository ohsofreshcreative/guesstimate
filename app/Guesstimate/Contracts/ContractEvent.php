<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Support\Model;
use LogicException;

/**
 * Dziennik zmian umów i szablonów. Tylko dopisywanie: wpisów nie można zmienić ani usunąć.
 * Payload zawiera metadane i skróty, nie treść umowy.
 */
class ContractEvent extends Model
{
	public const UPDATED_AT = null;

	protected $table = 'gst_contract_events';

	protected $casts = ['payload' => 'array', 'contract_id' => 'integer', 'template_id' => 'integer', 'version_no' => 'integer', 'user_id' => 'integer'];

	protected static function booted(): void
	{
		$refuse = function () {
			throw new LogicException('Wpisów dziennika umów nie można zmieniać ani usuwać.');
		};

		static::updating($refuse);
		static::deleting($refuse);
	}

	public static function record(string $type, ?Contract $contract = null, ?ContractTemplate $template = null, ?int $versionNo = null, array $payload = []): self
	{
		return self::create([
			'contract_id' => $contract?->id,
			'template_id' => $template?->id ?? $contract?->template_id,
			'version_no' => $versionNo,
			'user_id' => get_current_user_id() ?: null,
			'type' => $type,
			'payload' => $payload,
			'created_at' => now(),
		]);
	}
}
