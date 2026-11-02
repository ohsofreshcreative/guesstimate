# STAGE-00 — Audyt

**Status: DONE** (2026-11-01, commit bazowy `47855e2`, drzewo robocze czyste przed etapem)

Audyt kodu, danych i procesu wdrożenia przed biblioteką obrazów, swobodnym układem stron i modułem umów.
Nic nie zmieniono w kodzie aplikacji, bazie ani produkcji. Żadna z 8 decyzji biznesowych nie blokuje etapu 01.

| Pozycja | Wynik |
|---|---|
| Pliki | nowe: `STAGE-00.md`, `tests/Unit/Baseline/RegressionBaselineTest.php` |
| Migracje | brak nowych; wszystkie 10 z repo wykonane w bazie Local |
| Build | nie dotyczy (bez zmian w assetach) |
| Testy | `composer test`: przed etapem 124 OK, po etapie 130 OK (377 asercji) |
| Backup | gotowy, odtworzenie zweryfikowane, 0 różnic (sekcja 6) |

**Ograniczenia audytu**

- Strona w Local była wyłączona (brak socketu MySQL). Dane zbadano na **prywatnej kopii** datadir; oryginału nie uruchamiano.
  Nie było więc WP-CLI ani sprawdzenia renderu. Testy UI pominięto: projekt nie ma Playwrighta, a strona nie działała.
- Produkcji nie badano (zakaz). Stan jej bazy, wersja PHP, `ext-zip` i sposób dostarczania `vendor/` są **niezweryfikowane**.
- Do etapu nie przekazano: `README.md` roadmapy, `CHECKLISTA_QA.md`, `MODEL_ZMIENNY.md`, `SZABLON_UMOWY_NOTATKI.md`,
  katalogu `etapy/` ani wzoru umowy. Tokenów, §5/§6 i mapowania wzoru nie dało się ocenić. Etapy 07–10 muszą je dostać.
- Baza Local to stan z 14.10.2026 (ostatnia publikacja: 14.10). Liczby niżej nie opisują produkcji.

---

## 1. Architektura — punkty integracji

| Obszar | Stan faktyczny | Punkt integracji |
|---|---|---|
| Edytor | Alpine `resources/js/guesstimate/document-editor.js` + iframe z `resources/js/offer.js` (`window.gstDoc`, postMessage); `DocumentController`, `DocumentEditor`, `DocumentForm::design()`. Tylko gotowe układy (`Pages::PAGE_LAYOUTS`), kliknięcie elementu (`data-edit`) otwiera pole w panelu. Brak pozycji x/y, warstw, undo/redo, snapa i podglądu mobile | Etapy 03–05 |
| Render | Jedna implementacja: `public/partials/pages.blade.php` + `public/pages/*.blade.php`; arkusz w `cqw` (od 1180 px, edytor, miniatury) i układ płynny (mobile) | Nowa warstwa w `partials/pages.blade.php`, obok `.osf-content` |
| Dane dokumentu | `gst_offer_versions.document` (JSON) i `.content` (JSON) | `pages.<klucz>.elements` — bez nowej kolumny |
| Publikacja | `PublicationService` + `PublicOffer::build()` + `DocumentBuilder::build()`; snapshot w `gst_publications.snapshot`, render przez `DocumentView::fromSnapshot()` | Nowe klucze tylko gdy niepuste (R1) |
| Klienci | `Client`, `ClientService` (`FIELDS`, `validate()`, `delete()` z `lockForUpdate`), import ClickUp uzupełnia tylko puste pola | Dane stron umowy: nowe pola + `ClientService::FIELDS` + formularz + walidacja |
| Uploady | `Media::upload()`: JPG/PNG/WebP, 15 MB, biblioteka WP bez własnego magazynu; `POST /app/media`, `CREATE_OFFERS` + `upload_files` | `Media`, `Media::browse()`, `MediaRefs` |
| Ochrona mediów | `gst_media_refs` + filtr `pre_delete_attachment`: chroni **tylko załączniki z publikacji** | Rozszerzenie na wersje robocze (R5) |
| Uprawnienia | `Capabilities`, `Roles` (VERSION 3), `OfferPolicy`, `gst.can:`, `Modules` (menu) | Nowe capabilities, `Roles::VERSION` = 4, `ContractPolicy` wzorowana na `OfferPolicy` |
| Numeracja | `OfferNumberGenerator`, `gst_number_sequences` (prefiks + rok, `FOR UPDATE`) | Własny prefiks umów; sprawdzanie zajętości numeru dziś patrzy tylko na `gst_offers` |
| Testy | PHPUnit bez WordPressa i bazy (`tests/Unit`). Brak testów integracyjnych i JS | Etap 13 |

