# STAGE-11 — Eksport

**Status: DONE, z zastrzeżeniem** (2026-11-02, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–10)

Zatwierdzoną wersję umowy można pobrać jako PDF i DOCX. Oba pliki powstają z tych samych danych tej samej wersji. **DOCX nie był otwarty w Wordzie**, bo tego etapu nie wolno było robić przez aplikacje z interfejsem, a w systemie nie ma LibreOffice. Sprawdziły go niezależny parser i podgląd `docx-preview`.

| Pozycja | Wynik |
|---|---|
| Migracje | **brak nowych**; moduł wymaga migracji z etapu 07 |
| `PublicOffer::SCHEMA`, `Pages::blank` | bez zmian |
| Nowa zależność | `dompdf/dompdf` ^3.1 (+4 pakiety), `composer.json` i `composer.lock` |
| Build | **nie dotyczy**: bez zmian w `resources/css` i `resources/js`; użyte klasy są w obecnym buildzie |
| Testy PHP | `composer test`: 254 OK (770 asercji; przed etapem 237) |
| Testy JS | `node --test 'tests/js/*.test.mjs'`: 23 OK |
| Test UI etapu | **27/27 OK** (`tests/ui/contract-export.mjs`), headless Chromium, kopia bazy, prawdziwe pobrania |
| Dziennik | 4 pobrania = 4 zdarzenia, skrót zgodny, bez treści |
| Test obciążeniowy | 2 oglądane obrazy (PDF, DOCX): patrz „Błędy” |
| Regresja | generator 27, integracja 31, płatności 26 i 20, szablony 66, cykl umowy 67, umowa z szablonu 25: wszystkie OK |
| Render legacy | **48/48** pozycji identycznych ze wzorcem |

## Kryteria odbioru

| Kryterium | Jak sprawdzono | Wynik |
|---|---|---|
| Porównanie tekstowe PDF/DOCX | tekst z pdf.js i z `word/document.xml`, po usunięciu nagłówków, stopek i powtórzonych nagłówków tabeli: identyczny | OK |
| Porównanie wizualne | 6-stronicowy PDF i DOCX obejrzane na obrazach | OK dla PDF; **DOCX bez podziału stron**, `docx-preview` go nie liczy |
| Brak uciętych tabel | 60 wierszy na 5 stronach, wszystkie obecne, nagłówek powtórzony, tekst w marginesach. Wiersz wyższy od strony: eksport PDF odrzucony | OK |
| Brak pustych tokenów | brak `[brak`, `undefined`, etykiet tokenów; umowa z brakami nie jest eksportowana | OK |
| Polskie znaki | w treści, tabeli i adresie, odczytane z pliku | OK |
| Numery stron | PDF „Strona n z 6” na każdej stronie; DOCX pola `PAGE`/`NUMPAGES` w XML | OK dla PDF; **DOCX do sprawdzenia w Wordzie** |
| Bezpieczne pliki | pliki tworzone przy pobraniu, `attachment`, nazwa z samych numerów, `nosniff`, `no-store`; pracownik bez dostępu dostaje 403 | OK |

## Co powstało

- **Przyciski „Pobierz PDF” i „Pobierz DOCX”** na ekranie umowy, tylko dla wersji zatwierdzonej i zgodnej ze skrótem.
- **Trasa** `GET /app/umowy/{id}/eksport/{pdf|docx}?wersja=N`. Serwer sam sprawdza te warunki, a odmowa wraca na ekran umowy z powodem.
- **Podmieniona treść** (zmiana SQL po zatwierdzeniu): czerwony komunikat i blokada.
- **Dziennik:** każde pobranie zapisuje format i skrót, bez treści.
- **§ jako tekst:** w edytorze numer daje CSS, którego w pliku nie ma, więc eksport liczy go raz, wspólnie dla obu formatów.
- **Bez zapisu na dysku:** poza plikiem tymczasowym na czas pakowania DOCX. Treść umowy nie zmienia się.

## Decyzje

- **Decyzja 7 pozostaje otwarta.** Przyjęto uproszczoną typografię, bez obietnicy 1:1 względem podglądu lub oryginalnego DOCX: PDF ma DejaVu Sans, DOCX Arial, a kolumny tabel są równe.
- **`dompdf/dompdf` zamiast generatora własnego lub Chromium:** działa w czystym PHP, bez binarek na serwerze. Licencja LGPL. `composer audit` nie zgłasza dla niego ani jego pakietów żadnych uwag.
- **DOCX bez biblioteki:** własny zapis przez `ZipArchive`, spójny z `DocxImporter`. Każdy węzeł tekstu przechodzi wprost.
- **Odmowa zamiast cichej utraty treści.** Wiersz tabeli wyższy niż strona, nagłówek ze stopką powyżej 180 mm i emoji w PDF kończą się komunikatem. DOCX działa w tych przypadkach.
- Nic nie podpisuje ani nie wysyła umów. Klauzule nie zostały zmienione. Wszystkie treści testowe są sztuczne.

## Błędy znalezione przez testy

Test obciążeniowy wykrył w pierwszej wersji `PdfExporter` trzy błędy, których nie wykrył test zwykłej umowy. Wszystkie naprawiono.

