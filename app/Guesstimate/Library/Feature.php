<?php

namespace App\Guesstimate\Library;

use App\Guesstimate\Support\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feature extends Model
{
	protected $table = 'gst_features';

	protected $casts = [
		'is_active' => 'boolean',
	];

	public function category(): BelongsTo
	{
		return $this->belongsTo(FeatureCategory::class, 'category_id');
	}
}
