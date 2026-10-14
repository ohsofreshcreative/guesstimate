<?php

namespace App\Guesstimate\Clients;

use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Support\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
	use SoftDeletes;

	protected $table = 'gst_clients';

	public function offers(): HasMany
	{
		return $this->hasMany(Offer::class, 'client_id');
	}
}
