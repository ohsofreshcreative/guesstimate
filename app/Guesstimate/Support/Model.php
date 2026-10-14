<?php

namespace App\Guesstimate\Support;

use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * Bazowy model aplikacji: połączenie WordPressa (prefiks wp_), jawnie wypełniane pola.
 */
abstract class Model extends Eloquent
{
	protected $guarded = ['id'];
}
