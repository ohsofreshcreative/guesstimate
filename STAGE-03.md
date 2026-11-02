# STAGE-03 — Layout Engine

**Status: DONE z ograniczeniem** (2026-11-01, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–02). Część JS nie była
uruchomiona w przeglądarce — patrz „Ograniczenia”.

Wersjonowany schemat układu pól strony dokumentu, zapis, snapshot, render i fallback dla dokumentów bez układu. **Bez interfejsu:**
przeciąganie to etap 04, więc dziś żaden ekran nie tworzy układu.

| Pozycja | Wynik |
|---|---|
| Migracje | **brak** (układ w istniejącej kolumnie `document`) |
| `PublicOffer::SCHEMA` | bez zmian (2) |
| Build | `yarn build` wykonany lokalnie, OK; **bez commitu** |
| Testy jednostkowe | `composer test`: 155 OK (452 asercje; przed etapem 144) |
| Render przed i po | 49/49 pozycji identycznych bajt w bajt |
| Test układu | 17/17 OK na kopii bazy, bez zapisu do bazy |
| Test w przeglądarce | **pominięty** |

## Schemat

`document.pages.<klucz>.fields` (`Document\FieldLayout`, `SCHEMA = 1`):

```json
{
  "schema": 1,
  "items": [{
    "id": "heading", "page_id": "situation", "breakpoint": "sheet",
    "x": 5, "y": 8, "width": 50, "height": 10, "z_index": 2,
    "style": {"align": "left"},
    "constraints": {"locked": false, "min_width": 5, "min_height": 2}
  }]
}
```

| Pole | Znaczenie |
|---|---|
| `id` | istniejący element strony (wartość `data-edit`), lista `FieldLayout::FIELDS` |
| `page_id` | klucz strony; wpis z inną stroną jest pomijany |
| `x`, `width` | % szerokości strony A4 poziomo |
| `y`, `height` | % wysokości strony; `height` to wysokość **minimalna** |
| `z_index` | 1–99 |
| `style` | tylko `align`: `left`, `center`, `right` |
| `breakpoint` | tylko `sheet`, patrz „Ograniczenia” |
| `constraints` | `locked` (do użycia w etapie 04), minimalne rozmiary |

**Dostępne pola:** okładka `cover_lead`, `cover_heading`; sytuacja `heading`, `situation`; pozostałe strony `heading`. Tabele, listy i wycena
zostają w szablonie, bo dzieli je paginacja „cd.”.

**Zasady:**
- Pole przechowuje tylko pozycję. Treść zostaje w swoich polach, więc układ nie może jej zgubić ani powielić.
- Serwer dosuwa pole do strony: `x + width ≤ 100`, `y + height ≤ 100`.
- Pola, których nie ma w układzie, zostają w miejscu z szablonu; układ może być częściowy.
- Nieznana wersja schematu albo brak poprawnych pól oznacza szablon strony (fallback).
- Układ płynny (telefon, tablet) ignoruje pozycje, więc treść płynie w kolejności szablonu.

## Render

Szablony stron pozostały nietknięte. `partials/pages.blade.php` przekazuje im `$edit` rozszerzone o pozycję: bez układu wynik jest pustym
tekstem, stąd identyczny HTML. Strona z układem dostaje `data-free`, pola `data-field` i zmienne CSS `--field-*`, użyte w `offer.css`
tylko pod `:root.osf-sheet`.

**Nadmiar treści** (`offer.js`): pole absolutne nie rozpycha strony, więc pomiar uwzględnia dolną krawędź najniższego pola. Działają na nim
istniejące mechanizmy: dopasowanie typografii, strony „cd.”. Co nadal się nie mieści, wydłuża stronę i dostaje `data-overflow`,
czyli nic nie jest ucinane. Na stronie „Sytuacja” blokuje to publikację tak jak dotąd.

## Kryteria odbioru

Metoda: render wszystkich danych na kopii bazy **przed zmianami** i po nich, porównanie sum MD5.

| Kryterium | Dowód | Wynik |
|---|---|---|
| Dokumenty legacy renderują identycznie | 12 publikacji × 3 tryby (schema 1 i 2) + 6 bieżących wersji × 2 tryby | identyczne bajt w bajt |
| Snapshoty bez zmian | MD5 każdego snapshotu; kod nie zapisuje do `gst_publications` | identyczne |
| Publikacje nie stają się nieaktualne | `outdated()` dla każdej oferty | te same wartości |
| Pomiary dopasowania zachowują ważność | `fitHash` dla każdej oferty | te same wartości |
| Brak utraty treści | pełna treść w HTML we wszystkich trybach; usunięcie układu przywraca odcisk | OK |

