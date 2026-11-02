<?php
require __DIR__ . '/guard.php';
use App\Guesstimate\Auth\{AccessDenied, Roles};
use App\Guesstimate\Clients\{Client, ClientService};
use App\Guesstimate\Contracts\{Contract, ContractEvent, ContractPolicy, ContractService, ContractStatus, ContractTemplate, ContractTemplateService, ContractTemplateVersion, ContractVersion};
use App\Guesstimate\Offers\{Offer, TrashService};
use Illuminate\Support\Facades\DB;

$pass = 0; $fail = 0;
$ok = function (string $n, $c) use (&$pass, &$fail) { $c ? $pass++ : $fail++; echo($c ? '  ok  ' : ' FAIL '), $n, "\n"; };
$throws = function (callable $f, string $class = Throwable::class) { try { $f(); return false; } catch (Throwable $e) { return $e instanceof $class ? ($e->getMessage() ?: true) : 'INNY: ' . get_class($e) . ': ' . $e->getMessage() === 0; } };
$sum = function () { $o = []; foreach (['gst_publications','gst_offer_versions','gst_clients','gst_offer_events','gst_media_refs'] as $t) { $o[$t] = DB::selectOne('checksum table `wp_' . $t . '`')->Checksum; } return $o; };

Roles::sync();
wp_set_current_user(1);
$mk = fn ($login, $role) => get_user_by('login', $login)?->ID ?: wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(), 'user_email' => $login . '@example.test', 'role' => $role]);
$emp = $mk('t7emp', Roles::EMPLOYEE); $emp2 = $mk('t7emp2', Roles::EMPLOYEE); $mgr = $mk('t7mgr', Roles::MANAGER);
$offer = Offer::whereNotNull('client_id')->whereNotNull('current_version_id')->orderByDesc('id')->get()->first(fn ($o) => $o->currentVersion?->result);
$ok('oferta testowa z klientem', (bool) $offer);
$before = $sum();
$GLOBALS['t7pub'] = DB::table('gst_publications')->orderBy('id')->get()->keyBy('id')->all();
$offerRow = DB::table('gst_offers')->where('id', $offer->id)->first();

$body = "<p>§ 1. Zażółć „gęślą” jaźń\u{00A0}– 100 % 😀</p>\r\n\r\n<p>  dwie spacje  </p><!-- kom --><script>x</script>\n";
$templates = app(ContractTemplateService::class); $contracts = app(ContractService::class); $policy = app(ContractPolicy::class);

// Uprawnienia do szablonów
wp_set_current_user($emp);
$ok('pracownik nie tworzy szablonu', $throws(fn () => $templates->create('X', 'x'), AccessDenied::class));
wp_set_current_user($mgr);
$t = $templates->create('Wzór testowy', $body);
$tv = $t->fresh()->currentVersion;
$ok('szablon v1 = szkic', $tv->version_no === 1 && $tv->status === ContractStatus::Draft);
$ok('szkic szablonu nie daje umowy', $throws(fn () => $contracts->create($offer, $tv, 'U'), InvalidArgumentException::class));
$approved = $templates->approve($t->fresh());
$ok('szablon zatwierdzony przez managera', $approved->isApproved() && $approved->isIntact());
$ok('treść szablonu bajt w bajt po odczycie', ContractTemplateVersion::find($approved->id)->body === $body);
$ok('szablon zamrożony: model', $throws(fn () => ContractTemplateVersion::find($approved->id)->update(['body' => 'zmiana']), LogicException::class));
$ok('szablon zamrożony: usuwanie', $throws(fn () => ContractTemplateVersion::find($approved->id)->delete(), LogicException::class));
$ok('szablon zamrożony: usługa', $throws(fn () => $templates->saveDraft($t->fresh(), 'zmiana'), InvalidArgumentException::class));
$t2v = $templates->newVersion($t->fresh());
$ok('nowa wersja szablonu = v2, kopia', $t2v->version_no === 2 && $t2v->body === $body);
$ok('tylko jeden szkic szablonu', $throws(fn () => $templates->newVersion($t->fresh()), InvalidArgumentException::class));
$templates->saveDraft($t->fresh(), 'inna treść v2');
$ok('v1 szablonu nietknięta', ContractTemplateVersion::find($approved->id)->body === $body && ContractTemplateVersion::find($approved->id)->isIntact());

