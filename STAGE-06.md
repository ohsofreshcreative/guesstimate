# STAGE-06 — Kompatybilnosc

**Status: DONE** (2026-11-02, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–05)

Stary i nowy układ działają obok siebie. Oferta przechodzi na nowy układ wyłącznie po ręcznej akcji w jej edytorze, po obejrzeniu podglądu. Doszła walidacja zakresów i pełny wydruk A4.

| Pozycja | Wynik |
|---|---|
| Migracje bazy | **brak** |
| `PublicOffer::SCHEMA`, `Pages::blank` | bez zmian |
| Build | `yarn build` wykonany lokalnie, OK; **bez commitu** |
| Testy PHP | `composer test`: 169 OK (530 asercji; przed etapem 165) |
| Testy JS | `node --test 'tests/js/*.test.mjs'`: 13 OK |
| Test kompatybilności | **56/56 OK** (`tests/ui/document-compat.mjs`) |
| Test wydruku | **36/36 OK** (`tests/ui/document-print.mjs`), prawdziwe PDF-y |
| Blokady edycji | 10/10 OK |
| Izolacja danych | 6/6 OK na kopii bazy |
| Regresja UI | układ pól 75/75, dopracowanie stron 86/86 |
| Render legacy | **49/49** pozycji identycznych ze wzorcem, po ostatniej zmianie PHP i Blade |

## Kryteria odbioru

| Kryterium | Jak sprawdzono | Wynik |
|---|---|---|
| Istniejące wersje bez regresji | render 49/49 w 3 trybach; MD5 snapshotów bez zmian | OK |
| Publiczne URL bez regresji | 4 aktywne publikacje, także najstarszy format snapshotu: status 200 i wydruk | OK |
| Bez automatycznej migracji wszystkich | brak trasy, komendy i kodu po stronie serwera; inne 9 wersji bez układu i trybu | OK |

## Co doszło

Sekcja „Tryb układu” na górze panelu edytora.

- **Przełącznik:** „Nowy” pokazuje pola w zapisanych pozycjach. „Stary” pokazuje strony z szablonu.
  Stary tryb **nie usuwa** pozycji ani kopii pól, tylko je ignoruje, a powrót je przywraca. Do snapshotu nie trafiają wtedy żadne pola.
  Dotyczy całej wersji, zapis w `document.layout_mode`; brak klucza oznacza dotychczasowe działanie.
- **Migracja pojedynczej oferty:** „Przenieś tę ofertę do nowego układu” przypina pola dokładnie tam, gdzie są.
  Dotyczy tylko bieżącej wersji roboczej. Publiczny link zmienia się dopiero po ponownej publikacji.
- **Podgląd przed zapisem:** wynik widać od razu na wszystkich stronach, a autozapis i publikacja są wstrzymane.
  Do wyboru „Zapisz migrację” albo „Anuluj”. Główny przycisk „Zapisz” też akceptuje migrację. Po zapisie można ją cofnąć w historii układu.
- **Strony z nadmiarem treści:** zostają przy szablonie i są wymienione z nazwy, bo nie mają pozycji w obrębie A4.
- **Walidacja zakresów:** zapis zwraca 422 dla pola poza stroną, za małego, z nieliczbową pozycją, warstwą poza 1–99, nieznanym polem lub schematem, kadrem, podniesieniem i trybem spoza listy.
  Odczyt starych danych dalej tylko je dosuwa, więc istniejące dokumenty się otwierają.
- **Wydruk:** `@page` A4 poziomo, jedna strona dokumentu na kartkę, bez nawigacji, zaokrągleń i cieni.
  Na wąskim ekranie `beforeprint` przechodzi na arkusze, a `afterprint` wraca do układu płynnego.

## Błędy znalezione i naprawione

1. **Nagłówek wyjęty z przepływu przesuwał treść pod nim.** Dotyczyło to już pierwszego przeciągnięcia z etapu 04 na stronach: Rozwiązanie, Wycena, Usługi, Realizacje, Kolejne kroki, Kontakt. Teraz ukryty odpowiednik trzyma miejsce, w HTML tylko przy zapisanym układzie.
2. **Pomiar wysokości tekstu dłuższego niż arkusz dawał wartość poza zakresem.** Po dodaniu walidacji użytkownik utknąłby na błędzie, którego nie może naprawić. `createItem` przycina teraz pozycję do strony.
3. `measureAll` nie było wyeksportowane z modułu. Przycisk kończył się cichym błędem, a pierwsze zielone wyniki pozycji w teście nic nie znaczyły. Test wyłapuje już błędy z Alpine.

## Pliki

- Nowe:
  - `resources/views/guesstimate/editor/partials/layout-mode.blade.php`
  - `tests/ui/document-compat.mjs`, `tests/ui/document-print.mjs`
  - `STAGE-06.md`