**Szablony.** Szablonów umów, dokumentów ani eksportu nie ma. Słowo „template” w kodzie oznacza co innego:
typy podstron do wyceny (`gst_page_types`, `gst_v_templates`). Najbliższe wzorce to `gst_settings.document`
(domyślne teksty) i gotowe układy `Pages`.

**Eksport.** Brak bibliotek PDF/DOCX w `vendor/`. Lokalnie są `ext-zip`, `gd`, `imagick`, `dom`, `mbstring`;
nie ma LibreOffice, Chromium ani Ghostscript. Rekomendacja: czysto PHP-owe `phpoffice/phpword` i `dompdf/dompdf`,
potwierdzić z produkcją (sekcja 7, ryzyko 3). Czcionka PDF musi mieć polskie znaki.

**VAT.** Wyceny są netto. `gst_settings.vat_rate` = 23.00 jest „informacyjne” i nieużywane w obliczeniach.
Kwoty brutto do umowy trzeba liczyć w module umów (grosze, `brick/math`, HALF_UP), nie w silniku wyceny.

## 2. Mapa danych

Liczby dokładne z kopii bazy Local. Pełny odcisk: `baseline-data.txt` w backupie.

| Encja | Tabela | Wierszy | Uwagi |
|---|---|---|---|
| Oferty | `gst_offers` | 10 | 3 w koszu; status handlowy i `publication_status` są niezależne |
| Wersje | `gst_offer_versions` | 10 | 9 `budget` (silnik 2.0.0), 1 `percent` (1.0.0, tylko odczyt); żadna nie jest `locked_at` |
| Dzieci wersji | `gst_v_stages` 6, `_templates` 3, `_sitemap_nodes` 28, `_features` 22, `_work_items` 1, `_costs` 1 | | Kopia per wersja, niezależna od bibliotek |
| Publikacje | `gst_publications` | 6 | 4 aktywne; snapshot JSON |
| Referencje mediów | `gst_media_refs` | 3 | Zgodne ze snapshotami: brak braków i osieroconych ID |
| Wyświetlenia / historia | `gst_offer_views` 1, `gst_offer_events` 34 | | |
| Klienci | `gst_clients` | 3 | 1 usunięty; **0 z NIP** |
| Biblioteki | `gst_backgrounds` 5, `gst_case_studies` 3, `gst_features` 25, `gst_feature_categories` 12, `gst_page_types` 12 | | |
| ClickUp | `gst_clickup_connections` 1, `gst_clickup_index` 10 648 | | Token zaszyfrowany |
| Ustawienia | `gst_settings` | 17 | `secrets` zaszyfrowane kluczem z soli WP |
| AI / numeracja | `gst_ai_requests` 10, `gst_number_sequences` 1 | | |
| Załączniki WP | `wp_posts` (attachment) | 6 | Wyłącznie JPEG |
| Użytkownicy | `wp_users` | 1 | Tylko administrator: do testów ról trzeba użytkowników Manager i Pracownik |

**Trzy generacje snapshotów, wszystkie aktywne:**

| Generacja | Publikacje | Cecha |
|---|---|---|
| `schema: 1` | id 1 | brak `document`; strony odtwarzane w `DocumentView::legacy()` |
| `schema: 2` bez układów | id 2 | strony: `background`, `background_id`, `fit`, `key`, `label` |
| `schema: 2` z układami | id 3–6 | + `heading`, `hidden`, `image`, `layout` |

Dwie generacje `document` w wersjach (4 + 4) i dwie strony (3 vs 6 kluczy). `DocumentSettings::forVersion()` je wyrównuje.

**Czego brakuje do umów.** Klient ma: nazwę, WWW, osobę kontaktową, e-mail, telefon, NIP, notatki. Nie ma: adresu, KRS,
REGON, reprezentanta. Wszystkie 10 wycen ma widełki (`min ≠ max`), więc wybrany wariant **nie daje jednej kwoty** —
stąd decyzja 4.

## 3. Lista regresji

Każdy punkt to konkretny mechanizm, który kolejne etapy mogą zepsuć bezgłośnie.

