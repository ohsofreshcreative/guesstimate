<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Support\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Szablon umowy. Nie jest usuwany, tylko archiwizowany: umowy wskazują jego wersje.
 */
class ContractTemplate extends Model
{
	protected $table = 'gst_contract_templates';

	protected $casts = ['current_version_id' => 'integer', 'background_id' => 'integer', 'created_by' => 'integer', 'archived_by' => 'integer', 'archived_at' => 'datetime'];

	public function versions(): HasMany
	{
		return $this->hasMany(ContractTemplateVersion::class, 'template_id')->orderByDesc('version_no');
	}

	public function currentVersion(): BelongsTo
	{
		return $this->belongsTo(ContractTemplateVersion::class, 'current_version_id');
	}

	public function latestApproved(): HasOne
	{
		return $this->hasOne(ContractTemplateVersion::class, 'template_id')->where('status', ContractStatus::Approved->value)->orderByDesc('version_no');
	}

	public function isArchived(): bool
	{
		return $this->archived_at !== null;
	}
}
