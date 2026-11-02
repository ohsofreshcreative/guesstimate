<?php
// Skróty HTML wszystkich stron dokumentu: publikacje (snapshot) i wersje bieżące (public, editor, thumbs). Tylko odczyt.
use App\Guesstimate\Document\DocumentView;
use App\Guesstimate\Offers\{Offer, PublicOffer, PublicationService, Publication};
if (! function_exists('get_field')) { function get_field() { return null; } }
global $wpdb;
config(['database.connections.wordpress.port' => 33177]); Illuminate\Support\Facades\DB::purge('wordpress');
if ((int) $wpdb->get_var('select @@port') !== 33177) { exit("STOP\n"); }
wp_set_current_user(1);
$out = [];
$r = fn ($snap, $mode) => view('guesstimate.public.partials.pages', ['offer' => DocumentView::fromSnapshot($snap), 'mode' => $mode])->render();
foreach (Publication::orderBy('id')->get() as $p) {
	foreach (['public', 'editor', 'thumbs'] as $m) { $out["pub{$p->id}:$m"] = md5($r((array) $p->snapshot, $m)); }
	$out["pub{$p->id}:snapshot"] = md5(json_encode($p->snapshot));
}
foreach (Offer::orderBy('id')->get() as $o) {
	$v = $o->currentVersion;
	if (! $v || ! $v->isBudget() || ! $v->result) { continue; }
	$day = fn ($s) => array_replace_recursive($s, ['document' => ['cover' => ['date' => '2026-11-01']]]);
	$out["offer{$o->id}:public"] = md5($r($day(PublicOffer::build($o, $v)), 'public'));
	$out["offer{$o->id}:editor"] = md5($r($day(PublicOffer::build($o, $v, null, true)), 'editor'));
	$out["offer{$o->id}:outdated"] = var_export(PublicationService::outdated($o, $o->publication), true);
	$out["offer{$o->id}:fit"] = App\Guesstimate\Document\DocumentSettings::fitHash(App\Guesstimate\Document\DocumentSettings::forVersion($v, app(App\Guesstimate\Settings\SettingsRepository::class)->all()), (array) $v->content);
}
foreach ($out as $k => $h) { echo "$k $h\n"; }
