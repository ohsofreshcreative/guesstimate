<?php
// Regresja danych chronionych na kopii bazy (`wp eval-file`), bez żadnego zapisu:
// snapshoty publikacji i liczby wierszy względem baseline z backupu, kwoty zapisane względem ponownego przeliczenia.
// BASELINE=…/baseline-data.txt; MODE=snapshots pomija liczby wierszy (po testach, które dopisują wiersze).
require __DIR__ . '/guard.php';

use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Offers\VersionSnapshot;
use App\Guesstimate\Offers\VersionCalculator;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$pass, &$fail) {
	$ok ? $pass++ : $fail++;
	echo $ok ? 'OK   ' : 'FAIL ', $name, $extra !== '' && ! $ok ? " {$extra}" : '', "\n";
};
$lines = file((string) getenv('BASELINE'), FILE_IGNORE_NEW_LINES) ?: [];
$section = '';
$counts = [];
$snapshots = [];

foreach ($lines as $line) {
	if (str_starts_with($line, '## ')) {
		$section = $line;
	} elseif (str_contains($section, 'Liczby wierszy') && preg_match('/^wp_(gst_\S+) (\d+)$/', $line, $m)) {
		$counts[$m[1]] = (int) $m[2];
	} elseif (str_contains($section, 'Snapshoty publikacji') && preg_match('/^(\d+)\t.*\t([0-9a-f]{32})\t[01]$/', $line, $m)) {
		$snapshots[(int) $m[1]] = $m[2];
	}
}

$check('baseline wczytany', count($counts) > 10 && count($snapshots) > 3);

foreach ($snapshots as $id => $hash) {
	$stored = DB::table('gst_publications')->where('id', $id)->value('snapshot');
	$check("snapshot publikacji {$id} bez zmian", md5((string) $stored) === $hash);
}

if (getenv('MODE') !== 'snapshots') {
	foreach ($counts as $table => $count) {
		$check("liczba wierszy {$table}", DB::table($table)->count() === $count, (string) DB::table($table)->count());
	}
}

$canonical = function ($value) use (&$canonical) {
	if (is_array($value)) {
		ksort($value);

		return array_map($canonical, $value);
	}

	return is_numeric($value) ? (float) $value : $value;
};
$calculator = app(VersionCalculator::class);
$money = ['hours_min', 'hours_max', 'low_min', 'low_max', 'mid_min', 'mid_max', 'high_min', 'high_max'];

foreach (OfferVersion::orderBy('id')->get() as $version) {
	$version->unsetRelations();
	[$result] = $calculator->preview(VersionSnapshot::of($version));
	$check("wersja {$version->id} ({$version->calc_model}): wynik identyczny", $canonical($result) === $canonical($version->result));

	foreach ($money as $column) {
		$expected = str_starts_with($column, 'hours_') ? ($result['hours'][substr($column, 6)] ?? null) : ($result['levels'][substr($column, 0, strpos($column, '_'))]['total'][substr($column, strpos($column, '_') + 1)] ?? null);

		if ((float) $version->{$column} !== (float) $expected) {
			$check("wersja {$version->id}: {$column}", false, "{$version->{$column}} vs {$expected}");
		}
	}
}

echo "WYNIK: {$pass} ok, {$fail} błędów\n";
