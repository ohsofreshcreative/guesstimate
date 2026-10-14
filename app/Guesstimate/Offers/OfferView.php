<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Support\Model;

class OfferView extends Model
{
	public $timestamps = false;

	protected $table = 'gst_offer_views';

	protected $casts = ['viewed_at' => 'datetime'];
}
