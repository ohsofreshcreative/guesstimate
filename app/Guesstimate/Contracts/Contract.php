<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Clients\Client;
use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Support\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Umowa: jedna oferta może mieć ich wiele. Treść i dane są w wersjach (ContractVersion), a tu tylko powiązania.
 * Usunięcie umowy nie dotyka oferty ani klienta. Trwałego usuwania nie ma: dokument prawny i jego dziennik zostają.
 */
class Contract extends Model
{
	use SoftDeletes;

	protected $table = 'gst_contracts';

	protected $casts = [
		'offer_id' => 'integer',
		'client_id' => 'integer',
		'template_id' => 'integer',
		'current_version_id' => 'integer',
		'author_id' => 'integer',
		'last_editor_id' => 'integer',
		'deleted_by' => 'integer',
	];

	protected static function booted(): void
	{
		static::forceDeleting(function () {
			throw new LogicException('Umowy nie można usunąć na stałe.');
		});

		static::deleting(function (Contract $contract) {
			if ($contract->hasApprovedVersion()) {
				throw new LogicException('Umowy z zatwierdzoną wersją nie można usunąć.');
			}
		});
	}

	public function hasApprovedVersion(): bool
	{
		return $this->versions()->where('status', ContractStatus::Approved->value)->exists();
	}

	public function offer(): BelongsTo
	{
		return $this->belongsTo(Offer::class, 'offer_id')->withTrashed();
	}

	public function client(): BelongsTo
	{
		return $this->belongsTo(Client::class, 'client_id')->withTrashed();
	}

	public function template(): BelongsTo
	{
		return $this->belongsTo(ContractTemplate::class, 'template_id');
	}

	public function currentVersion(): BelongsTo
	{
		return $this->belongsTo(ContractVersion::class, 'current_version_id');
	}

	public function versions(): HasMany
	{
		return $this->hasMany(ContractVersion::class, 'contract_id')->orderByDesc('version_no');
	}

	public function events(): HasMany
	{
		return $this->hasMany(ContractEvent::class, 'contract_id')->orderByDesc('id');
	}
}
