<?php

namespace App\Guesstimate\Contracts;

use InvalidArgumentException;

/**
 * Szkic zmienił się od otwarcia edytora. Zapis nadpisałby cudzą pracę, a zatwierdzenie objęłoby tekst, którego nikt nie widział.
 */
class DraftConflict extends InvalidArgumentException
{
	public function __construct()
	{
		parent::__construct('Ktoś inny zmienił ten szkic. Odśwież stronę, żeby zobaczyć aktualną treść.');
	}
}
