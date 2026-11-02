# STAGE-08 — Edytor Szablonow

**Status: DONE** (2026-11-02, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–07)

Manager i administrator mogą pod `/app/szablony-umow` pisać, kopiować, wersjonować i zatwierdzać szablony umów w prostym edytorze z podglądem A4 pionowo. Istniejący wzór można zaimportować z DOCX jako szkic. Edytor i import nie obiecują zgodności z Wordem i mówią o tym w interfejsie.

| Pozycja | Wynik |
|---|---|
| Migracje | **brak nowych**; moduł wymaga migracji z etapu 07 |
| `PublicOffer::SCHEMA`, `Pages::blank` | bez zmian |
| Build | `yarn build` wykonany lokalnie, OK; **bez commitu** |
| Testy PHP | `composer test`: 210 OK (628 asercji; przed etapem 179) |
| Testy JS | `node --test 'tests/js/*.test.mjs'`: 23 OK |
| Test UI etapu | **66/66 OK** (`tests/ui/contract-templates.mjs`), headless Chromium, kopia bazy |
| Bez migracji | 3/3 OK: instrukcja 503, reszta aplikacji działa |
| Integracja umowa ← szablon | 25/25 OK na kopii bazy |
| Regresja etapu 07 | cykl życia umowy 67/67 |
| Regresja UI | układ pól 75/75, kompatybilność 56/56, dopracowanie stron 86/86 |
| Render legacy | **48/48** pozycji identycznych ze wzorcem |

## Kryteria odbioru

| Kryterium | Jak sprawdzono | Wynik |
|---|---|---|
| Brak obietnicy pełnej zgodności z Wordem | teksty w edytorze, imporcie i podglądzie; ostrzeżenia z importu | OK |
| Zapis | treść po odświeżeniu, polskie znaki, emoji, ukośniki, cudzysłowy, podwójne spacje; drugi zapis nie zmienia bajtów | OK |
| Kopia | nowy niezależny szablon z wybranej wersji, szkic v1 | OK |
| Wersje | v2 to kopia v1, v1 tylko do odczytu i bez zmian | OK |
| Zatwierdzenie | wersja niezmienna w UI i na serwerze; umowa dostaje jej treść co do bajtu | OK |

## Co powstało

- **Formatowanie:** nagłówki 1–3, akapit, drobny druk, pogrubienie, kursywa, podkreślenie, wyrównanie.
- **Numeracja:** paragraf § liczony automatycznie, listy punktowane i numerowane.
- **Tabele:** wstawianie, dodawanie i usuwanie wierszy i kolumn.
- **Podział strony, nagłówek i stopka** z numerami stron.
- **Zmienne:** dane klienta i oferty oraz numer i liczba stron. Kwot celowo nie ma: wymagają decyzji 4.
- **Podgląd A4 pionowo:** strony z nagłówkiem i stopką, tabele dzielone po wierszach, numeracja § ciągnie się przez strony.
- **Cofanie i ponawianie:** Ctrl+Z, Ctrl+Shift+Z, także dla tabel. Ctrl+S zapisuje.
- **Import DOCX:** nagłówki, akapity, listy, tabele, formatowanie znaków, podziały stron.

Menu „Szablony umów” widzą manager i administrator. Pracownik dostaje 403.

## Decyzje

- **Bez nowej kolumny.** Nagłówek, treść i stopka są w `body`, więc należą do `content_hash` i przechodzą do umowy bez zmian w kodzie etapu 07.
- **Własny `ContractHtml` zamiast `OfferContent::clean`,** który zamienia nagłówki na akapity. Usuwa znaczniki, nigdy tekst.
- **Wklejanie przyjmuje czysty tekst.** Formatowanie z Worda znika, ale do umowy nie trafi ukryty kod. Można to zmienić, jeśli będzie uciążliwe.
- **Zapis i zatwierdzenie porównują skrót szkicu.** Druga osoba dostaje 409 zamiast nadpisać pracę, a zatwierdzenie obejmuje tekst, który ktoś widział.
- **Import jest bezpieczny, więc powstał:**
  - plik nie trafia na dysk ani do uploads, do dziennika idzie tylko jego skrót;
  - limity rozmiaru sprawdzane przed odczytem, DOCTYPE i makra odrzucane;
  - tekst wyniku jest porównywany z tekstem pliku niezależną ścieżką, a różnica odrzuca import.
