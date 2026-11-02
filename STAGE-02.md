# STAGE-02 — Biblioteka Ui

**Status: DONE z ograniczeniem** (2026-11-01, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–01). Interakcji w
przeglądarce nie sprawdzono automatycznie — patrz „Do zweryfikowania ręcznie”.

Moduł „Biblioteka” w nawigacji (`/app/biblioteka`): przesyłanie wielu plików, siatka i lista, wyszukiwanie, filtry, podgląd z opisem
i miejscami użycia, wybór w edytorze, niedestrukcyjne kadrowanie, kontrola MIME i limitów.

| Pozycja | Wynik |
|---|---|
| Migracje | **brak** |
| Role | `Roles::VERSION` = 4, nowa capability `gst_manage_media` (addytywnie) |
| Build | `yarn build` wykonany lokalnie, OK; **bez commitu** |
| Testy jednostkowe | `composer test`: 144 OK (426 asercji; przed etapem 137) |
| Test integracyjny | 49/49 OK na WordPressie i prywatnej kopii bazy z backupu |
| Upload multipart | 9/9 przypadków zgodnych z oczekiwaniem (`php-cgi`, bez serwera) |
| Test w przeglądarce | **pominięty** |

**Kryterium „zasób w dwóch ofertach bez ponownego uploadu”: spełnione.** Ta sama grafika (ID 11) w ofertach OSF/2026/0003 i 0005:
ten sam plik i adres, inny kadr na ofertę, liczba załączników i plików bez zmian, `MediaUsage` pokazuje obie oferty.

**Kryterium „poprawne uprawnienia”: spełnione** na poziomie tras i kontrolera, tabela niżej.

## Uprawnienia

| Działanie | Pracownik | Manager, administrator | Inni |
|---|---|---|---|
| Moduł, podgląd, wybór w edytorze | tak | tak | 403 |
| Przesyłanie | tak (`upload_files`) | tak | 403 |
| Opis, usuwanie, przywracanie | tylko własne pliki | wszystkie | 403 |
| Foldery | nie (403) | tak | 403 |

Grafiki są wspólne dla wszystkich tworzących oferty. To założenie z decyzji 2, nadal **niepotwierdzone**.

## Kadrowanie

`document.pages.<klucz>.image_crop = {x, y, zoom}` (x, y 0–100, zoom 1–4), klasa `Document\ImageCrop`. Plik się nie zmienia, a kadr
należy do strony oferty, więc każda oferta ma własny. Domyślny kadr to `null`. Klucz trafia do snapshotu **tylko gdy jest ustawiony**
(ryzyko R1), `PublicOffer::SCHEMA` bez zmian, `Pages::blank` bez zmian. Zmiana lub usunięcie zdjęcia kasuje kadr.
Render: `object-position` + `transform` na `img` w `.osf-media`.

## Kontrola plików

JPG, PNG, WebP; rozmiar do `min(15 MB, limit serwera)`; rozdzielczość do 50 Mpx (nowy limit `Media::MAX_PIXELS`). Sprawdzenie w przeglądarce
jest tylko wygodą: o przyjęciu pliku decyduje serwer, po zawartości. Pliki idą kolejno, po jednym na żądanie, więc błąd pliku nie
przerywa pozostałych.

## Pliki

- Nowe:
  - `app/Guesstimate/Media/MediaPolicy.php`
  - `app/Guesstimate/Document/ImageCrop.php`
  - `app/Guesstimate/Http/Controllers/MediaLibraryController.php`
  - `resources/views/guesstimate/library/index.blade.php`
  - `resources/js/guesstimate/media-library.js`
  - `tests/Unit/Media/MediaUiTest.php`
  - `STAGE-02.md`
- Zmienione:
  - `app/Guesstimate/Auth/Capabilities.php`, `Roles.php`: `gst_manage_media`, wersja ról 4.
  - `app/Guesstimate/Support/Modules.php`, `routes/app.php`: moduł i trasy.
  - `app/Guesstimate/Media/MediaLibrary.php`: filtry z adresu, drzewo folderów, tagi, zmiana nazwy i usuwanie folderu.
  - `app/Guesstimate/Document/Media.php`: `maxBytes()`, `MAX_PIXELS`, znacznik duplikatu, dane listy (`mime`, `date`, `alt`).
  - `app/Guesstimate/Document/DocumentEditor.php`, `DocumentForm.php`, `DocumentBuilder.php`: `image_crop`, lista folderów.
  - `app/Guesstimate/Http/Controllers/DocumentController.php`: filtry w wyborze; archiwum nigdy nie jest pokazywane.
  - `resources/js/guesstimate.js`, `resources/js/guesstimate/document-editor.js`: rejestracja, filtr folderu, kadr.
  - `resources/views/guesstimate/editor/partials/page.blade.php`, `picker.blade.php`, `public/partials/pages.blade.php`,
    `components/gst/icon.blade.php`.
  - `tests/Unit/Support/NavigationTest.php`, `AGENTS.md`.
  - `public/build/**`: wynik `yarn build`.

