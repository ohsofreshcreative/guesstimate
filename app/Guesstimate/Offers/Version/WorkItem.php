<?php

namespace App\Guesstimate\Offers\Version;

use App\Guesstimate\Support\Model;

class WorkItem extends Model
{
	public $timestamps = false;

	protected $table = 'gst_v_work_items';
}
