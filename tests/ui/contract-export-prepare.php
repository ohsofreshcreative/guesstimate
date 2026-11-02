<?php
// Przygotowanie kopii bazy do tests/ui/contract-export.mjs (`wp eval-file`): zatwierdzona umowa ze wszystkimi
// konstrukcjami, szkic i wersja z podmienioną treścią. Treść jest sztuczna, to nie jest wzór umowy.
// Zatrzymuje się na każdej bazie innej niż kopia na porcie 33177.
use App\Guesstimate\Contracts\ContractService;
use App\Guesstimate\Contracts\ContractTemplateService;
use App\Guesstimate\Contracts\ContractVersion;
use App\Guesstimate\Contracts\PaymentSchedule;
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
$rows = '';

for ($i = 1; $i <= 60; $i++) {
	$rows .= "<tr><td>Pozycja {$i}</td><td>Zażółć gęślą jaźń ĄĆĘŁŃÓŚŹŻ, opis wiersza numer {$i}</td><td class=\"ct-right\">{$i},00</td></tr>";
}

$long = str_repeat('Strony postanawiają, że treść tego akapitu służy wyłącznie sprawdzeniu zawijania i podziału stron. ', 6);
$body = '<h1 class="ct-center">Umowa testowa nr ' . $token('offer.number') . '</h1>'
	. '<p>Zamawiający: <strong>' . $token('client.company_name') . '</strong>, ' . $token('client.address') . ', NIP ' . $token('client.nip') . '</p>'
	. '<p class="ct-par">Przedmiot umowy</p><p class="ct-justify">' . $long . '</p>'
	. '<ul><li>punkt pierwszy</li><li>punkt <strong>drugi</strong></li></ul><ol><li>numer jeden</li><li>numer dwa</li></ol>'
	. '<p class="ct-par">Wynagrodzenie</p><p>Kwota netto: ' . $token('contract.amount') . ', brutto: <em>' . $token('contract.amount_gross') . '</em>, cudzysłów „test” &amp; znak &lt;.</p>'
	. '<p>' . $token('payments.table') . '&nbsp;</p>'
	. '<p class="ct-par">Zakres</p><table><thead><tr><th>Lp.</th><th>Opis</th><th>Wartość</th></tr></thead><tbody>' . $rows . '</tbody></table>'
	. '<hr class="ct-break"><h2>Postanowienia końcowe</h2><p class="ct-small">Drobny druk po podziale strony.</p><p class="ct-right">Podpisy stron</p>';
$document = TemplateDocument::compose('<p>Firma: ' . $token('contractor.company_name') . '</p>', $body, '<p class="ct-center">Strona ' . $token('page.number') . ' z ' . $token('page.count') . '</p>');
$templates = app(ContractTemplateService::class);
$contracts = app(ContractService::class);
$template = $templates->create('Szablon eksportu', $document);
$approved = $templates->approve($template->fresh());
$offer = Offer::whereNotNull('client_id')->whereNotNull('current_version_id')->orderByDesc('id')->get()->first(fn ($o) => $o->currentVersion?->result);
$values = ['client.address' => 'ul. Żółwia 1, 00-001 Łódź', 'client.nip' => '5252344078', 'contractor.company_name' => 'Wykonawca Testowy'];
$make = function (string $title) use ($contracts, $offer, $approved, $values) {
	$contract = $contracts->create($offer, $approved, $title, $values);
	$payments = PaymentSchedule::parse(['agreed_net' => '10 000,50', 'vat_rate' => '23', 'stages' => [['name' => 'Etap I', 'milestone' => 'Makiety', 'net' => '6000,25', 'due' => '7 dni od odbioru'], ['name' => 'Etap II', 'net' => '4000,25']], 'deposit_net' => '1000', 'deposit_mode' => 'credited', 'deposit_stage' => '1', 'deposit_due' => 'przy podpisaniu']);
	$contracts->savePayments($contract->fresh(), $payments['payments'], $contract->fresh()->currentVersion->snapshotHash());

	return $contract->fresh();
};

$ready = $make('Umowa – „eksport”');
$contracts->approve($ready);
$draft = $make('Szkic');
$broken = $make('Podmieniona');
$version = $contracts->approve($broken);
DB::table('gst_contract_versions')->where('id', $version->id)->update(['body' => str_replace('Przedmiot umowy', 'Inny przedmiot', (string) $version->body)]);
echo "READY={$ready->id} DRAFT={$draft->id} BROKEN={$broken->id}\n";
