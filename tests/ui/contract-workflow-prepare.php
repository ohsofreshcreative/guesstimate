<?php
// Przygotowanie kopii bazy do tests/ui/contract-workflow.mjs (`wp eval-file`). Treść jest sztuczna, to nie jest wzór umowy.
// Zatrzymuje się na każdej bazie innej niż kopia na porcie 33177.
use App\Guesstimate\Contracts\ContractService;
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

$employee = (int) getenv('EMPLOYEE');
wp_set_current_user(1);
$token = fn (string $key) => sprintf('<span class="ct-var" data-var="%s" contenteditable="false">%s</span>', $key, TemplateVariables::all()[$key]);
$body = '<h1>Umowa testowa</h1><p>Klient: ' . $token('client.company_name') . ', ' . $token('client.address') . '</p>';
$templates = app(ContractTemplateService::class);
$contracts = app(ContractService::class);
$template = $templates->create('Szablon workflow', TemplateDocument::compose('', $body, ''));
$source = $templates->approve($template->fresh());
$offers = Offer::whereNotNull('client_id')->whereNotNull('current_version_id')->orderByDesc('id')->get()->filter(fn ($o) => $o->currentVersion?->result)->values();
[$adminOffer, $employeeOffer] = [$offers[0], $offers[1]];
DB::table('gst_offers')->where('id', $adminOffer->id)->update(['author_id' => 1]);
DB::table('gst_offers')->where('id', $employeeOffer->id)->update(['author_id' => $employee]);
$adminOffer = $adminOffer->fresh();
$employeeOffer = $employeeOffer->fresh();
$values = ['client.address' => 'ul. Żółwia 1'];
$draft = $contracts->create($adminOffer, $source, 'Umowa alfa', $values);
$incomplete = $contracts->create($adminOffer, $source, 'Umowa bez adresu', []);
$approved = $contracts->create($adminOffer, $source, 'Umowa beta', $values);
$contracts->approve($approved->fresh());
$onMine = $contracts->create($employeeOffer, $source, 'Cudza na mojej ofercie', $values);
$removed = $contracts->create($adminOffer, $source, 'Umowa usunięta', $values);
$contracts->delete($removed->fresh());

for ($i = 1; $i <= 21; $i++) {
	$contracts->create($adminOffer, $source, "Seria {$i}", $values);
}

printf("ADMIN_OFFER=%d EMPLOYEE_OFFER=%d DRAFT=%d INCOMPLETE=%d APPROVED=%d ON_MINE=%d REMOVED=%d\n", $adminOffer->id, $employeeOffer->id, $draft->id, $incomplete->id, $approved->id, $onMine->id, $removed->id);
printf("OFFER_NUMBER=%s CLIENT=%s\n", $adminOffer->number, str_replace(' ', '_', (string) $adminOffer->client?->company_name));
