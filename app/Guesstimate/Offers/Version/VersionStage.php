<?php

namespace App\Guesstimate\Offers\Version;

use App\Guesstimate\Support\Model;

class VersionStage extends Model
{
	public $timestamps = false;

	protected $table = 'gst_v_stages';

	protected $casts = [
		'enabled' => 'boolean',
		'in_pool' => 'boolean',
		'fixed_amount' => 'integer',
	];
}
