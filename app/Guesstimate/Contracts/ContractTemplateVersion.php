<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Support\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractTemplateVersion extends Model
{
	use FreezesOnApproval;

	protected $table = 'gst_contract_template_versions';

	protected $casts = [
		'status' => ContractStatus::class,
		'template_id' => 'integer',
		'version_no' => 'integer',
		'created_by' => 'integer',
		'approved_by' => 'integer',
		'approved_at' => 'datetime',
	];

	public function template(): BelongsTo
	{
		return $this->belongsTo(ContractTemplate::class, 'template_id');
	}

	public function fingerprint(): string
	{
		return hash('sha256', (string) $this->body);
	}
}
