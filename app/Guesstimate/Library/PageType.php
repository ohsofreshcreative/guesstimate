<?php

namespace App\Guesstimate\Library;

use App\Guesstimate\Support\Model;

class PageType extends Model
{
	public const KINDS = [
		'unique' => 'Podstrona indywidualna (własny szablon)',
		'template' => 'Szablon wielokrotny (liczony raz)',
	];

	protected $table = 'gst_page_types';

	protected $casts = [
		'is_active' => 'boolean',
	];

	public function kindLabel(): string
	{
		return $this->kind === 'unique' ? 'Indywidualna' : 'Szablon';
	}
}
