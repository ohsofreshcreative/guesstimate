<?php
// Przygotowanie kopii bazy do tests/ui/document-polish.mjs (`wp eval-file`). Realizacje w bazie mogą nie mieć grafik.
// Zatrzymuje się na każdej bazie innej niż kopia na porcie 33177.
use App\Guesstimate\Document\CaseStudy;
use Illuminate\Support\Facades\DB;

global $wpdb;
config(['database.connections.wordpress.port' => 33177]);
DB::purge('wordpress');

if ((int) $wpdb->get_var('select @@port') !== 33177) {
	exit("STOP: to nie jest kopia bazy\n");
}

CaseStudy::whereKey(1)->update(['attachment_id' => 12]); // pionowa
CaseStudy::whereKey(2)->update(['attachment_id' => 7]); // pozioma; realizacja 3 zostaje bez grafiki
echo "ok\n";