| # | Ryzyko | Mechanizm | Wymaganie | Strażnik |
|---|---|---|---|---|
| R1 | Wszystkie publikacje pokazują „wersja robocza różni się” | `PublicationService::outdated()` porównuje odcisk snapshotu ze świeżym `build()`; nowy klucz lub `SCHEMA` = 3 zmienia odcisk | Nowe klucze tylko gdy niepuste; nie podbijać `SCHEMA` globalnie | Baseline |
| R2 | Pozycje znikają przy zapisie | `DocumentEditor::state()` buduje stronę z **zamkniętej listy 6 kluczy** | Dodać `elements` w `state()`, `DocumentForm::design()`, `DocumentBuilder`, `DocumentView::page()` | Etap 03 |
| R3 | Stare snapshoty przestają się renderować | `DocumentView` musi obsłużyć 3 generacje | Brak `elements` = dzisiejszy render | Baseline |
| R4 | Fałszywe „przepełnienie” lub blokada publikacji | `paginate()` klonuje strony „cd.”; `fit()` działa tylko dla `situation`; `fitHash()` nie zna pozycji; raport obejmuje jedną stronę | Strona ze swobodnym układem wyłączona z paginacji; `fitHash` i `blockers()` rozszerzone | Etap 03 |
| R5 | Grafika znika z oferty roboczej | `guardDelete` chroni tylko publikacje; wersja robocza dostaje „Zdjęcie usunięte z biblioteki” | Ochrona także dla użyć w wersjach roboczych | Etap 01 |
| R6 | Osierocone rekordy po trwałym usunięciu oferty | `TrashService::forceDelete()` ma ręczną listę tabel | Każda nowa tabela powiązana z ofertą dopisana tam | Baseline, etap 07 |
| R7 | Umowa traci ofertę lub klienta | `forceDelete()` nie zna umów; `ClientService::delete()` sprawdza tylko oferty | **Blokada** trwałego usunięcia oferty i klienta z umową | Etap 07 |
| R8 | Nowa rola nie działa | `Roles::syncIfOutdated` działa po wzroście `VERSION` i usuwa capabilities spoza definicji | Podbić `VERSION`; test ról | Baseline |
| R9 | Błędy 500 po wdrożeniu kodu bez migracji | `EnsureSchema` sprawdza tylko `gst_offer_versions`, **nie zatrzymuje** przy brakującej nowej migracji | Najpierw migracja albo rozszerzony warunek; zapytania poza `/app` (filtry WP) z `try/catch` jak w `MediaRefs` | Etap 01, 07 |
| R10 | Utrata zmian przy dwóch edytorach | `DocumentController::save()` nie ma blokady optymistycznej (ostatni zapis wygrywa) | Przy undo/redo dodać kontrolę `updated_at` | Etap 04 |
| R11 | Zamrożona wersja staje się edytowalna | `lockReason()` + `VersionCalculator` przy `locked_at` | Nowe zapisy przechodzą przez `locked()` | Etap 04 |
| R12 | Kopia wersji gubi dane | `VersionCopier` kopiuje surowe atrybuty — JSON przechodzi sam, nowe tabele powiązane z wersją **nie** | Nowa tabela per wersja = dopisać w `copy()` | Etap 01, 07 |
| R13 | Kalkulacja zmieniona przez edytor | Zapis edytora nie przelicza wyceny (`recalculate: false`), cena z `result` | Nigdy nie edytować ceny w dokumencie jako tekstu | Etap 04 |
| R14 | Dane wewnętrzne u klienta | `PublicOffer` celowo pomija godziny, stawki i notatki; `OfferContext` nie wysyła danych osobowych do AI | Umowy i tokeny poza snapshotem publicznym; bez treści umów w AI | Etap 09 |
| R15 | Szerszy dostęp do plików umów | Oferta publiczna działa przez token bez logowania | PDF/DOCX tylko po zalogowaniu i z polityką; **nie** w publicznej bibliotece mediów | Etap 11 |
| R16 | ClickUp nadpisuje dane stron umowy | Import uzupełnia puste pola klienta | Umowa zamraża dane w snapshocie, zmiana klienta jej nie dotyka | Etap 09 |
| R17 | JS edytora obciąża ofertę publiczną | `offer.js` to wspólne wejście Vite dla klienta i edytora | Kod drag & drop w osobnym, dynamicznym module | Etap 04 |
| R18 | `yarn build` pominięty | `public/build` jest w gicie | Build i commit po każdej zmianie assetów | Każdy etap |

