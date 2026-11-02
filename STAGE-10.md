# STAGE-10 — Platnosci

**Status: DONE** (2026-11-02, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–09)

Umowa ma konfigurowalny harmonogram płatności: uzgodnioną kwotę, etapy i zadatek. Cena z oferty jest tylko orientacyjna. Rozbieżności blokują zatwierdzenie albo dają ostrzeżenie, a kod niczego sam nie dociąga.

| Pozycja | Wynik |
|---|---|
| Migracje | **brak nowych**; moduł wymaga migracji z etapu 07 |
| `PublicOffer::SCHEMA`, `Pages::blank` | bez zmian |
| Build | `yarn build` wykonany lokalnie, OK; **bez commitu** |
| Testy PHP | `composer test`: 237 OK (710 asercji; przed etapem 221) |
| Testy JS | `node --test 'tests/js/*.test.mjs'`: 23 OK |
| Test UI etapu | **26/26 OK** (`tests/ui/contract-payments.mjs`), headless Chromium, kopia bazy |
| Integracja etapu | 20/20 OK na kopii bazy |
| Regresja | generator UI 27/27 i integracja 31/31, edytor szablonów 66/66, cykl umowy 67/67, umowa z szablonu 25/25 |
| Render legacy | **48/48** pozycji identycznych ze wzorcem |

## Kryteria odbioru

| Kryterium | Jak sprawdzono | Wynik |
|---|---|---|
| Rozliczenie bez podwójnego liczenia | zadatek zaliczony: etapy 12 300 zł brutto = zadatek 1 230 + dopłaty 11 070; odrębny ponad pełną kwotę daje blokadę | OK |
| Rounding do 0,01 zł | VAT od każdej pozycji, grosze i `brick/math`, HALF_UP: 0,50 zł → 0,12 zł VAT | OK |
| Ostrzeżenia o rozbieżnościach | suma, VAT, procenty, widełki, zadatek wyższy od etapu | OK |
| Cena orientacyjna oddzielona od kwoty umowy | pole kwoty puste mimo widełek; zmiana oferty nie dotyka kwoty; kwota poza widełkami dozwolona, z ostrzeżeniem | OK |
| Brak narzuconego 30/30/30/10 | nowa umowa ma 0 etapów i 0 procentów; w kodzie nie ma takiego podziału | OK |

## Co powstało

- **Ekran `/app/umowy/{id}/platnosci`:** kwota, stawka VAT, etapy (nazwa, kamień milowy, netto, procent, termin), zadatek i tabela rozliczenia z VAT, brutto, procentami i dopłatami.
- **Tokeny szablonu:** kwota netto, VAT, brutto, stawka, zadatek oraz harmonogram jako tabela.
- **Panel umowy:** kwota, blokady i ostrzeżenia oraz wejście do harmonogramu.
- **Zapis:** w `data_snapshot.payments`, więc harmonogram jest zamrażany przy zatwierdzeniu i objęty skrótem `content_hash`. Odświeżenie danych i nowa wersja go zachowują.

## Rozstrzygnięcia

| Sytuacja | Skutek |
|---|---|
| Brak uzgodnionej kwoty lub etapów (gdy cokolwiek wpisano) | blokada |
| Etapy + zadatek odrębny ≠ kwota netto | blokada, z kwotą różnicy |
| Zadatek bez wybranego rozliczenia | blokada |
| Zadatek wyższy od etapu lub brak etapu | blokada |
| VAT od pozycji ≠ VAT od całości | ostrzeżenie |
| Procent zapisany ≠ procent z kwot | ostrzeżenie |
| Procenty nie sumują się do 100 | ostrzeżenie |
| Kwota poza ceną orientacyjną | ostrzeżenie |

## Decyzje

- **Decyzja 4 nadal otwarta, a kod ją omija:** kwotę zawsze wpisuje człowiek. Jeśli wybierzesz wariant kalkulacji, wystarczy dodać przycisk przepisania.
- **Decyzja 5 nierozstrzygnięta w kodzie:** oba tryby zadatku działają, a wartości domyślnej nie ma.
- **Decyzja 6 zachowuje oba zapisy:** procent wpisany z umowy nie zmienia kwot. Oba są na ekranie, a ostrzeżenie wymienia różnicę. Procent liczony jest od brutto. Ostrzeżenia nie blokują zatwierdzenia, bo rozbieżność wynika ze wzoru i rozstrzyga ją człowiek.
- **Procent wpisany jest wskazaniem w tabeli umowy,** a bez wpisu tabela pokazuje procent z kwot. W jednej tabeli mogą być więc obok siebie oba rodzaje.
- **Zmiana względem etapu 09:** „Kwota umowy” przestała być ręcznym tekstem. Tokeny kwot wynikają z harmonogramu i nie przyjmują wartości ręcznych, żeby treść nie rozjechała się z tabelą. Wcześniejsze ręczne wpisy tego tokenu są ignorowane; prawdziwych umów jeszcze nie ma.
- **Jedna stawka VAT na umowę.** Domyślna z ustawień, do zmiany w umowie.
- **Bez migracji:** dane należą do wersji umowy.
- **Termin to tekst,** bo terminy bywają względne („7 dni od odbioru”).

## Błędy znalezione przez testy

