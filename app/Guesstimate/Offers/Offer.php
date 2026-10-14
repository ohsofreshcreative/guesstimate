<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Clients\Client;
use App\Guesstimate\Support\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
	use SoftDeletes;

	protected $table = 'gst_offers';

	protected $casts = [
		'status' => OfferStatus::class,
		'author_id' => 'integer',
		'last_editor_id' => 'integer',
		'deleted_by' => 'integer',
		'publication_status' => PublicationStatus::class,
		'publication_id' => 'integer',
		'view_count' => 'integer',
		'first_viewed_at' => 'datetime',
		'last_viewed_at' => 'datetime',
	];

	public function publication(): BelongsTo
	{
		return $this->belongsTo(Publication::class, 'publication_id');
	}

	public function publications(): HasMany
	{
		return $this->hasMany(Publication::class, 'offer_id')->orderByDesc('number');
	}

	public function client(): BelongsTo
	{
		return $this->belongsTo(Client::class, 'client_id')->withTrashed();
	}

	public function currentVersion(): BelongsTo
	{
		return $this->belongsTo(OfferVersion::class, 'current_version_id');
	}

	public function versions(): HasMany
	{
		return $this->hasMany(OfferVersion::class, 'offer_id')->orderByDesc('version_no');
	}

	public function events(): HasMany
	{
		return $this->hasMany(OfferEvent::class, 'offer_id')->orderByDesc('created_at')->orderByDesc('id');
	}
}
