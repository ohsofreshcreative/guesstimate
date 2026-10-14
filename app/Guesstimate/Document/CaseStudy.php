<?php

namespace App\Guesstimate\Document;

use App\Guesstimate\Support\Model;

/**
 * Realizacja OhSoFresh pokazywana na stronie „W kooperacji siła”.
 */
class CaseStudy extends Model
{
	public const MAX_METRICS = 3;

	protected $table = 'gst_case_studies';

	protected $casts = ['metrics' => 'array', 'is_active' => 'boolean', 'attachment_id' => 'integer'];
}
