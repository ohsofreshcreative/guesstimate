# STAGE-09 — Zmienne Generator

**Status: DONE** (2026-11-02, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–08)

Z oferty można wygenerować szkic umowy. Formularz pokazuje brakujące pola przed generowaniem, dane zamrażają się w wersji umowy, wartości można poprawić ręcznie, a nowe dane z oferty trafiają do umowy dopiero po świadomym odświeżeniu.

| Pozycja | Wynik |
|---|---|
| Migracje | **brak nowych**; moduł wymaga migracji z etapu 07 |
| `PublicOffer::SCHEMA`, `Pages::blank` | bez zmian |
| Build | `yarn build` wykonany lokalnie, OK; **bez commitu** |
| Testy PHP | `composer test`: 221 OK (657 asercji; przed etapem 210) |
| Testy JS | `node --test 'tests/js/*.test.mjs'`: 23 OK |
| Test UI etapu | **27/27 OK** (`tests/ui/contract-generator.mjs`), headless Chromium, kopia bazy |
| Integracja | 31/31 OK na kopii bazy |
| Regresja | edytor szablonów 66/66, cykl umowy 67/67, umowa z szablonu 25/25 |
| Render legacy | **48/48** pozycji identycznych ze wzorcem |

## Kryteria odbioru

| Kryterium | Jak sprawdzono | Wynik |
|---|---|---|
| Tokeny escapowane | HTML, `<img onerror>`, cudzysłowy i token we wpisanej wartości wyświetlają się dosłownie, kod się nie wykonuje | OK |
| Brak pustych „undefined” | pusta wartość, pusta lista i nieznany token dają `[brak: Etykieta]`; formularz i podgląd bez `undefined`, `null`, `NaN` | OK |
| Ręczna edycja | wartość ręczna wygrywa z danymi, a wyczyszczenie pola do nich wraca | OK |
| Świadome odświeżanie | zmiana źródła nie zmienia umowy; panel pokazuje różnice, a przycisk je przyjmuje i zachowuje wartości ręczne | OK |
| Braki przed generowaniem | lista na ekranie przed wygenerowaniem | OK |
| Snapshot zamiast powiązania | zmiana klienta i wykonawcy nie dotyka umowy; zatwierdzona wersja bez zmian po nowej wersji | OK |

## Co powstało

- **Karta oferty:** sekcja „Umowy” i przycisk „Przygotuj umowę”.
- **Generowanie:** wybór zatwierdzonego szablonu, lista braków, formularz wartości.
- **Widok umowy:** strony A4 z danymi, panel braków, wartości, porównanie zmian i wersje.
- **Tokeny:**

| Grupa | Tokeny | Źródło |
|---|---|---|
| Klient | firma, NIP, kontakt, e-mail, telefon, WWW | klient |
| Klient | adres, reprezentant | **ręcznie**, patrz „Decyzje” |
| Oferta | numer, data, nazwa, rodzaj, wersja, opis, zakres UX, mapa, funkcjonalności, wariant, widełki | wersja oferty |
| Wykonawca | nazwa, adres, NIP, reprezentant | nowa sekcja ustawień „Dane wykonawcy” |
| Umowa i terminy | kwota umowy, data i miejsce zawarcia, rozpoczęcie, zakończenie | **ręcznie** |

## Decyzje

- **Kod nie rozstrzyga decyzji 4.** „Kwota umowy” jest wpisywana ręcznie, bez podpowiedzi. Tokeny kwot z oferty to wyłącznie widełki i tak się nazywają. Jeśli decyzja wskaże wybrany wariant, wystarczy dodać podpowiedź.
- **Treść umowy nie zmienia się przy generowaniu.** `body` zostaje kopią szablonu co do bajtu, a wartości leżą w `data_snapshot`. Render zmienia tylko tokeny i działa w jednym przebiegu. Skrót zatwierdzenia obejmuje i treść, i wartości.
- **Adres i reprezentant klienta bez nowych kolumn.** W `gst_clients` ich nie ma, a kontakt nie musi być osobą reprezentującą. Etap ich nie dodaje, bo wymagałoby to migracji i zmian w formularzu klienta oraz imporcie ClickUp. Skutek: przy każdej umowie wpisuje się je od nowa. Dodanie kolumn w przyszłości jest proste, jeśli będzie uciążliwe.
- **Brakujące pola blokują zatwierdzenie, nie szkic.** Szkic można przygotować przed zebraniem danych, a dokument pokazuje braki na czerwono.
- **Odświeżenie zachowuje wartości ręczne** i wskazuje je w porównaniu. Nowa wersja umowy też je przenosi, biorąc pozostałe dane aktualne.
- **Dziennik zapisuje nazwy pól i skróty,** nie wartości.
- **Bez przycisku zatwierdzenia:** obieg należy do etapu Workflow. Usługa ma już nową blokadę.
- **Etapy i płatności nie są tu obsługiwane:** to etap 10.

## Błędy znalezione przez testy

1. **Wyczyszczonego pola nie dało się przywrócić do danych z oferty.** Laravel zamienia pusty ciąg na `null`, a `clean()` go pomijał. Test jednostkowy i integracyjny przechodziły, bo podawały pusty tekst; wadę wykrył test UI. Naprawione, jest test z `null`.
2. Trzy inne porażki były błędami samych testów: `str_replace` nie trafiał w `ł` zapisane w JSON jako `ł` (co oznaczało, że test niczego nie podmieniał; dodano asercję, że podmiana zaszła), selektor łapał link „Przygotuj umowę”, a klient oferty testowej nie ma NIP-u.

## Pliki