- Zmienione:
  - `app/Guesstimate/Document/FieldLayout.php`, `ImageCrop.php`, `PagePolish.php`, `DocumentSettings.php`, `DocumentForm.php`, `DocumentBuilder.php`, `DocumentEditor.php`
  - `resources/js/offer.js`, `resources/js/guesstimate/document-editor.js`, `field-stage.js`, `field-layout.js`, `resources/css/offer.css`
  - `resources/views/guesstimate/editor/index.blade.php`, `editor/partials/fields.blade.php`, `public/partials/heading.blade.php`
  - `tests/Unit/Document/FieldLayoutTest.php`, `tests/js/field-layout.test.mjs`, `AGENTS.md`
  - `public/build/**`: wynik `yarn build`

## Testy

- **`document-compat.mjs`:** dla każdej z 8 stron pozycje przed migracją, w podglądzie, po anulowaniu, po zapisie i odświeżeniu oraz w starym trybie, z tolerancją 0,6%. Na serwerze nic się nie zmienia przed akceptacją. Także nadmiar treści, tryb po odświeżeniu i odrzucanie zakresów.
- **`document-print.mjs`:** 5 adresów (4 publikacje, oferta po migracji) z viewportu 1400 i 390 px. Liczba kartek = liczba stron dokumentu, format 842 × 595 pt, wydruk z telefonu identyczny jak z komputera. Wygląd sprawdzono na obrazie pierwszej kartki.
- **Blokady:** wersja zamrożona (`locked_at` ustawiony na kopii, żadna oferta go nie ma) i oferta anulowana: edycja i migracja wyłączone, serwer odrzuca zapis, MD5 dokumentów bez zmian. Stary model: 409, brak edytora.
- **Izolacja:** snapshot w nowym trybie ma pola, w starym nie; klucz trybu nie trafia do snapshotu; inne wersje i publikacje bez układu.
- Po testach prywatna baza jest zatrzymana, a kopia usunięta. Realny katalog uploads ma nadal 39 plików.

**Konflikt instrukcji:** jak w etapach 04 i 05, `CLAUDE.md` i `AGENTS.md` zabraniają przeglądarki do weryfikacji, a etap i Christie zlecają testy w trybie headless. Przyjęto polecenie etapu. Dotyczy to też `yarn build`.

## Ograniczenia

- **Wydruk sprawdzono w Chromium przez generator PDF.** Okno drukowania w Safari i Firefoksie nie było testowane.
- **Obrazy leniwe:** `beforeprint` przełącza je na ładowanie od razu, ale przeglądarka może nie zdążyć ich pobrać przed wydrukiem, gdy klient nie przewinął dokumentu. Test wczytywał je wcześniej.
- **Pozycje po migracji są stałe.** Dłuższy tekst wpisany później nie przesuwa pól, więc pola mogą na siebie nachodzić. Przepełnienie wykrywa blokada publikacji, nachodzenia nie.
- **Stary tryb dotyczy tylko pozycji.** Opcje z „Wygląd strony” (etap 05) działają w obu trybach.
- Oferty w starym modelu kalkulacji nie mają edytora. Przechodzą przez nową wersję (`LegacyConverter`), bez zmian w tym etapie.
- Cofnięcie migracji po zapisie: sprawdzono wpis w historii, samego cofnięcia nie wykonano w teście. Historia znika po zamknięciu karty; do starego wyglądu zawsze prowadzi przełącznik.
- `document-compat.mjs` wymaga świeżej kopii, a `document-polish.mjs` przygotowanej: testy UI nie mogą dzielić tej samej oferty bez jej zresetowania.
- Tylko konto administratora i wtyczki wyłączone. Testy jak w etapie 04: tylko Chromium i mysz.

## Rollback

Cofnięcie kodu i `public/build`. Struktura bazy się nie zmienia. Klucz `layout_mode` stary kod zignoruje, więc wersje w starym trybie pokażą zapisane pola; kod etapów 03–05 obsługuje je bez zmian.

## Wymagane od użytkownika

- Commit razem z `public/build` (nic nie zostało zacommitowane).
- Potwierdzenie autozapisu z etapu 04 i decyzja o bocznych napisach z etapu 05.
- Decyzje 1 i 2 nadal otwarte.

## Do zweryfikowania ręcznie

Po uruchomieniu strony w Local:

1. Migracja prawdziwej oferty: podgląd, „Anuluj”, ponownie, „Zapisz migrację”, cofnięcie.
2. Przełącznik trybu na ofercie z kopiami pól.
3. Wydruk publicznej oferty z Safari, Firefoksa i telefonu: Ctrl/Cmd+P, bez wcześniejszego przewijania.
4. Oferta z długą Wyceną lub Realizacjami: strony „cd.” po migracji i na wydruku.
5. Konto pracownika: sekcja „Tryb układu” przy cudzej ofercie.
