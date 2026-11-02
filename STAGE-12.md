# STAGE-12 — Workflow

**Status: DONE** (2026-11-02, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–11)

Obieg umowy działa w interfejsie: lista, wyszukiwanie, filtr, generowanie z oferty, szkic, podgląd, zatwierdzenie i pobranie. Nic nie wysyła ani nie podpisuje umów.

| Pozycja | Wynik |
|---|---|
| Migracje | **brak nowych**; moduł wymaga migracji z etapu 07 |
| `PublicOffer::SCHEMA`, `Pages::blank` | bez zmian |
| Nowe zależności | brak |
| Build | **nie dotyczy**: bez zmian w `resources/css` i `resources/js`; użyte klasy są w obecnym buildzie |
| Testy PHP | `composer test`: 257 OK (778 asercji; przed etapem 254) |
| Testy JS | `node --test 'tests/js/*.test.mjs'`: 23 OK |
| Test UI etapu | **58/58 OK** (`tests/ui/contract-workflow.mjs`), headless Chromium, kopia bazy, 3 role |
| Regresja | generator 27, integracja 31, płatności 26 i 20, szablony 66, cykl umowy 67, umowa z szablonu 25, eksport 27: wszystkie OK |
| Render legacy | **48/48** pozycji identycznych ze wzorcem |

## Kryteria odbioru

| Kryterium | Jak sprawdzono | Wynik |
|---|---|---|
| Nieuprawniony nie odczyta umowy po ID | pracownik: GET podglądu, `?wersja=1`, płatności: 403, na szkicu i zatwierdzonej | OK |
| Nieuprawniony nie wyeksportuje cudzego dokumentu | pracownik: PDF, DOCX, `?wersja=1`: 403, na szkicu i zatwierdzonej | OK |
| Nie zmieni cudzej umowy | POST zatwierdzenia, wartości, odświeżenia, płatności: 403 | OK |

Odmowy 403 pochodzą z polityki dostępu, a nie z tokenu: ten sam token przechodzi na własnej umowie. Lista i liczniki też są ograniczone, a parametr `author` nie omija zakresu.

## Co doszło

| Element | Działanie |
|---|---|
| **Lista `/app/umowy`** | szukanie po nazwie, numerze oferty i kliencie; filtr statusu i autora; po 20 na stronie |
| **Menu** | „Umowy” dla wszystkich tworzących oferty, „Szablony umów” jak dotąd dla managera i administratora |
| **Zatwierdź umowę** | sekcja w panelu umowy, okno potwierdzenia z informacją, że nic nie jest wysyłane ani podpisywane |
| **Blokady** | puste pola i błędy harmonogramu wyłączają przycisk; serwer sprawdza je też sam |
| **Zmiana w międzyczasie** | jeśli dane szkicu zmieniły się po otwarciu strony, zatwierdzenie jest odrzucane |
| **Odświeżenie danych** | okno ostrzega, gdy dane oferty się zmieniły, a umowa ich nie odświeżyła |

Bez zmian, bo już działały: generowanie z karty oferty, zapis szkicu (wartości i płatności), podgląd, pobieranie, duplikowanie szablonu. Test UI przeszedł je wszystkie w jednym obiegu.

## Uprawnienia

Bez nowych capabilities. Podział nadal jest **propozycją z decyzji 8**.

| Działanie | Pracownik | Manager | Administrator |
|---|---|---|---|
| Lista i podgląd | własne umowy i umowy do własnych ofert | wszystkie | wszystkie |
| Generowanie | z własnych ofert | z każdej | z każdej |
| Edycja szkicu | tylko umowy, których jest autorem | wszystkie | wszystkie |
| Zatwierdzanie | nie | tak | tak |
| Pobieranie zatwierdzonej | jak podgląd | wszystkie | wszystkie |
| Szablony | nie | tak | tak |

## Błąd znaleziony przez testy

Mój nowy komunikat „Brakujące pola umowy.” powielał nagłówek panelu i wykrył to `contract-generator.mjs`. Zmieniono treść na „Uzupełnij puste pola umowy.”, a generator (27/27) i obieg (58/58) powtórzono na świeżej kopii. Pozostałe testy regresji nie były powtarzane po tej zmianie jednego napisu.

## Pliki

