<?php

namespace App\Guesstimate\Offers\Version;

use App\Guesstimate\Support\Model;

class VersionFeature extends Model
{
	public $timestamps = false;

	protected $table = 'gst_v_features';

	protected $casts = [
		'is_custom' => 'boolean',
	];
}
