<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Support\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opublikowana wersja oferty — zamrożony snapshot tego, co widzi klient.
 */
class Publication extends Model
{
	public $timestamps = false;

	protected $table = 'gst_publications';

	protected $casts = [
		'snapshot' => 'array',
		'published_at' => 'datetime',
		'withdrawn_at' => 'datetime',
		'number' => 'integer',
		'version_no' => 'integer',
	];

	public function offer(): BelongsTo
	{
		return $this->belongsTo(Offer::class, 'offer_id');
	}
}