- Nowe:
  - `app/Guesstimate/Contracts/ContractValues.php`
  - `app/Guesstimate/Http/Controllers/ContractController.php`
  - `resources/js/guesstimate/contract-sheets.js`
  - `resources/views/guesstimate/contracts/create.blade.php`, `show.blade.php`, `partials/fields.blade.php`
  - `resources/views/guesstimate/offers/partials/contracts.blade.php`
  - `resources/views/guesstimate/settings/partials/contractor.blade.php`
  - `tests/Unit/Contracts/ContractValuesTest.php`
  - `tests/ui/contract-generator.mjs`, `tests/ui/contract-prepare.php`
  - `STAGE-09.md`
- Zmienione:
  - `app/Guesstimate/Contracts/ContractService.php`, `ContractSnapshot.php` (schemat 2), `ContractVersion.php`, `TemplateVariables.php`
  - `app/Guesstimate/Http/Controllers/OfferController.php`, `SettingsController.php`
  - `app/Guesstimate/Http/Middleware/EnsureSchema.php`, `app/Guesstimate/Settings/Defaults.php`, `routes/app.php`
  - `resources/js/guesstimate.js`, `resources/js/guesstimate/contract-editor.js` (podział stron przeniesiony do wspólnego modułu)
  - `resources/css/guesstimate.css`
  - `resources/views/guesstimate/offers/show.blade.php`, `settings/index.blade.php`
  - `tests/Unit/Contracts/ContractHtmlTest.php`, `AGENTS.md`
  - `public/build/**`: wynik `yarn build`

Test `test_variable_catalog_has_no_amounts_before_decision_4` z etapu 08 zastąpiono świadomie: kwoty są już w katalogu, a nowy test pilnuje, że kwota umowy nie pochodzi z oferty.

## Testy

- **`contract-generator.mjs`:** dane wykonawcy, braki przed generowaniem, generowanie, podgląd, numery stron, atak przez wartość, edycja, zmiana źródła, konflikt dwóch kart, odświeżenie, czyszczenie pola, karta oferty, cudza umowa (403).
- **Integracja:** treść bajt w bajt, snapshot po zmianie klienta i wykonawcy, kwoty z kolumn wersji, blokady, skrót obejmujący wartości, nowa wersja, role, publikacje i kwoty ofert bez zmian.
- Wynik integracji 31/31 i regresja pochodzą sprzed jednowierszowej poprawki `clean()`. Po niej powtórzono testy jednostkowe, JS i test UI etapu.
- `document-layout`, `document-compat`, `document-polish` i `document-print` nie były powtórzone: etap nie zmienia edytora ani oferty publicznej. Render legacy przeszedł po zmianach widoków.
- Prywatna baza zatrzymana; `cgi-prepend.php` nie jest do wdrożenia.

**Konflikt instrukcji:** jak w poprzednich etapach, `CLAUDE.md` i `AGENTS.md` zabraniają przeglądarki do weryfikacji, a etap i Christie zlecają testy headless. Przyjęto polecenie etapu. Dotyczy to też `yarn build`.

## Ograniczenia

- **`SZABLON_UMOWY_NOTATKI.md` i `MODEL_ZMIENNY.md` nie zostały przekazane.** Katalog tokenów powstał z opisu etapu i może nie pokrywać prawdziwego wzoru. Brakujący token trzeba dodać w kodzie.
- **Kwota umowy to na razie zwykły tekst.** Nic jej nie sprawdza i nie porównuje z ofertą. Słownie też nie ma. Walidacja należy do etapu 10.
- **Daty i terminy to tekst,** bez kontroli formatu i kolejności.
- **Mapa i funkcjonalności są listą po przecinku w jednym wierszu,** a nie listą punktowaną. W długiej ofercie to długi akapit.
- **Wariant ma etykietę z aplikacji („Mid Risk”),** po angielsku. W umowie warto użyć pola ręcznego albo nie używać tego tokenu.
- **Tokeny działają tylko w widoku.** Eksport z etapu 11 musi przejść przez `ContractValues::render`, inaczej wyjdzie sam szablon.
- **Porównanie obejmuje tylko tokeny użyte w treści.** Zmiana innych danych oferty, także kwoty, gdy szablon jej nie używa, nie pokaże się.
- Odświeżenie, gdy oferta dostała innego klienta, przepina `client_id` umowy. Sprawdzono tylko zmianę danych tego samego klienta.
- Nie ma ekranu listy wszystkich umów: dostęp jest z karty oferty. Nie ma też UI edycji treści szkicu, choć usługa ją ma.
- Tylko Chromium, administrator i pracownik; konto managera nie było testowane w UI tego etapu.

## Rollback

Cofnięcie kodu i `public/build`. Struktura bazy się nie zmienia. Klucz `contractor` w `gst_settings` stary kod zignoruje. Umowy ze schematem 2 pozostaną czytelne, ale bez ekranu.

## Wymagane od użytkownika

- Backup i `wp acorn migrate` w Local (z etapu 07, nadal niewykonane).
- Wypełnienie sekcji Ustawienia → Dane wykonawcy.
- Commit razem z `public/build`.
- Prawdziwy wzór umowy i `SZABLON_UMOWY_NOTATKI.md`.
- **Decyzja 4 przed etapem Płatnosci,** a także 3, 5, 6 i 8.

## Do zweryfikowania ręcznie

Po migracji w Local:

1. Umowa z prawdziwego szablonu: czy tokeny pokrywają wzór.
2. Długa mapa strony w treści umowy.
3. Zmiana klienta oferty i odświeżenie.
4. Konto managera i pracownika z własną ofertą: pełna ścieżka.
5. Safari i Firefox: podgląd A4.
