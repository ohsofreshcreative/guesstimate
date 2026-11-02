<?php
// Przygotowanie kopii bazy do tests/ui/contract-generator.mjs (`wp eval-file`): zatwierdzony szablon z tokenami.
// Treść jest sztuczna, to nie jest wzór umowy. Zatrzymuje się na każdej bazie innej niż kopia na porcie 33177.
use App\Guesstimate\Contracts\ContractTemplateService;
use App\Guesstimate\Contracts\TemplateDocument;
use App\Guesstimate\Contracts\TemplateVariables;
use App\Guesstimate\Offers\Offer;
use Illuminate\Support\Facades\DB;

global $wpdb;
config(['database.connections.wordpress.port' => 33177, 'database.connections.wordpress.host' => '127.0.0.1']);
DB::purge('wordpress');

if ((int) DB::selectOne('select @@port as p')->p !== 33177 || (int) $wpdb->get_var('select @@port') !== 33177) {
	exit("STOP: to nie jest kopia bazy\n");
}

wp_set_current_user(1);
$token = fn (string $key) => sprintf('<span class="ct-var" data-var="%s" contenteditable="false">%s</span>', $key, TemplateVariables::all()[$key]);
$body = '<h1>Umowa testowa</h1><p>Zamawiający: ' . $token('client.company_name') . ', ' . $token('client.address') . ', NIP ' . $token('client.nip') . '</p>'
	. '<p>Wykonawca: ' . $token('contractor.company_name') . '</p><p>Oferta ' . $token('offer.number') . ', wariant ' . $token('offer.variant') . ', ' . $token('offer.amount_variant') . '</p>'
	. '<p>Mapa: ' . $token('offer.pages') . '</p><p>Kwota: ' . $token('contract.amount') . '</p><p>' . $token('payments.table') . '&nbsp;</p><p>Miejsce: ' . $token('terms.place') . '</p>';
$footer = '<p>Strona ' . $token('page.number') . ' z ' . $token('page.count') . '</p>';
$service = app(ContractTemplateService::class);
$template = $service->create('Szablon generatora', TemplateDocument::compose('', $body, $footer));
$service->approve($template->fresh());
$offer = Offer::whereNotNull('client_id')->whereNotNull('current_version_id')->orderByDesc('id')->get()->first(fn ($o) => $o->currentVersion?->result);
echo "OFFER={$offer->id}\n";