// Wiele umów dla jednej oferty
$c1 = $contracts->create($offer, $approved, 'Umowa A');
$c2 = $contracts->create($offer, $approved, 'Umowa B');
$c3 = $contracts->create($offer, $approved, 'Aneks');
$ok('3 umowy dla jednej oferty', Contract::where('offer_id', $offer->id)->count() === 3);
$ok('powiązanie z klientem', $c1->client_id === $offer->client_id && $c1->client->id === $offer->client_id);
$v1 = $c1->fresh()->currentVersion;
$ok('umowa = kopia szablonu bajt w bajt', $v1->body === $body && $v1->template_version_id === $approved->id);
$ok('snapshot: oferta, klient, wynik', $v1->data_snapshot['offer']['number'] === $offer->number && $v1->data_snapshot['client']['id'] === $offer->client_id && ! empty($v1->data_snapshot['pricing']['result']['levels']));
$ok('snapshot: wszystkie 3 warianty, bez kwoty umowy', count($v1->data_snapshot['pricing']['result']['levels']) === 3 && ! array_key_exists('amount', $v1->data_snapshot['pricing']));
$ok('wersja wskazuje wersję oferty', $v1->offer_version_id === $offer->current_version_id);

// Snapshot odporny na zmiany oferty i klienta
$name = $offer->client->company_name;
DB::table('gst_clients')->where('id', $offer->client_id)->update(['company_name' => 'ZMIENIONA']);
DB::table('gst_offer_versions')->where('id', $offer->current_version_id)->update(['project_name' => 'ZMIENIONY']);
$raw = DB::table('gst_contract_versions')->where('id', $v1->id)->value('data_snapshot');
$ok('snapshot bez zmian po zmianie klienta i oferty', json_decode($raw, true)['client']['company_name'] === $name && ! str_contains($raw, 'ZMIENION'));
DB::table('gst_clients')->where('id', $offer->client_id)->update(['company_name' => $name]);
DB::table('gst_offer_versions')->where('id', $offer->current_version_id)->update(['project_name' => $v1->data_snapshot['offer']['title']]);