## Testy

- `composer test`: 144 testy OK. Nowe: polityka, role, filtry, drzewo, kadr, rola kadru przy zmianie zdjęcia.
- Integracja (`wp eval-file`, port prywatny, zablokowane HTTP i cron, bez wtyczek, `get_field` podstawiony pustą funkcją, uploady poza
  projektem): role, 10 tras z middleware, własne i cudze pliki, walidacja, foldery, dwie oferty, render kadru w HTML, widok biblioteki,
  edytor, **MD5 snapshotów i kwot bez zmian**.
- Multipart przez `php-cgi` na kopii bazy:

| Plik | Użytkownik | Wynik |
|---|---|---|
| JPG | admin | 200, załącznik 23, rozmiary pochodne, folder, SHA-256 |
| ten sam JPG | admin | 200, `duplicate: true`, nadal 23 |
| PNG | pracownik | 200, załącznik 24 |
| tekst jako `.jpg` | admin | 422 |
| PHP jako `image/jpeg` | admin | 422 |
| PNG 100 Mpx | admin | 422 |
| SVG | admin | 422 |
| PNG | subskrybent | 403 |
| JPG | niezalogowany | 403 |

- Publikacje: oferty 0004 i 0008 są oznaczone jako nieaktualne, ale **nie z powodu tego etapu**. Nie mają klucza `image_crop`, a różnice
  to starszy format snapshotu, data okładki i dane kontaktowe. Porównania z flagami sprzed etapu nie wykonano, bo `baseline-data.txt`
  ich nie zawiera. Oferta 0010 pozostaje aktualna, a klucz kadru pojawia się tylko w ofercie, w której test go ustawił.
- Po testach prywatna baza jest zatrzymana, a kopia usunięta. Realny katalog uploads ma nadal 39 plików.

**Pominięte:** test w przeglądarce. W projekcie nie ma Playwrighta, strona w Local jest wyłączona, a instrukcje zabraniają uruchamiania
serwera deweloperskiego. Nie sprawdzono więc działania `media-library.js`, kolejki, przeciągania plików, suwaków ani wyglądu
kadru. Sprawdzono składnię JS, build i render Blade po stronie serwera.

## Ograniczenia

- **Konflikt instrukcji:** `AGENTS.md` mówi, że `yarn build` uruchamia użytkownik, a dokument etapu zleca build po zmianie assetów.
  Przyjęto dokument etapu: build wykonany lokalnie, bez commitu.
- Lista nie pokazuje liczby użyć, tylko podgląd pojedynczej grafiki. Liczenie dla całej siatki wymagałoby zapytania zbiorczego.
- Przesłanie duplikatu **przywraca grafikę z archiwum** (zachowanie z etapu 01), także cudzą, bez sprawdzania `MediaPolicy`. Skutek jest
  niewielki, bo plik i tak jest wspólny, ale to wyjątek od tabeli uprawnień. Duplikat nie zmienia też folderu istniejącej grafiki.
- Kolejne uploady duplikatu nie wykrywają plików dodanych przed etapem 01 (brak SHA-256).
- Limit serwera `post_max_size` działa przed PHP: za duży plik dostanie ogólny błąd serwera, jeśli ominie sprawdzenie w przeglądarce.
- Zmiana nazwy i usuwanie folderu używają natywnych `prompt`/`confirm`.

## Rollback

Cofnięcie kodu i `public/build`. Struktura bazy się nie zmienia. `gst_manage_media` zostanie w rolach, dopóki nie obniży się opcji wersji
ról (`syncIfOutdated` działa tylko w górę), ale bez kodu nie ma skutków. Klucz `image_crop` w dokumentach wersji stary kod zignoruje.

## Wymagane od użytkownika

- Commit razem z `public/build` (nic nie zostało zacommitowane).
- Decyzja 2: czy grafiki mają być globalne.

## Do zweryfikowania ręcznie

Po uruchomieniu strony w Local:

1. Biblioteka: przesłanie kilku plików naraz, w tym jednego złego; przeciąganie; siatka i lista; „Pokaż więcej”.
2. Podgląd: zapis opisu, tagi, folder, usunięcie nieużywanej i archiwizacja używanej grafiki.
3. Edytor: wybór z filtrem folderu, suwaki kadru na żywo, „Przywróć pełny kadr”.
4. Kadr po publikacji: ten sam wygląd na arkuszu i w układzie mobilnym.
5. Konto pracownika: brak formularza folderów, brak edycji cudzych plików.
