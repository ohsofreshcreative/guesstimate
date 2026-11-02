# STAGE-04 — Drag Drop

**Status: DONE** (2026-11-02, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–03)

W edytorze dokumentu pola tekstowe można przeciągać, zmieniać ich rozmiar uchwytami, wyrównywać, przyciągać do siatki, duplikować, blokować,
układać w warstwach, cofać i obsługiwać z klawiatury. Doszedł autozapis i historia zmian układu.

| Pozycja | Wynik |
|---|---|
| Migracje | **brak** |
| `PublicOffer::SCHEMA` | bez zmian (2) |
| Build | `yarn build` wykonany lokalnie, OK; **bez commitu** |
| Testy PHP | `composer test`: 160 OK (464 asercje; przed etapem 155) |
| Testy JS | `node --test 'tests/js/*.test.mjs'`: 12 OK |
| Test UI | **75/75 OK** w headless Chromium, prawdziwa aplikacja na kopii bazy |
| Blokada publikacji | 5/5 OK na kopii bazy |
| Render legacy | 45/45 pozycji identycznych bajt w bajt ze wzorcem z etapu 03 |

## Kryteria odbioru

| Kryterium | Jak sprawdzono | Wynik |
|---|---|---|
| Kilka stron | Sytuacja, Okładka, Wycena; cofanie przechodzi między stronami | OK |
| Zoom | 75%, 125%, Dopasuj: ruch proporcjonalny do strony, pozycja zgodna z obrazem | OK |
| Odświeżenie | układ, kopia i historia zostają, cofanie działa po przeładowaniu | OK |
| Cofanie | Ctrl+Z, Ctrl+Shift+Z, przyciski, cofanie resetu strony | OK |
| Brak overflow | pole nie wychodzi za krawędzie; przepełnienie wykryte, nic nie ucięte, publikacja zablokowana | OK |

## Obsługa

| Działanie | Sposób |
|---|---|
| Wybór pola | kliknięcie w podglądzie albo na liście „Układ pól” |
| Przesuwanie | przeciąganie, próg 4 px; strzałki (krok siatki), Shift+strzałka (5 kroków) |
| Rozmiar | 8 uchwytów na ramce |
| Wyrównanie | do strony (6 pozycji) i tekstu (3 pozycje) |
| Siatka | przyciąganie co 2%, Alt wyłącza je na czas ruchu; siatka widoczna opcjonalnie |
| Warstwy | Na wierzch, Wyżej, Niżej, Na spód; `]` i `[` |
| Blokada | blokuje ruch i rozmiar; odblokowanie zawsze dostępne |
| Duplikat | przycisk lub Ctrl+D, najwyżej 6 kopii na stronę |
| Cofanie | Ctrl+Z, Ctrl+Shift+Z, Ctrl+Y, przyciski; historia 50 kroków |
| Reset | „Przywróć układ strony” (można cofnąć) |

**Wybór tekstu bez przypadkowego przeciągania.** Kliknięcie nigdy nie przesuwa. Dwuklik włącza tryb tekstu: wskaźnik zaznacza tekst, pole
się nie rusza, Esc kończy tryb.

**Pierwszy ruch na stronie** zapisuje pozycje wszystkich jej pól. Inaczej wyjęcie jednego pola przesunęłoby pozostałe.

## Decyzje i zmiany względem STAGE-03

- **Kopia pola to samodzielny tekst** (`copy_N`, własne pole `text`, rola `heading` lub `text`). Pole wskazuje jeden slot treści, więc inaczej nie dało się
  zrealizować „Duplikuj”. Kopia nie aktualizuje się po zmianie oryginału. Usunięcie kopii nie rusza oryginału.
- **Jednostki CSS:** pozycja pionowa liczona od wysokości A4 (`cqw`), a nie bieżącej strony. Wartości `--field-*` są teraz liczbami. Schemat zapisu bez zmian.
- **Publikację blokuje przepełnione pole na każdej stronie** (`fit_report.layout`, `PublicationService::fieldsOverflow`), a nie tylko na „Sytuacji”.
- **Autozapis zmienia dotychczasową zasadę „zapis tylko przyciskiem”** i obejmuje **wszystkie** zmiany w edytorze, także teksty. Działa po 2,5 s przerwy, można go
  wyłączyć przełącznikiem, wstrzymuje się przy błędach walidacji. Zapisuje wersję roboczą; to, co widzi klient, zmienia nadal tylko publikacja.
  Historia oferty się nie zaśmieca (`touch` scala wpisy z 10 minut).

## Błędy znalezione przez testy

Wszystkie naprawione przed zakończeniem etapu:

1. `setPointerCapture` przy naciśnięciu kierował kliknięcie na całą stronę: pole się nie zaznaczało.
2. Przeciągnięcie zablokowanego pola kasowało zaznaczenie.
3. Status „Zapisano automatycznie” był od razu kasowany przez obserwator stanu.
4. Pozycje w % wysokości rosły razem z wydłużaną stroną, więc przepełnione pole było ucinane (strona 1708 px, pole do 2311 px).
5. Zaznaczonego pola przykrytego innym nie dało się chwycić.
6. **Skrót pomiaru zależał od formatu JSON z MySQL.** Skutek: przepełniona strona z układem przechodziłaby przez blokadę publikacji. Dotyczyło też „Sytuacji”, kod z etapu 03.
7. Brakujące `use Pages` w `DocumentController` i `PublicationService`: błąd 500 przy przepełnionym polu. Żaden test jednostkowy tego nie wykrywał.