// Zatwierdzanie i historia
wp_set_current_user($emp);
$ok('pracownik nie widzi cudzej umowy', ! $policy->canView($c1->fresh()));
$ok('pracownik nie edytuje cudzej', $throws(fn () => $contracts->saveDraft($c1->fresh(), 'x'), AccessDenied::class));
$ok('pracownik nie zatwierdza', $throws(fn () => $contracts->approve($c1->fresh()), AccessDenied::class));
$ok('pracownik nie tworzy umowy do cudzej oferty', $offer->author_id === $emp || $throws(fn () => $contracts->create($offer, $approved, 'X'), AccessDenied::class));
$ok('lista pracownika pusta', $policy->scope(Contract::query())->count() === 0);
wp_set_current_user($mgr);
$contracts->saveDraft($c1->fresh(), $body . 'poprawka');
$ok('zapis szkicu w dzienniku ze skrótami', ($e = ContractEvent::where('contract_id', $c1->id)->where('type', 'draft_saved')->first()) && $e->payload['before'] === hash('sha256', $body));
$contracts->saveDraft($c1->fresh(), $body . 'poprawka');
$ok('zapis bez zmian nie dopisuje wpisu', ContractEvent::where('contract_id', $c1->id)->where('type', 'draft_saved')->count() === 1);
$a = $contracts->approve($c1->fresh());
$hash = $a->content_hash;
$ok('zatwierdzona', $a->fresh()->isApproved() && $a->fresh()->isIntact() && $a->approved_by === $mgr);
$ok('ponowne zatwierdzenie odrzucone', $throws(fn () => $contracts->approve($c1->fresh()), InvalidArgumentException::class));
$ok('edycja zatwierdzonej: usługa', $throws(fn () => $contracts->saveDraft($c1->fresh(), 'x'), InvalidArgumentException::class));
$ok('edycja zatwierdzonej: model', $throws(fn () => ContractVersion::find($a->id)->update(['body' => 'x']), LogicException::class));
$ok('zmiana statusu z powrotem: model', $throws(fn () => ContractVersion::find($a->id)->update(['status' => 'draft']), LogicException::class));
$ok('usunięcie wersji: model', $throws(fn () => ContractVersion::find($a->id)->delete(), LogicException::class));
$ok('usunięcie umowy z zatwierdzoną wersją', $throws(fn () => $contracts->delete($c1->fresh()), InvalidArgumentException::class));
$ok('usunięcie umowy modelem też odmowa', $throws(fn () => Contract::find($c1->id)->delete(), LogicException::class));
$ok('forceDelete odmowa', $throws(fn () => Contract::find($c1->id)->forceDelete(), LogicException::class));
$n = $contracts->newVersion($c1->fresh());
$ok('nowa wersja = v2 szkic z kopią', $n->version_no === 2 && $n->status === ContractStatus::Draft && $n->body === $a->fresh()->body);
$ok('tylko jeden szkic', $throws(fn () => $contracts->newVersion($c1->fresh()), InvalidArgumentException::class));
$ok('v1 zostaje nietknięta', ($x = ContractVersion::find($a->id)) && $x->isIntact() && $x->content_hash === $hash && $x->isApproved());
$contracts->saveDraft($c1->fresh(), 'v2');
$ok('historia: 2 wersje', Contract::find($c1->id)->versions()->count() === 2 && Contract::find($c1->id)->versions->pluck('version_no')->all() === [2, 1]);

// Wykrywanie obejścia przez SQL (na kopii)
DB::table('gst_contract_versions')->where('id', $a->id)->update(['body' => 'podmiana']);
$ok('podmiana SQL wykryta skrótem', ! ContractVersion::find($a->id)->isIntact());
DB::table('gst_contract_versions')->where('id', $a->id)->update(['body' => $body . 'poprawka']);
$ok('po przywróceniu skrót zgodny', ContractVersion::find($a->id)->isIntact());

// Dziennik tylko do dopisywania
$ev = ContractEvent::where('contract_id', $c1->id)->first();
$ok('wpisu nie można zmienić', $throws(fn () => $ev->update(['type' => 'x']), LogicException::class));
$ok('wpisu nie można usunąć', $throws(fn () => $ev->delete(), LogicException::class));
$ok('dziennik: pełna ścieżka', array_slice(ContractEvent::where('contract_id', $c1->id)->orderBy('id')->pluck('type')->all(), 0, 4) === ['created', 'draft_saved', 'approved', 'version_created']);
$ok('dziennik bez treści umowy', ! DB::table('gst_contract_events')->where('payload', 'like', '%gęślą%')->exists());

// Oferta i klient
$trash = app(TrashService::class); wp_set_current_user(1);
$trash->trash($offer->fresh());
$ok('kosz: umowy zostają', Contract::where('offer_id', $offer->id)->count() === 3);
$ok('kosz: relacja do oferty działa', Contract::find($c1->id)->offer?->trashed() === true);
$ok('kosz: nowa umowa odrzucona', $throws(fn () => $contracts->create($offer, $approved, 'X'), InvalidArgumentException::class));
$ok('forceDelete oferty odrzucone', $throws(fn () => $trash->forceDelete(Offer::withTrashed()->find($offer->id)), InvalidArgumentException::class));
$ok('oferta nadal istnieje z wersjami', Offer::withTrashed()->find($offer->id) && DB::table('gst_offer_versions')->where('offer_id', $offer->id)->exists());
$trash->restore(Offer::withTrashed()->find($offer->id));
$ok('przywrócona', ! Offer::find($offer->id)->trashed());
$res = app(ClientService::class)->delete($offer->client_id, 1);
$ok('klient z umowami in_use', $res['result'] === 'in_use');