Do tego istniejące testy pilnują: oba silniki wyceny, `calc.xlsx`, statusy, role, schematy snapshotów, odcisk publikacji.

**Zachowania do zaakceptowania, nie błędy:** anulowanie oferty nie wycofuje publikacji (2 oferty `cancelled` + `published`,
link dalej działa; kod mówi, że statusy są niezależne). Publikacja nie zamraża wersji: mrozi dopiero status „Wysłana”
(0 zamrożonych wersji przy 4 aktywnych publikacjach). Dla umów oznacza to, że podstawą nie może być samo „opublikowana”.

## 4. Plan migracji

Zasady: tylko nowe pliki, kolumny `nullable` lub z domyślną, `down()` usuwa wyłącznie to, co dodał, zero `UPDATE` na
istniejących wierszach, brak zmian w snapshotach. Numeracja od `000011`. Nazwy są robocze; ostateczne ustala dany etap.

| Etap | Migracja | Dotyka istniejących danych? |
|---|---|---|
| 01 | `create_gst_media_assets` (metadane na załącznikach WP: nazwa, kategoria, autor) + użycia w wersjach roboczych | Nie |
| 03–04 | Brak: `elements` w istniejącym JSON `document`, opcjonalne | Nie |
| 07 | `create_gst_contract_templates` + wersje szablonów | Nie |
| 07 | `create_gst_contracts`, wersje/snapshot, zdarzenia | Nie |
| 07/09 | `add_gst_clients_legal_fields` (adres, KRS, REGON, reprezentant — nullable; lista po `MODEL_ZMIENNY.md`) | Tylko nowe puste kolumny |
| 10 | `create_gst_contract_payments` | Nie |

Klucze obce w istniejącym stylu: bez constraintów, spójność w serwisach (`foreignId` bez `constrained`).
Role: `Roles::VERSION` 3 → 4 bez migracji, sync na `init`. Migracje uruchamia użytkownik lokalnie (`wp acorn migrate`), po
backupie; przed każdą porównanie z `baseline-data.txt`.

**Kolejność wdrożenia na produkcję (przy odbiorze):** backup produkcji → `wp acorn migrate` → kod + `public/build` →
`composer install`, jeśli doszły zależności → sprawdzenie aktywnych linków `/offer/{token}/`. Migracje idą pierwsze, bo są
wyłącznie addytywne, a kod bez nich daje 500 (R9).

## 5. Testy bazowe

- `composer test`: 130 testów, 377 asercji (124 istniejące + 6 nowych).
- Nowy `tests/Unit/Baseline/RegressionBaselineTest.php` pilnuje: niezmienionych 10 migracji (SHA-256), reguły odcisku
  (R1), ceny/treści/kolejności stron w 3 generacjach snapshotów, kontraktu `Pages`, capabilities ról, tabel `forceDelete`.
- `baseline-data.txt` (w backupie): liczby wierszy, MD5 każdego snapshotu, kwoty i MD5 `result` każdej wersji. Te same
  zapytania po każdej migracji muszą dać identyczny wynik dla istniejących wierszy.

## 6. Backup i rollback

Katalog: `/Users/Arek/Websites/guesstimate/app/backups/2026-11-01-stage-00/` — poza webrootem i repo, `chmod 700`, 17 MB.

| Plik | Zawartość |
|---|---|
| `db-local-logical.sql.gz` | dump logiczny bazy `local` |
| `mysql-datadir-cold.tar.gz` | kopia datadir przy zatrzymanym serwerze |
| `uploads.tar.gz` | `wp-content/uploads` (39 plików) |
| `theme-repo.bundle` | pełne repo motywu, `bundle verify` OK |
| `baseline-data.txt`, `SHA256SUMS`, `RESTORE.md` | odcisk danych, sumy, instrukcja odtworzenia |

**Weryfikacja:** dump odtworzony do osobnej bazy: 38 tabel, 11 323 wiersze, 0 różnic (`COUNT(*)` + `CHECKSUM TABLE`). Archiwum
datadir rozpakowane i uruchomione na MySQL 8.4.0. Odtwarzania na żywej bazie Local **nie wykonywano**. `wp-config.php`
jest poza backupem (sekrety; jest tylko SHA-256): sekrety w bazie deszyfruje sól WP, więc utrata tego pliku unieważnia klucz
OpenAI i tokeny ClickUp.

**Rollback:**

