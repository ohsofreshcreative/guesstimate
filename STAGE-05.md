# STAGE-05 — Polish Ofert

**Status: DONE** (2026-11-02, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–04)

Cztery zmiany wyglądu oferty, wszystkie jako opcje w edytorze. Bez ich włączenia oferta wygląda dokładnie tak samo jak dotąd.

| Pozycja | Wynik |
|---|---|
| Migracje | **brak** |
| `PublicOffer::SCHEMA`, `Pages::blank` | bez zmian |
| Build | `yarn build` wykonany lokalnie, OK; **bez commitu** |
| Testy PHP | `composer test`: 165 OK (506 asercji; przed etapem 160) |
| Testy JS | `node --test 'tests/js/*.test.mjs'`: 12 OK |
| Test UI etapu | **86/86 OK** (`tests/ui/document-polish.mjs`), headless Chromium, kopia bazy |
| Test UI etapu 04 | 75/75 OK na świeżej kopii |
| Snapshoty | 6/6 OK na kopii bazy |
| Render legacy | 45/45 pozycji identycznych ze wzorcem; przed testami UI także oferta 5 (49/49) |

## Kryteria odbioru

| Kryterium | Jak sprawdzono | Wynik |
|---|---|---|
| Cztery layouty | `text_image`, `image_text`, `full`, `tiles` | OK |
| Wszystkie 8 stron | 17 kombinacji strona/układ: brak wyjścia poza stronę, proporcje A4, zdjęcie zgodne z układem | OK |
| Zmiany opt-in | domyślne wartości nie są zapisywane; „0” wraca do szablonu | OK |
| Stare oferty nietknięte | render 45/45 identyczny; żaden opublikowany snapshot ani inna oferta nie dostają klucza `polish` | OK |

## Co doszło

Nowa sekcja „Wygląd strony” w panelu edytora.

| Zadanie | Opcja | Strona |
|---|---|---|
| Tytuły Realizacji | Domyślna, Większa, Duża | Realizacje |
| Podniesienie nagłówka okładki | suwak 0–100 (0 = środek, 100 = tuż pod logo) | Okładka |
| Boczne napisy | „Ukryj boczny napis”: usuwa go z dokumentu | Wycena, Realizacje |
| Proporcje grafik | 16:9 (domyślne), 4:3, 1:1 | Realizacje |
| Dopasowanie | „Wypełnij ramkę” lub „Pokaż całą grafikę” | Realizacje i zdjęcie strony |
| Kadr, punkt skupienia | suwaki osobno dla każdej realizacji | Realizacje |
| Kolejność grafik | przyciski ↑ ↓ | Realizacje |

Kadr zdjęcia strony istniał od etapu 02 i nie wymagał zmian. Plik grafiki nigdy się nie zmienia.

## Decyzje

- **Domyślnie nic się nie zmienia, także w nowych ofertach.** Boczny napis trzeba ukryć ręcznie na każdej stronie. Tak odczytano „jako opcjonalne elementy” i „zmiany opt-in”.
- **Opcje zapisuje `pages.<klucz>.polish`** (`Document\PagePolish`). Klucz trafia do snapshotu tylko wtedy, gdy jest ustawiony.
- **W snapshocie kadr należy do realizacji** (`references.items[n].image_crop`), więc przy zmianie kolejności idzie za grafiką.
- **Podniesienie dzieli tylko wolne miejsce** (`flex-grow`), zamiast przesuwać o stałą wartość. Blok nie wejdzie na logo i nie wydłuży strony, bez względu na długość tekstu.
- **Wycofana proporcja 3:4.** Test wykazał, że grafika zajmuje wtedy ponad połowę A4 i wszystkie realizacje spadają na stronę „cd.”, a pierwsza zostaje pusta.
- **Usunięcie bocznego napisu zdejmuje też zarezerwowany na niego margines**, więc treść może zająć tę przestrzeń na stronach Wycena i Realizacje.

## Pliki

- Nowe:
  - `app/Guesstimate/Document/PagePolish.php`
  - `resources/views/guesstimate/editor/partials/polish.blade.php`
  - `tests/Unit/Document/PagePolishTest.php`
  - `tests/ui/document-polish.mjs`, `tests/ui/polish-prepare.php`
  - `STAGE-05.md`