- **Import z ostrzeżeniami wymaga potwierdzenia porównania z oryginałem,** także na serwerze i przy kopiowaniu szkicu.
- **Śledzone zmiany w DOCX:** usunięcia pominięte, wstawienia przyjęte. Obie liczby są w ostrzeżeniach.
- **Zarchiwizowanego szablonu nie można edytować ani przywrócić.** Dalsza praca idzie przez kopię.

## Błędy znalezione przez testy

Wszystkie naprawione:

1. **Zapis usuwał ukośniki z tekstu:** `C:\plik` zapisywało się jako `C:plik`. `UnslashInput` czyścił też dane JSON, do których WordPress nic nie dodaje. **Dotyczyło to również zapisów edytora dokumentu oferty.** Błąd wykrył test UI; moje wcześniejsze założenie, że JSON jest bezpieczny, było błędne.
2. Pusty szablon miał akapit bez wysokości: tekst lądował poza blokami.
3. Ctrl+B przywracał starą pozycję kursora i kasował przełączenie formatu.
4. Chrome zostawiał listę w akapicie; edytor poprawia strukturę od razu.
5. `insertHTML` wypychał zmienną poza akapit, a kursor utykał. Zmienne, tabele i podziały idą teraz przez DOM.
6. Akcje tabeli po sobie nie widziały komórki. „Usuń wiersz” przechodziło w teście tylko dlatego, że wcześniejsze dodanie wiersza się nie udało.
7. **Układ `editor` nie pokazywał komunikatów flash** i po cichu je tracił. Dotyczyło też edytora dokumentu.
8. Błąd walidacji pojawiał się w obu modalach.

Poprawki infrastruktury testów: `bridge.mjs` obsługuje przekierowania, które Playwright wypuszczał poza mostek; plik wysyłany jest ręcznie zbudowanym `multipart`, bo Playwright nie udostępnia jego treści. `cgi-prepend.php` przyjmuje użytkownika z nagłówka testowego, co wciąż działa tylko jako `auto_prepend_file`.

## Korekta raportu etapu 07

Napisałem, że bez migracji aplikacja pokazuje instrukcję zamiast działać. To było nieprawdą: `EnsureSchema` sprawdzał tylko `gst_offer_versions`. Bez migracji aplikacja działa, ale **trwałe usunięcie oferty i usunięcie klienta kończą się błędem SQL**. `STAGE-07.md` poprawiono. Moduł szablonów pokazuje teraz instrukcję, a te dwie operacje nadal wymagają migracji.

## Pliki

- Nowe:
  - `app/Guesstimate/Contracts/ContractHtml.php`, `TemplateDocument.php`, `TemplateVariables.php`, `TemplateForm.php`, `DocxImporter.php`, `DraftConflict.php`
  - `app/Guesstimate/Http/Controllers/ContractTemplateController.php`
  - `resources/js/guesstimate/contract-editor.js`, `contract-pages.js`
  - `resources/views/guesstimate/contract-templates/index.blade.php`, `edit.blade.php`
  - `tests/Unit/Contracts/ContractHtmlTest.php`, `DocxImporterTest.php`
  - `tests/js/contract-pages.test.mjs`, `tests/ui/contract-templates.mjs`
  - `STAGE-08.md`
- Zmienione:
  - `app/Guesstimate/Contracts/ContractTemplateService.php`
  - `app/Guesstimate/Http/Middleware/UnslashInput.php`, `EnsureSchema.php`
  - `app/Guesstimate/Support/Modules.php`, `routes/app.php`
  - `resources/js/guesstimate.js`, `resources/css/guesstimate.css`
  - `resources/views/components/gst/icon.blade.php`, `resources/views/guesstimate/layouts/editor.blade.php`
  - `tests/Unit/Support/NavigationTest.php`, `tests/ui/bridge.mjs`, `tests/ui/cgi-prepend.php`
  - `STAGE-07.md`, `AGENTS.md`
  - `public/build/**`: wynik `yarn build`

## Testy

