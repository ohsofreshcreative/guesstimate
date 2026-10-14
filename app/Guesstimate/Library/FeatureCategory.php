<?php

namespace App\Guesstimate\Library;

use App\Guesstimate\Support\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureCategory extends Model
{
	protected $table = 'gst_feature_categories';

	public function features(): HasMany
	{
		return $this->hasMany(Feature::class, 'category_id')->orderBy('sort')->orderBy('name');
	}
}
