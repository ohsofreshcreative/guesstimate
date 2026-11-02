# STAGE-01 — Biblioteka Model

**Status: DONE** (2026-11-01, baza: commit `47855e2` + niezacommitowane wyniki etapu 00)

Model biblioteki grafik na bibliotece mediów WordPressa: foldery, tagi, opis, autorstwo i licencja, relacje z ofertami,
archiwum, wykrywanie duplikatów. **Bez migracji, bez kopiowania plików, bez zmian w snapshotach i kwotach.** Interfejs i trasy
to etap 02.

| Pozycja | Wynik |
|---|---|
| Migracje | **brak** — roboczy `create_gst_media_assets` ze STAGE-00 okazał się niepotrzebny |
| Build | nie dotyczy (bez zmian w assetach) |
| Testy jednostkowe | `composer test`: 137 OK (przed etapem 130) |
| Test integracyjny | 33/33 OK na WordPressie i prywatnej kopii bazy z backupu |

## Model

| Dane | Gdzie | Uwagi |
|---|---|---|
| Plik i rozmiary | załącznik WP | istnieje raz; oferty wskazują go po ID |
| Foldery | taksonomia `gst_media_folder` (hierarchiczna) | jeden folder na grafikę; filtr obejmuje podfoldery; to także „kategorie” |
| Tagi | taksonomia `gst_media_tag` | do 20, filtr wielu tagów = AND |
| Nazwa, alt | `post_title`, `_wp_attachment_image_alt` | natywne pola WP |
| Autor, licencja, źródło | meta `_gst_media_author`, `_gst_media_license`, `_gst_media_source_url` | opcjonalne; licencje z listy `MediaAttributes::LICENSES` |
| Kto przesłał | `post_author` | natywne |
| Duplikaty | meta `_gst_media_sha256` | ten sam plik przesłany ponownie wskazuje istniejący załącznik |
| Archiwum | meta `_gst_media_archived_at` | ukrywa w wyborze, plik zostaje |
| Relacje | `MediaUsage::forAttachment()` | liczone z danych źródłowych, bez tabeli do synchronizacji |

Taksonomie są niepubliczne: bez REST, adresów i ekranów w wp-admin.

**Usuwanie.** `MediaLibrary::remove()`: grafika używana → archiwum (znika z wyboru, plik zostaje, render bez zmian); nieużywana →
trwałe usunięcie z plikami. Filtr `pre_delete_attachment` obejmuje teraz **wszystkie** użycia, także przy usuwaniu z wp-admin i REST:
publikacje (`gst_media_refs` + odczyt snapshotów), wersje ofert (także w koszu), tła, realizacje, logo. Przy błędzie zapytania plik zostaje.
Zamyka to ryzyko R5 ze STAGE-00. Odczyt snapshotu obok referencji chroni też ewentualne starsze publikacje bez kompletnych referencji.

## Pliki

- Nowe: `app/Guesstimate/Media/MediaLibrary.php`, `MediaAttributes.php`, `MediaUsage.php`, `tests/Unit/Media/MediaLibraryTest.php`, `STAGE-01.md`.
- Zmienione:
  - `app/Guesstimate/Document/Media.php`: duplikaty przy uploadzie, ukrywanie archiwum, filtry w `browse()`.
  - `app/Guesstimate/Document/MediaRefs.php`: strażnik usuwania na `MediaUsage`, opis w bibliotece mediów.
  - `app/Guesstimate/Http/Controllers/DocumentLibraryController.php`: usuwanie tła i naprawa `usage()`.
  - `app/Providers/GuesstimateServiceProvider.php`: rejestracja taksonomii.
  - `AGENTS.md`: opis modułu.

**Naprawiony błąd.** `DocumentLibraryController::usage()` szukał `"background_id":5` przez REGEXP, a MySQL zapisuje JSON ze spacją po
dwukropku. Ekran teł zawsze pokazywał 0 użyć (potwierdzone w MySQL 8.4). Teraz `JSON_CONTAINS`, odporne także na inny silnik. Osobno
`destroyBackground` sprawdzał tylko publikacje; przy szerszej ochronie zgłosiłby usunięcie pliku, który został. Teraz podaje, kto go używa.

## Testy

- `composer test`: 137 testów, wszystkie OK (7 nowych: walidacja opisu, filtry, ocena użycia, opis użycia).
- Integracja: `wp eval-file` na prawdziwym WordPressie i kopii bazy z backupu (port prywatny, zablokowane HTTP i cron, bez wtyczek): 33/33.
  Objęte: taksonomie; strażnik dla wszystkich 6 załączników; ochrona ze snapshotu po usunięciu referencji; blokada przez wersję oferty;
  archiwizacja z zachowaniem pliku; ukrywanie w `browse()`/`recent()`; zapis opisu (cudzysłowy, apostrofy); foldery, podfoldery, tagi,
  liczniki; czyszczenie opisu; duplikaty; usunięcie nieużywanej; **MD5 snapshotów, kwot i wyników bez zmian**.
  Test znalazł jeden błąd w nowym kodzie (`get_the_title()` zmieniał cudzysłowy) — poprawiony.
- `usage()` po zmianie nie był uruchomiony przez kontroler; identyczne wyrażenia wykonano w teście, a trafienie potwierdzono w SQL.
- Pominięte: UI (brak interfejsu w tym etapie).

## Ograniczenia

- **Brak warstwy uprawnień:** `MediaLibrary::remove()`, `update()` i `createFolder()` nie sprawdzają uprawnień. Etap 02 musi to zrobić
  w kontrolerze (trasy jeszcze nie istnieją, więc dziś nic ich nie wywołuje).
- **Duplikaty działają tylko dla plików przesłanych od teraz** przez `Media::upload()`. Istniejące załączniki i uploady z wp-admin nie mają
  skrótu. Sam upload nie był testowany integracyjnie (`is_uploaded_file` nie działa w CLI); przetestowano wyszukiwanie duplikatu.
- `browse()` nie zwraca jeszcze opisu ani liczby użyć: to potrzeba etapu 02; liczenie użyć dla całej siatki trzeba zrobić zbiorczo.
- Zmiana alt w bibliotece pokaże „wersja robocza różni się od publikacji” przy ofertach z tą grafiką. To poprawne: publikacja ma własną
  kopię alt i nie zmienia się aż do ponownej publikacji.
- Grafiki globalne to założenie z decyzji 2, nadal **niepotwierdzone**.
- Silnik bazy produkcji nieznany. `JSON_CONTAINS`/`JSON_EXTRACT` działają w MySQL 8 i MariaDB 10.2+, ale przetestowano tylko MySQL 8.4.

## Rollback

Cofnięcie kodu wystarcza: nowe taksonomie i meta to dane dodatkowe, ignorowane przez stary kod. Zmiany struktury bazy nie ma.
Po cofnięciu ochrona wraca do samych publikacji, a zarchiwizowane grafiki znów pojawią się w wyborze.

## Do zweryfikowania ręcznie

- Po uruchomieniu strony w Local: ekran Ustawienia → Tła powinien pokazać prawdziwe liczby użyć.
- W bibliotece mediów WP: próba usunięcia grafiki użytej w wersji roboczej powinna się nie udać.