W kodzie aplikacji żaden test nie wykrył wady. Dwie porażki pierwszego przebiegu UI to błędne oczekiwania testu: udział 6 000 z 10 000 to 60%, nie 66,67%, a pole pokazuje `10000`. Poprawiono też niespójny zapis „100%” w komunikacie.

## Pliki

- Nowe:
  - `app/Guesstimate/Contracts/PaymentSchedule.php`
  - `resources/views/guesstimate/contracts/payments.blade.php`, `partials/payment-notices.blade.php`
  - `tests/Unit/Contracts/PaymentScheduleTest.php`, `tests/ui/contract-payments.mjs`
  - `STAGE-10.md`
- Zmienione:
  - `app/Guesstimate/Contracts/ContractValues.php`, `ContractSnapshot.php` (schemat 3), `ContractService.php`, `TemplateVariables.php`
  - `app/Guesstimate/Http/Controllers/ContractController.php`, `routes/app.php`
  - `resources/views/guesstimate/contracts/show.blade.php`, `partials/fields.blade.php`
  - `tests/Unit/Contracts/ContractHtmlTest.php`, `ContractValuesTest.php`, `tests/ui/contract-generator.mjs`, `contract-prepare.php`, `AGENTS.md`
  - `public/build/**`: wynik `yarn build`

## Testy

- **`contract-payments.mjs`:** brak domyślnych etapów, walidacja, każda rozbieżność, oba tryby zadatku, odczyt po odświeżeniu, tabela w dokumencie, tekst z HTML, konflikt dwóch kart, cudzy harmonogram (403).
- **Integracja:** treść bez zmian, blokady zatwierdzenia, zatwierdzenie mimo ostrzeżeń, skrót wykrywa podmianę kwoty, odświeżenie, nowa wersja, zatwierdzona v1 bez zmian, role, kwoty ofert i publikacje bez zmian.
- Ostatnia poprawka tekstu komunikatu nastąpiła po regresji; po niej powtórzono test UI etapu, integrację etapu, testy PHP i JS.
- `document-*.mjs` nie były powtórzone: etap nie zmienia edytora ani oferty publicznej. Render legacy przeszedł.
- `mysql-stop.sh` przy ostatnim przebiegu zgłosił „NIE ZATRZYMANO”, bo serwer zamykał się dłużej niż limit skryptu. Sprawdzono potem: 0 procesów kopii, port wolny, kopia usunięta ręcznie. Realne uploads: 39 plików.

**Konflikt instrukcji:** jak w poprzednich etapach, `CLAUDE.md` i `AGENTS.md` zabraniają przeglądarki do weryfikacji, a etap i Christie zlecają testy headless. Przyjęto polecenie etapu. Dotyczy to też `yarn build`.

## Ograniczenia

- **Nie miałem prawdziwego wzoru umowy.** Tabela ma stałe kolumny i może nie odpowiadać §5 i §6. Szablon może użyć pojedynczych tokenów kwot, ale nie ma tokenów dla konkretnego etapu.
- **Zadatek wskazuje etap numerem.** Usunięcie wcześniejszego wiersza przesuwa numerację. Gdy etap przestaje istnieć, jest blokada, ale gdy numer nadal istnieje, zadatek przejdzie na inny etap. Widać to w rozliczeniu po zapisie.
- **VAT:** jedna stawka na umowę, a zadatek liczony tak samo jak etapy. Traktowanie podatkowe zadatku i moment powstania obowiązku podatkowego to temat dla księgowości. Zadatek można wpisać tylko netto.
- **Kwoty przyjęto jako netto.** Formularz i komunikaty tak to nazywają, a cena z oferty jest porównywana z netto. Założenie, że oferty są netto, wymaga potwierdzenia.
- **Waluty inne niż PLN:** liczone tak samo, bez przeliczeń.
- **Podgląd liczy się po zapisie,** nie w trakcie pisania.
- **Tabela w dokumencie nie ma wiersza sumy.** Kwoty umowy są osobnymi tokenami.
- **Kwoty słownie nie ma.**
- **Blokady działają w usłudze; przycisku zatwierdzenia nadal nie ma,** bo należy do etapu Workflow. Zatwierdzenie sprawdzono testem integracyjnym.
- Limit kwoty: 999 999 999,99 netto na pozycję (istniejący `Input::money`).
- Tylko Chromium, administrator i odmowa pracownikowi. Tryb tylko do odczytu po zatwierdzeniu sprawdzono na poziomie usługi, nie w UI.

## Rollback

Cofnięcie kodu i `public/build`. Struktura bazy się nie zmienia. Klucz `payments` stary kod zignoruje. Treść umów jest niezmieniona.

## Wymagane od użytkownika

- Backup i `wp acorn migrate` w Local (z etapu 07, nadal niewykonane).
- Commit razem z `public/build`.
- Prawdziwy wzór umowy.
- **Decyzje 4, 5, 6:** kod działa bez nich, ale każda umowa wymaga wtedy rozstrzygnięcia ręcznego.
- Konsultacja księgowa w sprawie VAT od zadatku.

## Do zweryfikowania ręcznie

Po migracji w Local:

1. Odtworzenie §5 i §6 prawdziwego wzoru: czy tabela i ostrzeżenia oddają ten przypadek.
2. Zadatek w obu trybach na prawdziwych liczbach.
3. Usunięcie etapu, gdy zadatek jest zaliczony.
4. Konto managera i pracownika z własną ofertą.
5. Długi harmonogram w podglądzie A4.
