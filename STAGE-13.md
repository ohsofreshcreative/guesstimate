# STAGE-13 — Testy

**Status: DONE, z zastrzeżeniami** (2026-11-02, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–12)

Wszystkie testy przechodzą, a regresja kalkulatora i snapshotów jest czysta. Testy wykryły 2 prawdziwe błędy widoku umowy na telefonie, oba naprawione. Testy nie obejmują prawdziwego Worda, logowania przez formularz, Safari, Firefoksa ani połączeń z OpenAI i ClickUp.

| Pozycja | Wynik |
|---|---|
| Migracje | **brak nowych** |
| `PublicOffer::SCHEMA`, `Pages::blank` | bez zmian |
| Build | **nie dotyczy**: bez zmian w `resources/css` i `resources/js`; użyte klasy są w obecnym buildzie |
| Zrzuty | 39 plików: `/Users/Arek/Websites/guesstimate/app/backups/2026-11-02-stage-13-zrzuty` |
| Dane chronione | snapshoty, kwoty, wersje umów, produkcja: bez zmian; uploads nadal 39 plików |

## Wyniki

| Zestaw | Wynik | Rodzaj |
|---|---|---|
| `composer test` | 257 OK (778 asercji) | mock |
| `node --test 'tests/js/*.test.mjs'` | 23 OK | czysta logika |
| `data-regression.php` przed testami | 40/40 | real |
| `data-regression.php` po testach | 17/17 (tryb bez liczb wierszy) | real |
| `render-legacy.php` | 48/48 identycznych | real |
| `document-layout.mjs` | 75/75 | real* |
| `document-compat.mjs` | 56/56 | real* |
| `document-polish.mjs` | 86/86 | real* |
| `document-print.mjs` | 29/29 | real* |
| `media-library.mjs` **nowy** | 26/26 | real* |
| `responsive.mjs` **nowy** | 99/99 | real* |
| `contract-templates.mjs` | 66/66 | real* |
| `contract-generator.mjs` | 27/27 | real* |
| `contract-payments.mjs` | 26/26 | real* |
| `contract-export.mjs` | 27/27 | real* |
| `contract-workflow.mjs` | 58/58 | real* |
| `contract-lifecycle.php` | 67/67 | real |
| `contract-from-template.php` | 25/25 | real |
| `contract-values.php` | 31/31 | real |
| `contract-payments.php` | 20/20 | real |

\* real z ograniczeniami z sekcji niżej.

**Nie wszystko powtórzono po ostatniej zmianie.** Poprawki widoku umowy i mostka testowego dotyczą `show.blade.php` i `bridge.mjs`. Po nich powtórzono zestawy: media, responsive, generator, eksport, workflow, dane po testach, `composer test`. Testy edytora dokumentu, szablonów, płatności i integracyjne szły wcześniej w tym samym etapie, już na kodzie etapu 12 i z przekierowaniem uploads, ale przed tymi dwiema poprawkami.

`document-print` ma 29 asercji zamiast 36 z etapu 06, bo tym razem bez blokady wersji zamrożonej (`LOCKED`) i bez oferty po migracji. Tamte przypadki nie były powtarzane.

## Mock i real

| Warstwa | Real | Mock lub zastępstwo |
|---|---|---|
| Baza | MySQL, kopia backupu Local | testy jednostkowe: bez bazy, atrapy funkcji WP |
| Aplikacja | prawdziwy kod, Acorn, trasy, middleware, nonce | `php-cgi` przez mostek zamiast serwera WWW |
| Przeglądarka | Chromium headless | bez Safari i Firefoksa |
| Logowanie | role i capabilities z bazy | użytkownik z nagłówka testowego; formularz `/login` niesprawdzony |
| Wtyczki | brak | wyłączone, `get_field` to atrapa |
| Przesyłanie | prawdziwy multipart, `wp_handle_upload`, miniatury, SHA-256 | katalog testowy zamiast uploads (starsze pliki tylko odczyt) |
| PDF | prawdziwy dompdf, odczyt pdf.js | nie dotyczy |
| DOCX | prawdziwy plik | `docx-preview` i parser zamiast Worda |
| OpenAI, ClickUp | nic | HTTP zablokowane, testowane tylko mapowanie i logika |
| Dane | oferty z backupu | sztuczne treści umów i konta testowe |

## Pokrycie wymagań

| Wymaganie | Test | Wynik |
|---|---|---|
| Upload | `media-library`: PNG, miniatury, odrzucenie udawanego PNG, PHP i SVG | OK |
| Ponowne użycie | ten sam plik = ten sam załącznik; jedna grafika w 2 ofertach bez kopii; archiwizacja używanej | OK |
| Drag/drop | `document-layout`: mysz, granice strony, snap, warstwy | OK; **dotyk niesprawdzony** |
| Undo | `document-layout`, `document-compat` | OK |
| Layout legacy | render 48/48, `document-compat` | OK |
| Responsywność | 14 ekranów w 3 szerokościach | OK po naprawach |
| Tokeny | `ContractValuesTest`, `contract-values`, `contract-generator` | OK |
| Brak danych | blokada zatwierdzenia i eksportu | OK |
| VAT, etapy, zadatek | `PaymentScheduleTest`, `contract-payments` (oba) | OK |
| Eksport | `contract-export`: tekst PDF = tekst DOCX | OK; **bez Worda** |
| Role | `RolesTest`, `media-library`, `contract-workflow`: 3 role, dostęp po ID | OK |
| Historia | `contract-lifecycle` (dziennik, wersje); `gst_offer_events` zgodne z baseline; historia układu | OK |
| Publiczne snapshoty | MD5 zgodne z backupem przed i po wszystkich testach; 4 adresy publiczne: status 200, bez przewijania | OK |
| Regresja kalkulatora | 10 wersji przeliczonych od nowa bez zapisu = zapisany wynik i kwoty; `ExcelCompatibilityTest` (calc.xlsx) | OK |

