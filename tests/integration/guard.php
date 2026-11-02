<?php
use Illuminate\Support\Facades\DB;
if (! function_exists('get_field')) { function get_field() { return null; } }
global $wpdb;
config(['database.connections.wordpress.port' => 33177, 'database.connections.wordpress.host' => '127.0.0.1']);
DB::purge('wordpress');
$acorn = (int) (array_values((array) DB::selectOne('select @@port as p'))[0] ?? 0);
if ($acorn !== 33177 || (int) $wpdb->get_var('select @@port') !== 33177) { exit("STOP: nie kopia (acorn=$acorn)\n"); }
