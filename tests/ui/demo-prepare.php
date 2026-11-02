<?php
// Dane do tests/ui/demo.mjs (`wp eval-file`): jak contract-export-prepare.php, ale czytelne nazwy i bez umowy z podmienioną treścią.
// Tylko kopia bazy. Treść jest sztuczna, to nie jest wzór umowy.
require __DIR__ . '/contract-export-prepare.php';

use App\Guesstimate\Contracts\Contract;
use Illuminate\Support\Facades\DB;

DB::table('gst_contracts')->where('title', 'Podmieniona')->update(['deleted_at' => now()]);
DB::table('gst_contracts')->where('title', 'Szkic')->update(['title' => 'Umowa demonstracyjna']);
DB::table('gst_contracts')->where('title', 'Umowa – „eksport”')->update(['title' => 'Umowa demonstracyjna – wcześniejsza']);
echo 'DEMO=', Contract::where('title', 'Umowa demonstracyjna')->value('id'), "\n";