- **`contract-templates.mjs`:** wszystkie kontrolki paska, cofanie, wklejanie, atak XSS przez edytor, trwałość po odświeżeniu, podgląd (A4, 60 wierszy tabeli bez strat, numery stron, §), konflikt dwóch kart, zatwierdzanie, wersje, kopia, archiwum, import, role.
- **Integracja:** umowa z szablonem ma treść co do bajtu z nagłówkiem i stopką, a zmiana samego nagłówka jest wykrywana skrótem.
- **Import:** pliki syntetyczne, także XXE, makra, bomba zip, uszkodzony XML, nieznany kontener.
- **Układ pól:** 75/75 na świeżej kopii, po wszystkich zmianach. W ciągu po innych testach było 71/75, bo reset nie przywraca oferty w pełni; ten sam objaw opisuje etap 05. Testy UI nadal wymagają osobnych kopii.
- `document-print.mjs` nie był powtórzony: `offer.css`, `offer.js` i trasy publiczne bez zmian.
- Po testach prywatna baza jest zatrzymana, a procedura czeka na wyjście procesu.

**Konflikt instrukcji:** jak w poprzednich etapach, `CLAUDE.md` i `AGENTS.md` zabraniają przeglądarki do weryfikacji, a etap i Christie zlecają testy headless. Przyjęto polecenie etapu. Dotyczy to też `yarn build`.

## Ograniczenia

- **Nie miałem prawdziwego wzoru umowy.** `SZABLON_UMOWY_NOTATKI.md` nie został przekazany, a w repo nie ma pliku DOCX. Import sprawdzono na plikach sztucznych. Dokument z Worda może mieć konstrukcje, których kod nie zna: wtedy import zostanie odrzucony, co jest bezpieczne, ale może wymagać poprawek.
- **Numeracja automatyczna z Worda nie jest tekstem.** Listy liczą się od nowa, a odwołania typu „zgodnie z § 5” w tekście się nie aktualizują. To ostrzeżenie importu.
- **Paragraf § to etykieta generowana w CSS.** Eksport z etapu 11 musi ją wyliczyć; inaczej numery znikną w DOCX i PDF.
- **Podgląd jest przybliżony:** akapitów nie dzieli między strony, a blok wyższy od strony jest ucinany i zgłaszany komunikatem.
- Podział strony widać jako przerywaną linię, bez etykiety.
- Listy nie mają poziomów. Tabele nie mają scalania komórek w edytorze; `colspan` z importu jest zachowany.
- Scalenia pionowe, przypisy, komentarze, pola tekstowe, grafiki i nagłówki z DOCX nie są przenoszone, wszystkie są zgłaszane.
- Historia cofania znika po zapisie i odświeżeniu.
- Zachowanie `contenteditable` zależy od przeglądarki. **Sprawdzono tylko Chromium.** Safari i Firefox mogą inaczej traktować listy i kursor.
- `Ctrl+B` itd. test wykonuje klawiszem Control. Na macOS obsługa Cmd jest w kodzie, ale nie została sprawdzona.
- Edytor wymaga szerokiego ekranu: papier ma 210 mm, a na telefonie się przewija.
- Zmiany w `UnslashInput` nie cofają ukośników utraconych w ofertach zapisanych wcześniej.

## Rollback

Cofnięcie kodu i `public/build`. Struktura bazy się nie zmienia. Treść szablonów zostaje w `body`; stary kod potraktuje znaczniki sekcji jako treść, ale nie ma w nim interfejsu, który by to pokazał.

## Wymagane od użytkownika

- Backup i `wp acorn migrate` w Local (z etapu 07, nadal niewykonane).
- Commit razem z `public/build`.
- Dostarczenie prawdziwego wzoru umowy i próba importu.
- Potwierdzenie decyzji 8; decyzje 3 i 4 przed generatorem.

## Do zweryfikowania ręcznie

Po migracji w Local:

1. Import prawdziwego wzoru i porównanie z oryginałem, szczególnie numeracji.
2. Safari i Firefox: listy, tabele, kursor przy zmiennych, Cmd+Z, Cmd+B.
3. Czy styl paragrafu § (wyśrodkowany, pogrubiony) odpowiada wzorowi.
4. Konto managera: pełna ścieżka do zatwierdzenia.
5. Komunikaty w edytorze dokumentu oferty: nowy dymek w rogu.