- Zmienione:
  - `app/Guesstimate/Document/DocumentBuilder.php`, `DocumentEditor.php`, `DocumentForm.php`
  - `resources/views/guesstimate/public/pages/cover.blade.php`, `pricing.blade.php`, `references.blade.php`
  - `resources/views/guesstimate/public/partials/pages.blade.php`
  - `resources/views/guesstimate/editor/partials/page.blade.php`
  - `resources/js/guesstimate/document-editor.js`, `resources/css/offer.css`
  - `AGENTS.md`
  - `public/build/**`: wynik `yarn build`

## Testy

- **`document-polish.mjs`:** macierz stron i układów, każda opcja w edytorze, zapis, odświeżenie, widok klienta na arkuszu i na telefonie, brak błędów JS. Obraz kontrolny z 21 widokami obejrzany ręcznie.
- **Snapshoty:** opcje, kolejność i kadr trafiają do snapshotu; strona bez opcji nie ma klucza; 6 pozostałych ofert i wszystkie publikacje nie mają klucza `polish`.
- **Render legacy:** bezpośrednio po zmianie kodu 49/49 identycznych, także oferta 5. Po testach UI porównano 45 pozycji bez oferty 5, którą testy zmieniły celowo.
- Różnica w ofercie 3 podczas testów wynikała z podpięcia grafik do realizacji w przygotowaniu testu. Po cofnięciu tego zmiana znikła.
- 4 niepowodzenia `document-layout.mjs` po przebiegu testu etapu wynikały z oferty zmienionej przez ten test. Na świeżej kopii: 75/75. **Te dwa testy UI wymagają osobnych kopii bazy.**
- Po testach prywatna baza jest zatrzymana, a kopia usunięta. Realny katalog uploads ma nadal 39 plików.

**Konflikt instrukcji:** jak w etapie 04, `CLAUDE.md` i `AGENTS.md` zabraniają przeglądarki do weryfikacji, a etap i Christie zlecają testy UI w trybie headless. Przyjęto polecenie etapu. Dotyczy to też `yarn build`.

## Ograniczenia

- **Lokalne realizacje nie mają grafik.** Test podpiął je tylko na kopii, a realizacja 3 została bez grafiki, co sprawdza wariant zastępczy. Kadr i proporcje trzeba zobaczyć na prawdziwych grafikach.
- Tylko Chromium, tylko mysz, administrator, wtyczki wyłączone. Test nie dotyczy wersji zamrożonej: panel jest wyłączony przez istniejący `fieldset`, ale bez testu UI.
- **Opcje nie są objęte cofaniem Ctrl+Z** — historia z etapu 04 dotyczy tylko układu pól.
- **Podniesienie działa tylko na arkuszu A4.** Telefon zachowuje układ płynny. Nagłówek ustawiony ręcznie w „Układ pól” nie reaguje na suwak.
- Test miał krótkie opisy realizacji. Przy proporcji 1:1, dużych tytułach i długich opisach kafelek może spaść na stronę „cd.”.
- „Pokaż całe zdjęcie” zostawia puste pasy w wąskiej kolumnie 42%, jeśli zdjęcie jest poziome.
- Zmiana wielkości tytułów nie wpływa na istniejący pomiar dopasowania, bo ten dotyczy tylko strony „Sytuacja” i pól układu.
- Boczny napis „W kooperacji siła” jest stałym tekstem w szablonie. Tego etapu nie dotyczy nagłówek strony o tej samej treści.

## Rollback

Cofnięcie kodu i `public/build`. Struktura bazy się nie zmienia. Klucz `polish` stary kod zignoruje, więc strony wrócą do wyglądu z szablonu. Zmieniona kolejność realizacji zostanie, bo jest zapisana w istniejącym `reference_ids`.

## Wymagane od użytkownika

- Commit razem z `public/build` (nic nie zostało zacommitowane).
- Decyzja, czy nowe oferty mają domyślnie ukrywać boczne napisy; dziś są domyślnie widoczne.
- Decyzje 1 i 2 nadal otwarte.

## Do zweryfikowania ręcznie

Po uruchomieniu strony w Local:

1. Realizacje z prawdziwymi grafikami: wszystkie proporcje, kadr, „Pokaż całą grafikę”.
2. Czy „Większa” i „Duża” to właściwe wielkości tytułów.
3. Okładka z długim nagłówkiem i podniesieniem 100, w obu układach.
4. Safari i Firefox: suwaki i podgląd.
5. Konto pracownika i wersja zamrożona: sekcja „Wygląd strony” nieaktywna.
