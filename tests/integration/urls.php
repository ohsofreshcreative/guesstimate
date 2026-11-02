<?php
// Adresy dla tests/ui/document-print.mjs i responsive.mjs (`wp eval-file`, OUT=plik). Zawierają tokeny publiczne: tylko do pliku tymczasowego.
require __DIR__ . '/guard.php';

use Illuminate\Support\Facades\DB;

$rows = DB::table('gst_publications as p')->join('gst_offers as o', 'o.id', '=', 'p.offer_id')->whereNull('p.withdrawn_at')->where('o.publication_status', 'published')->orderBy('p.id')->get(['p.id', 'o.public_token']);
$urls = [];

foreach ($rows as $row) {
	$urls[$row->public_token] = ['name' => "publikacja {$row->id}", 'url' => home_url("/offer/{$row->public_token}/")];
}

file_put_contents((string) getenv('OUT'), json_encode(array_values($urls), JSON_UNESCAPED_SLASHES));
echo 'URLS=', count($urls), "\n";
