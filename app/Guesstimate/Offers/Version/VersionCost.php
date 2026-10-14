<?php

namespace App\Guesstimate\Offers\Version;

use App\Guesstimate\Support\Model;

class VersionCost extends Model
{
	public const TYPES = [
		'license' => 'Licencja',
		'plugin' => 'Płatna wtyczka',
		'service' => 'Usługa zewnętrzna',
		'hosting' => 'Hosting',
		'other' => 'Inny koszt',
	];

	public const BILLING = [
		'one_time' => 'Jednorazowo',
		'monthly' => 'Co miesiąc',
		'yearly' => 'Co rok',
	];

	public const BILLING_PUBLIC = [
		'one_time' => 'jednorazowo',
		'monthly' => '/ miesiąc',
		'yearly' => '/ rok',
	];

	public $timestamps = false;

	protected $table = 'gst_v_costs';

	protected $casts = [
		'amount' => 'integer',
	];
}