- Nowe:
  - `app/Guesstimate/Contracts/ContractQuery.php`
  - `resources/views/guesstimate/contracts/index.blade.php`
  - `tests/Unit/Contracts/ContractQueryTest.php`
  - `tests/ui/contract-workflow.mjs`, `tests/ui/contract-workflow-prepare.php`
  - `STAGE-12.md`
- Zmienione:
  - `app/Guesstimate/Http/Controllers/ContractController.php`, `app/Guesstimate/Contracts/ContractService.php`, `routes/app.php`
  - `app/Guesstimate/Support/Modules.php`, `tests/Unit/Support/NavigationTest.php`
  - `resources/views/guesstimate/contracts/show.blade.php`
  - `resources/views/components/gst/status-badge.blade.php`, `icon.blade.php`
  - `AGENTS.md`

Klucz modułu `contracts` oznacza teraz listę umów, a szablony mają `contract_templates`. W kodzie klucze są używane tylko przy budowaniu menu.

## Ochrona danych

- Testy wyłącznie na kopii z backupu `2026-11-01-stage-00`. Skrypt przygotowujący zmienia autora dwóch ofert tylko tam i zatrzymuje się na innej bazie.
- Snapshoty, kwoty, wersje umów, produkcja: bez zmian. W Local nie uruchomiono migracji.
- Po testach prywatna baza jest zatrzymana, kopia usunięta, procesy `mysqld`, Chromium i `php-cgi`: 0. Realny katalog uploads ma nadal 39 plików.
- `it7` wycofał publikację 5 na kopii, jak w poprzednich etapach.

**Konflikt instrukcji:** `CLAUDE.md` i `AGENTS.md` zabraniają przeglądarki do weryfikacji, a etap wymaga testów UI. Przyjęto polecenie etapu, tylko headless.

## Ograniczenia

- **Zatwierdzona umowa nie ma poprawki w interfejsie.** `ContractService::newVersion` działa, ale etap nie wymieniał tej akcji, więc nie ma przycisku. Błąd w zatwierdzonej umowie można dziś naprawić tylko przez kod, a umowy z zatwierdzoną wersją nie da się usunąć. **To najważniejszy brak do decyzji.**
- Nie ma też usuwania szkicu w interfejsie.
- Statusy: szkic i zatwierdzona. Własnej numeracji umów nie dodano (decyzja 3).
- Filtr statusu dotyczy bieżącej wersji.
- Nazwa klienta na liście pochodzi z aktualnego rekordu, nie ze snapshotu. Dokument i eksport używają snapshotu.
- Odmowa 403 potwierdza, że umowa o danym ID istnieje. Treści nie ujawnia.
- Pracownik będący autorem oferty widzi cudzą umowę do tej oferty, ale nie może jej zmienić.
- Testy: tylko Chromium, ekran 1500 px. Lista ma przewijaną tabelę, ale widoku telefonu nie sprawdzono. Ekran umowy ma stały panel boczny, jak od etapu 09.
- Nie było testu prawdziwej równoległości dwóch zatwierdzeń; chroni je blokada wiersza.
- Zrzut okna potwierdzenia złapał je w trakcie animacji. Działanie sprawdzono kliknięciami.

## Korekta raportu etapu 11

`STAGE-11.md` podaje, że zatwierdzanie nie ma przycisku. Ten etap to zmienił: przyciski pobrania są teraz osiągalne w zwykłym obiegu.

## Rollback

Cofnięcie kodu. Struktura bazy się nie zmienia. Zatwierdzone wersje pozostają ważne, bo zatwierdzenie zapisuje istniejące pola.

## Wymagane od użytkownika

- Decyzja, czy dodać „Nową wersję” do zatwierdzonej umowy i usuwanie szkicu.
- Potwierdzenie decyzji 8 (kto zatwierdza) i decyzji 3 (numeracja i statusy).
- `composer install`, a przy braku migracji z etapu 07 backup i `wp acorn migrate` w Local.
- Commit (nic nie zostało zacommitowane). Ten etap nie zmienia `public/build`, ale poprzednie tak.

## Do zweryfikowania ręcznie

Po migracji, na prawdziwych kontach:

1. Pracownik: lista, generowanie z własnej oferty, brak dostępu po wpisaniu ID cudzej umowy.
2. Manager: zatwierdzenie i pobranie, wygląd okna potwierdzenia.
3. Menu i lista na mniejszym ekranie.
4. Wyszukiwanie po prawdziwych numerach ofert i nazwach klientów.
