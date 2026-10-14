<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Support\Model;

class OfferEvent extends Model
{
	public const UPDATED_AT = null;

	protected $table = 'gst_offer_events';

	protected $casts = [
		'payload' => 'array',
	];
}
