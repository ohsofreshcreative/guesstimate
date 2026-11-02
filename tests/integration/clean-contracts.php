<?php
require __DIR__ . '/guard.php';
use Illuminate\Support\Facades\DB;
foreach (['gst_contract_events','gst_contract_versions','gst_contracts','gst_contract_template_versions','gst_contract_templates'] as $t) DB::table($t)->delete();
echo "wyczyszczono\n";