## Błędy znalezione przez testy

Prawdziwe, naprawione w `contracts/show.blade.php`, oba z etapu 09:

1. **Podgląd umowy na telefonie był nieczytelny.** Panel boczny miał stałe 384 px i zabierał cały ekran, a tekst umowy miał jedną literę szerokości linii. Teraz podgląd jest nad panelem.
2. **Arkusz umowy był ucięty z lewej** i tej części nie dało się przewinąć, bo był wyśrodkowany. Teraz margines nie wychodzi poza krawędź.

Moje poprawki też miały błędy, oba złapane na zrzutach, a nie przez asercje:

- pierwsza zwijała podgląd do zera,
- druga przesuwała arkusz w lewo na desktopie, bo użyła klasy spoza buildu.

Do `responsive.mjs` doszły asercje na widoczność, lewą krawędź i wyśrodkowanie.

Błędy samych testów, poprawione: warunek `hours_` łapał kolumny `high_*` (20 fałszywych błędów), a test czekał na żądanie, którego przeglądarka słusznie nie wysyła dla PHP i SVG.

## Co doszło

- `tests/ui/media-library.mjs`, `tests/ui/responsive.mjs`.
- `tests/integration/`: testy, które dotąd istniały tylko w katalogu tymczasowym, plus `data-regression.php`, `urls.php`, wzorzec `render-legacy.golden.txt` (same skróty MD5).
- `tests/ui/cgi-prepend.php`, `bridge.mjs`: przesyłanie do `GST_TEST_UPLOADS`; bez tej zmiennej przesyłanie jest w testach wyłączone. **`cgi-prepend.php` nie może trafić na serwer**, choć nic go tam nie ładuje.

## Pliki

- Nowe: wymienione wyżej, `STAGE-13.md`.
- Zmienione: `resources/views/guesstimate/contracts/show.blade.php`, `tests/ui/bridge.mjs`, `tests/ui/cgi-prepend.php`, `AGENTS.md`.

Uruchomienie: kopia bazy na porcie 33177 (`STAGE-00.md`), kolejność w nagłówkach plików. `document-*.mjs` wymagają osobnych świeżych kopii.

**Konflikt instrukcji:** `CLAUDE.md` i `AGENTS.md` zabraniają przeglądarki do weryfikacji, a etap wymaga testów e2e i zrzutów. Przyjęto polecenie etapu, tylko headless. Zrzuty leżą poza repozytorium i poza katalogiem publicznym; pokazują dane z lokalnej kopii, w tym grafikę i nazwy klientów, więc nie należy ich publikować.

## Ograniczenia

- **Word:** żaden plik DOCX nie był otwarty w Wordzie.
- **Formularz logowania, reset hasła, wylogowanie:** bez e2e.
- **OpenAI i ClickUp:** bez prawdziwych wywołań.
- **Safari, Firefox, prawdziwy telefon:** emulacja Chromium.
- **Dotyk w edytorze:** bez testu; decyzja 1 nadal otwarta.
- **Serwer WWW:** LiteSpeed, nagłówki cache i limity uploadu z produkcji niesprawdzone.
- **Równoległość:** dwóch transakcji naraz nie uruchomiono.
- **Arkusz umowy na telefonie** ma pełną szerokość A4 i przewija się w poziomie w swojej ramce. Nie jest skalowany.
- `responsive.mjs` sprawdza przewijanie i status. Wygląd obejrzano wyrywkowo: ekrany umowy, biblioteka, oferty, edytor, oferta publiczna.
- Wyniki `.mjs` i skrypty wymagają narzędzi spoza repo: `playwright-core`, `pdfjs-dist`, `docx-preview`, `jszip`.
- Wcześniejsze skrypty `it2`–`it6` (etapy 02–06) zostały poza repo; ich zakres częściowo pokrywają nowe testy.

## Rollback

Cofnięcie `show.blade.php` przywraca stary układ. Testy można usunąć bez wpływu na aplikację. Zrzuty można skasować.

## Wymagane od użytkownika

- Commit (nic nie zostało zacommitowane), razem z `public/build` z wcześniejszych etapów.
- Wcześniejsze: `composer install`, backup i `wp acorn migrate` w Local; decyzja o „Nowej wersji” umowy; otwarte decyzje 1–8.

## Do zweryfikowania ręcznie

1. DOCX w Wordzie: brak naprawy pliku, strony, numery stron, tabele.
2. Logowanie, wylogowanie, reset hasła na prawdziwych kontach.
3. Ekran umowy na prawdziwym telefonie.
4. Zdjęcia w ofertach z włączonymi wtyczkami.
5. Oferta i umowa w Safari i Firefoksie.