Porównanie obejmuje kod etapów 00–02, a nie commit `47855e2`. Zgodność z etapem bazowym pilnują sumy i testy poprzednich etapów.

## Pliki

- Nowe: `app/Guesstimate/Document/FieldLayout.php`, `tests/Unit/Document/FieldLayoutTest.php`, `STAGE-03.md`.
- Zmienione:
  - `app/Guesstimate/Document/DocumentSettings.php`: `fitHash` uwzględnia układ, tylko gdy istnieje.
  - `app/Guesstimate/Document/DocumentForm.php`, `DocumentEditor.php`, `DocumentBuilder.php`, `DocumentView.php`: zapis, stan, snapshot, odczyt.
  - `app/Guesstimate/Document/ImageCrop.php`: `css()` publiczna, do wspólnego formatowania liczb.
  - `resources/views/guesstimate/public/partials/pages.blade.php`, `resources/css/offer.css`, `resources/js/offer.js`.
  - `AGENTS.md`.
  - `public/build/**`: wynik `yarn build`.

## Testy

- `composer test`: 155 OK. 11 nowych: pełny schemat, fallback, trzymanie się strony, ograniczenia, odrzucanie błędnych wpisów, style,
  atrybuty, formularz, odczyt snapshotów.
- Test układu na kopii bazy (17/17, wyłącznie w pamięci): stan edytora, zapis, dosunięcie pola, snapshot, render w 3 trybach, odcisk.
- Test ujawnił problem wcześniej: `DocumentTest` pinuje kształt strony i odrzucił dokładany `fields => null`. Poprawiono kod, a testu nie zmieniano.
- Po testach prywatna baza jest zatrzymana, a kopia usunięta.
- **Pominięte:** test w przeglądarce. Brak Playwrighta w projekcie, strona w Local wyłączona, instrukcje zabraniają dev serwera.

## Ograniczenia

- **`offer.js` i CSS układu nie były uruchomione w przeglądarce.** Sprawdzono składnię, build i wygenerowany HTML, ale nie faktyczne
  pozycje, pomiar `fieldsBottom`, wydłużanie strony ani strony „cd.” z polami. Bez układu kod jest pomijany i nic z tego nie działa na istniejących
  dokumentach, więc ryzyko dotyczy dopiero etapu 04. **Etap 04 powinien to sprawdzić jako pierwsze.**
- **Tylko breakpoint `sheet`:** decyzja 1 jest niepotwierdzona. Rekomendacja: na telefonie układ automatyczny, co jest obecnym zachowaniem.
  Wpisy z innym breakpointem są dziś pomijane; dodanie drugiego nie wymaga migracji, ale wymaga zmiany `BREAKPOINTS` i `attributes()`.
- **Blokada publikacji działa tylko dla strony „Sytuacja”.** Na innych stronach przepełnione pole wydłuża stronę i pokazuje ostrzeżenie
  w edytorze, ale nie blokuje publikacji (jak za długi element dziś).
- Lista pól jest celowo wąska. Rozszerzenie na bloki rozwiązania czy kroki wymaga decyzji, jak mają działać z paginacją „cd.”.
- Układ nie reaguje na zmianę układu strony (np. tekst + zdjęcie → pełna szerokość). Pole może przykryć zdjęcie; pozostaje to do obsługi w etapie 04.
- **Pole nieznanej przyszłej wersji schematu** jest traktowane jak brak układu, a zapis w edytorze by je nadpisał. Dziś nie dotyczy.
- **Konflikt instrukcji:** jak w etapie 02, `yarn build` wykonany lokalnie zgodnie z dokumentem etapu.

## Rollback

Cofnięcie kodu i `public/build`. Struktura bazy się nie zmienia, a klucz `fields` stary kod zignoruje. Dziś nie ma go w żadnym dokumencie.

## Wymagane od użytkownika

- Commit razem z `public/build` (nic nie zostało zacommitowane).
- Decyzja 1: tekst na telefonie. Etap 04 przyjmie rekomendację, jeśli nie zapadnie inna.

## Do zweryfikowania ręcznie

Po uruchomieniu strony w Local:

1. Istniejące oferty i publikacje: wygląd bez zmian (publiczna, edytor, miniatury, telefon).
2. Sprawdzenie układu wymaga etapu 04 albo ręcznego wpisania `fields` do wersji roboczej na kopii bazy.
