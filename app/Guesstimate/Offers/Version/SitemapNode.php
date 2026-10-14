<?php

namespace App\Guesstimate\Offers\Version;

use App\Guesstimate\Support\Model;

class SitemapNode extends Model
{
	public $timestamps = false;

	protected $table = 'gst_v_sitemap_nodes';

	protected $casts = [
		'content_entry' => 'boolean',
		'instances' => 'integer',
		'position' => 'integer',
	];
}