1. Kod: `git revert` lub powrót do `47855e2` (albo `theme-repo.bundle`).
2. Baza po migracji addytywnej: `wp acorn migrate:rollback` tylko dla migracji `≥ 000011`. Nigdy poniżej: `down()` starych
   migracji kasują kolumny z danymi (np. `000004` usuwa `budget`, `content`, `gst_publications`).
3. Baza awaryjnie: pełne odtworzenie według `RESTORE.md` (po zgodzie użytkownika, kasuje zmiany od backupu).
4. Po rollbacku porównanie z `baseline-data.txt`; `Roles::VERSION` cofnięty w kodzie wymaga ręcznego `Roles::sync()`,
   bo `syncIfOutdated` działa tylko w górę.

## 7. Ryzyka

1. **`deploy/deploy-db.sh` nadpisuje całą bazę produkcji bazą lokalną** (robi backup, ale kasuje dane nowsze niż lokalne:
   oferty, publikacje, a potem umowy). `sed` i `search-replace` modyfikują przy tym snapshoty. Po starcie pracy na
   produkcji nie wolno go używać; wdrażać wyłącznie przez migracje.
2. **`deploy/pull-db.sh` zawiera jawne hasło bazy produkcji** (plik poza gitem). W repo jest śledzony klucz prywatny `key` (znane).
   Obu nie używano.
3. **`vendor/` jest poza gitem**, a sposób wdrożenia kodu (commit „Automatyczny deploy”) nie wynika z repo. PHPWord/dompdf
   wymagają potwierdzenia, że `composer install` działa na produkcji, oraz wersji PHP i `ext-zip`. Lokalnie PHP 8.5.3,
   `composer.json` deklaruje ≥ 8.2.
4. **Pozycje swobodne a paginacja** to największe ryzyko techniczne: układ automatyczny i „cd.” nie są zgodne z absolutnym
   pozycjonowaniem. Rekomendacja: `elements` tylko na arkuszu, współrzędne w % strony (zgodne z `cqw`), na mobile układ
   automatyczny.
5. **Treść prawna:** wzór umowy i `SZABLON_UMOWY_NOTATKI.md` nie były dostępne. Bez nich nie wolno projektować importu.
6. **Testów integracyjnych i UI nie ma**; nowe serwisy z bazą będą bez pokrycia do etapu 13, chyba że wcześniej powstanie
   środowisko testowe. Testy ról wymagają użytkowników Manager i Pracownik.

## 8. Decyzje do potwierdzenia

Rekomendacje na podstawie kodu. Nic z tego nie zostało przyjęte jako ustalone, poza założeniem do etapu 01 przy decyzji 2.

| # | Decyzja | Rekomendacja | Potrzebna przed |
|---|---|---|---|
| 1 | Przesuwanie tekstu na mobile | Bez przesuwania: na mobile układ automatyczny, podgląd mobile tylko do odczytu pozycji | Etap 03 |
| 2 | Grafiki globalne | Tak, dla `CREATE_OFFERS`: `Media::browse()` już dziś pokazuje całą bibliotekę każdemu pracownikowi. **Założenie etapu 01** | Etap 01 |
| 3 | Numeracja i statusy umów | Własna numeracja (`gst_number_sequences`, własny prefiks), statusy szkic / zatwierdzona / podpisana ręcznie | Etap 07 |
| 4 | Podstawa ceny umowy | Kwota wpisana ręcznie, z podpowiedzią z wybranego wariantu; widełki wykluczają automat | Etap 07 |
| 5 | Zadatek | Wymaga decyzji; bez wzoru brak rekomendacji | Etap 10 |
| 6 | §5 netto vs §6 brutto | Zachować oba zapisy, walidacja tylko ostrzega; wymaga decyzji po wzorze | Etap 10 |
| 7 | DOCX 1:1 | Wymaga wzoru; dopuścić uproszczenie typografii, treść bez zmian | Etap 11 |
| 8 | Zatwierdzanie i szablony | Nowe capabilities: szablony — Administrator; zatwierdzanie — Administrator i Manager; tworzenie — jak `CREATE_OFFERS`. Dziś Manager nie ma `MANAGE_SETTINGS`, więc potrzebne osobne, nie te same | Etap 07 |

## 9. Do zweryfikowania ręcznie

- Czy wypełnić brakujące dokumenty (sekcja „Ograniczenia”) dla etapów 07–11.
- Na produkcji: PHP, `ext-zip`, `composer`, sposób wdrożenia kodu.
- Przy następnym uruchomieniu strony w Local: `wp acorn migrate:status`, render oferty publicznej.