## Pliki

- Nowe:
  - `resources/js/guesstimate/field-layout.js`: czysta logika.
  - `resources/js/guesstimate/field-stage.js`: wskaźnik i klawiatura w ramce, ładowany tylko w edytorze.
  - `resources/views/guesstimate/editor/partials/fields.blade.php`, `resources/views/guesstimate/public/partials/field-copies.blade.php`.
  - `tests/js/field-layout.test.mjs`, `tests/ui/document-layout.mjs`, `tests/ui/bridge.mjs`, `tests/ui/cgi-prepend.php`.
  - `STAGE-04.md`.
- Zmienione:
  - `app/Guesstimate/Document/FieldLayout.php`, `DocumentSettings.php`, `DocumentEditor.php`.
  - `app/Guesstimate/Http/Controllers/DocumentController.php`, `app/Guesstimate/Offers/PublicationService.php`.
  - `resources/js/guesstimate/document-editor.js`, `resources/js/offer.js`, `resources/css/offer.css`.
  - `resources/views/guesstimate/editor/index.blade.php`, `editor/partials/page.blade.php`, `public/partials/pages.blade.php`.
  - `tests/Unit/Document/FieldLayoutTest.php`, `AGENTS.md`.
  - `public/build/**`: wynik `yarn build`.

## Testy

- **Test UI:** `tests/ui/document-layout.mjs`, prawdziwy edytor w headless Chromium. Bez nasłuchującego serwera: żądania przeglądarki trafiają do jednorazowych procesów
  `php-cgi`. Baza to kopia z backupu na porcie prywatnym. Bez widocznych okien.
  Objęte: wszystkie kontrolki z tabeli, autozapis, odświeżenie, tryb tekstu, przepełnienie, oferta klienta na arkuszu i na telefonie, brak błędów JS.
- **Render legacy:** 45 pozycji identycznych ze wzorcem. Oferta 5 wyłączona z porównania, bo testy zmieniły ją celowo; dzień okładki przypięty do wzorca.
  Snapshoty publikacji: te same sumy MD5.
- Po testach prywatna baza jest zatrzymana, a kopia usunięta. Realny katalog uploads ma nadal 39 plików.

**Konflikt instrukcji:** globalny `CLAUDE.md` i `AGENTS.md` zabraniają uruchamiania przeglądarki do weryfikacji, a dokument etapu i Christie zlecają testy UI w trybie Playwright
headless. Przyjęto polecenie etapu, bo kryteriów nie da się sprawdzić inaczej. Serwera deweloperskiego nie uruchomiono. To samo dotyczy `yarn build`.

## Ograniczenia

- **Tylko Chromium, tylko mysz.** Safari, Firefox i ekrany dotykowe nie były testowane.
- **Sprawdzono 3 z 8 stron.** Pozostałe używają tego samego mechanizmu, ale nie zostały przećwiczone. Nie sprawdzono też stron „cd.” z polami w przeglądarce.
- **Test działał jako administrator, z wyłączonymi wtyczkami.** Nie sprawdzono trybu tylko do odczytu (wersja zamrożona, inna rola): kod wyłącza edycję, ale bez testu UI.
- Historia dotyczy **tylko układu**, nie tekstów, i żyje w `sessionStorage`: znika po zamknięciu karty i nie przechodzi na inne urządzenie.
- Tekst edytuje się w panelu, nie bezpośrednio na stronie. Nie ma zaznaczania wielu pól.
- Podgląd desktop/mobile z dokumentu głównego nie należał do tego etapu. Telefon pokazuje układ automatyczny, a kopie na końcu strony (rekomendacja decyzji 1, **niepotwierdzona**).
- Układ nie reaguje na zmianę układu strony: pole może przykryć zdjęcie.
- Pusta kopia znika przy zapisie.
- `tests/ui/cgi-prepend.php` loguje administratora, ale działa wyłącznie jako `auto_prepend_file`. Wywołany z adresu URL niczego nie udostępnia. Warto mimo to nie wdrażać katalogu `tests/`
  na produkcję; deploy nie był sprawdzany.
- Test UI wymaga `playwright-core` poza repo (nie dodano zależności) i kopii bazy.

## Rollback

Cofnięcie kodu i `public/build`. Struktura bazy się nie zmienia. Klucze `fields` w wersjach roboczych stary kod zignoruje, więc oferty wrócą do układu z szablonu. Kopie pól nie będą wtedy widoczne.

## Wymagane od użytkownika

- Commit razem z `public/build` (nic nie zostało zacommitowane).
- Potwierdzenie, że autozapis ma być domyślnie włączony dla wszystkich zmian w edytorze.
- Decyzje 1 i 2 nadal otwarte.

## Do zweryfikowania ręcznie

Po uruchomieniu strony w Local:

1. Safari i Firefox: przeciąganie, uchwyty, skróty (na macOS Cmd+Z).
2. Pozostałe strony: Rozwiązanie, Usługi, Realizacje, Kolejne kroki, Kontakt.
3. Konto pracownika i wersja zamrożona: brak panelu „Układ pól”, brak przeciągania.
4. Strona z bardzo długim tekstem i układem: strony „cd.”.
5. Czy siatka 2% i próg 4 px są wygodne w codziennej pracy.