// Usunięcie szkicu nie dotyka oferty; forceDelete blokuje też soft-usunięte
$contracts->delete($c3->fresh());
$ok('szkic usunięty miękko', Contract::find($c3->id) === null && Contract::withTrashed()->find($c3->id)->deleted_by === 1);
$ok('szkic po usunięciu ma historię', ContractVersion::where('contract_id', $c3->id)->exists() && ContractEvent::where('contract_id', $c3->id)->where('type', 'deleted')->exists());
$ok('oferta po usunięciu umowy', Offer::find($offer->id) !== null);
$ok('edycja usuniętej odrzucona', $throws(fn () => $contracts->saveDraft(Contract::withTrashed()->find($c3->id), 'x'), InvalidArgumentException::class));

// Własna umowa pracownika
$own = Offer::whereNotNull('client_id')->whereNotNull('current_version_id')->where('id', '!=', $offer->id)->orderBy('id')->get()->first(fn ($o) => $o->currentVersion?->result);
DB::table('gst_offers')->where('id', $own->id)->update(['author_id' => $emp]);
wp_set_current_user($emp);
$c4 = $contracts->create($own->fresh(), $approved, 'Moja');
$ok('pracownik: własna oferta, własna umowa', $c4->author_id === $emp && $policy->canView($c4) && $policy->canEdit($c4));
$ok('pracownik: widzi tylko swoją', $policy->scope(Contract::query())->pluck('id')->all() === [$c4->id]);
$contracts->saveDraft($c4->fresh(), 'moja treść');
$ok('pracownik nie zatwierdza własnej', $throws(fn () => $contracts->approve($c4->fresh()), AccessDenied::class));
wp_set_current_user($emp2);
$ok('drugi pracownik nie ma dostępu', ! $policy->canView($c4->fresh()) && $policy->scope(Contract::query())->count() === 0);
wp_set_current_user($mgr);
$ok('pusta umowa nie do zatwierdzenia', (function () use ($contracts, $c2, $throws) { $contracts->saveDraft($c2->fresh(), '<p> </p>'); return $throws(fn () => $contracts->approve($c2->fresh()), InvalidArgumentException::class); })());
$templates->archive($t->fresh());
$ok('szablon zarchiwizowany nie daje umów', $throws(fn () => $contracts->create($offer->fresh(), $approved, 'X'), InvalidArgumentException::class));
$ok('umowy po archiwizacji działają', Contract::find($c1->id)->template->isArchived() && ContractTemplate::count() === 1);

// Ochrona danych
$after = $sum(); unset($before['gst_offer_events'], $after['gst_offer_events']);
foreach ($after as $k => $v) { if ($before[$k] !== $v) echo "  różnica: $k\n"; }
$pubBefore = $GLOBALS['t7pub'];
foreach (DB::table('gst_publications')->orderBy('id')->get() as $r) { $b = $pubBefore[$r->id]; foreach ((array) $r as $col => $val) { if ($val !== $b->$col) echo "  pub {$r->id}.$col: ", substr((string) $b->$col, 0, 30), ' -> ', substr((string) $val, 0, 30), "\n"; } }
unset($before['gst_publications'], $after['gst_publications']);
$ok('wersje, klienci, media bez zmian', $after === $before);
$snapNow = DB::table('gst_publications')->orderBy('id')->pluck('snapshot', 'id')->map(fn ($s) => md5($s))->all();
$ok('snapshoty publikacji identyczne', $snapNow === array_map(fn ($r) => md5($r->snapshot), $pubBefore));
DB::table('gst_offers')->where('id', $own->id)->update(['author_id' => $own->author_id]);
$now = DB::table('gst_offers')->where('id', $offer->id)->first();
$ok('kwoty i status oferty bez zmian', $now->status === $offerRow->status && $now->number === $offerRow->number && $now->current_version_id === $offerRow->current_version_id);
$ok('nic nie wysłano', true === empty($GLOBALS['phpmailer']->Subject ?? null));
echo "WYNIK: $pass ok, $fail błędów\n";
