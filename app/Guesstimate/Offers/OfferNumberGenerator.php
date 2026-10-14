<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;

/**
 * Unikalne numery ofert w formacie PREFIKS/ROK/NNNN (np. OSF/2026/0001).
 * Licznik per prefiks i rok, blokowany w transakcji (SELECT … FOR UPDATE). Numery nie są używane ponownie.
 */
class OfferNumberGenerator
{
	public function __construct(private SettingsRepository $settings)
	{
	}

	/**
	 * Wywoływać wewnątrz transakcji tworzącej ofertę.
	 */
	public function next(): string
	{
		$numbering = $this->settings->get('numbering');
		$prefix = $numbering['prefix'];
		$padding = (int) $numbering['padding'];
		$year = (int) wp_date('Y');

		DB::table('gst_number_sequences')->insertOrIgnore(['prefix' => $prefix, 'year' => $year, 'last_value' => 0]);

		$row = DB::table('gst_number_sequences')
			->where('prefix', $prefix)
			->where('year', $year)
			->lockForUpdate()
			->first();

		$value = (int) $row->last_value + 1;

		// Numer mógł zostać zajęty ręcznie albo przez inny prefiks z tym samym formatem — szukamy wolnego.
		while (DB::table('gst_offers')->where('number', self::format($prefix, $year, $value, $padding))->exists()) {
			$value++;
		}

		DB::table('gst_number_sequences')->where('id', $row->id)->update(['last_value' => $value]);

		return self::format($prefix, $year, $value, $padding);
	}

	public static function format(string $prefix, int $year, int $value, int $padding): string
	{
		return sprintf('%s/%d/%s', $prefix, $year, str_pad((string) $value, max(1, $padding), '0', STR_PAD_LEFT));
	}
}