1. **Wiersz wyższy niż strona był ucinany bez błędu.** Z 260 powtórzeń tekstu w pliku zostało 39, a przy tym powstawały puste strony. To utrata treści prawnej. Dompdf nie umie podzielić wiersza, więc eksport teraz go wykrywa i odmawia.
2. **Długi nagłówek nachodził na treść.** Szacowanie po liczbie znaków było błędne. Nagłówek i stopka są teraz mierzone osobnym przebiegiem.
3. **Długie niepodzielne słowo wychodziło poza margines.** Zawijanie obejmuje teraz cały dokument.

Dodatkowo w DOCX: scalanie wierszy (`rowspan`) przesuwało kolumny, a tabela w komórce spłaszczała się do tabeli zewnętrznej.

Sam test miał błąd: zakładał, że punktory listy są tekstem PDF. Są grafiką, więc poprawiono test.

## Pliki

- Nowe:
  - `app/Guesstimate/Contracts/ContractExport.php`, `PdfExporter.php`, `DocxExporter.php`
  - `tests/Unit/Contracts/ContractExportTest.php`
  - `tests/ui/contract-export.mjs`, `tests/ui/contract-export-prepare.php`
  - `STAGE-11.md`
- Zmienione:
  - `app/Guesstimate/Http/Controllers/ContractController.php`, `routes/app.php`
  - `resources/views/guesstimate/contracts/show.blade.php`
  - `composer.json`, `composer.lock`, `AGENTS.md`

## Testy

- **Jednostkowe:** § i tokeny, poprawność XML wszystkich części DOCX, escapowanie, odczyt pliku przez `DocxImporter`, pola stron, listy, scalanie, tabele zagnieżdżone, A4, odmowy.
- **UI:** szkic, podmieniona treść, nieznany format, nagłówki odpowiedzi, dostęp pracownika, tekst, strony i marginesy. Narzędzia `pdfjs-dist`, `docx-preview`, `jszip` zainstalowano w katalogu tymczasowym, poza projektem.
- Po testach prywatna baza jest zatrzymana, kopia usunięta, procesy `mysqld`, Chromium i `php-cgi`: 0. Realny katalog uploads ma nadal 39 plików.
- `it7` wycofał publikację 5 na kopii, jak w poprzednich etapach. To działanie kosza, tylko na kopii.
- Czasy: dokument testowy około 0,2 s i 20 MB.

**Konflikt instrukcji:** `CLAUDE.md` i `AGENTS.md` zabraniają przeglądarki do weryfikacji, a etap wymaga testów UI i porównania wizualnego. Przyjęto polecenie etapu, tylko headless. Word nie został otwarty.

## Ograniczenia

- **Prawdziwy Word nie sprawdzony.** Kolejność elementów OOXML ustawiono wg schematu, ale bez walidatora. Word może zgłosić naprawę pliku; tego testy nie wykluczają.
- **Podział stron DOCX i numery stron w DOCX** nie były widoczne w żadnym narzędziu. Liczy je Word przy otwarciu.
- **Wzór umowy nie był testowany.** Etap użył treści sztucznej, a prawdziwy szablon może mieć wysoki wiersz, np. układ w tabeli. Wtedy PDF zostanie odrzucony do czasu poprawy w nowej wersji.
- PDF odrzuca tylko znaki spoza BMP. Inne znaki spoza DejaVu Sans, np. CJK, dadzą puste kwadraty; odmowy dla nich nie ma.
- Zatwierdzanie nadal nie ma przycisku w interfejsie (etap Workflow). Test zatwierdzał przez usługę, więc do tego czasu przyciski pobrania się nie pojawią.
- Komunikat o odmowie z powodu emoji lub wysokiego wiersza sprawdzono jednostkowo, nie na ekranie.
- Stopka z tabelą nie była testowana z realnym wzorem; pomiar stopki sprawdzono na tekście.
- `composer audit` zgłasza 23 uwagi dla 8 pakietów istniejących wcześniej (m.in. guzzle, symfony, flysystem). Nie są związane z tym etapem i nie były aktualizowane.
- Repo nie pokazuje, jak `vendor` trafia na serwer: nie jest w gicie, a w `deploy/` nie ma instalacji composera. Bez `composer install` na serwerze eksport zwróci błąd 500. Reszta aplikacji działa.

## Rollback

Cofnięcie kodu oraz `composer.json` i `composer.lock`, potem `composer install`. Struktura bazy się nie zmienia. Zdarzenia `exported` zostają w dzienniku.

## Wymagane od użytkownika

- `composer install` lokalnie i na każdym serwerze, na którym kod się znajdzie.
- Backup i `wp acorn migrate` w Local, jeśli migracja z etapu 07 nie została jeszcze wykonana.
- Commit (nic nie zostało zacommitowane). Ten etap nie zmienia `public/build`, ale poprzednie tak.
- Decyzja 7 oraz pozostałe otwarte decyzje: 1, 2, 3, 4, 5, 6, 8, autozapis, boczne napisy, ceny netto, VAT od zadatku.

## Do zweryfikowania ręcznie

Po migracji, na zatwierdzonej umowie z prawdziwego szablonu:

1. **Otwórz DOCX w Wordzie.** Sprawdź brak komunikatu o naprawie, listy, numery i podział stron, nagłówek i stopkę, „Strona n z m”.
2. Porównaj PDF z DOCX i z podglądem w aplikacji, także tabelę płatności.
3. Prawnie ważne: przeczytaj eksport wzoru względem oryginału.
4. Spróbuj pobrać PDF z bardzo długim wierszem tabeli i przeczytaj komunikat.
5. Otwórz DOCX w Pages lub Google Docs, jeśli klienci z nich korzystają.
