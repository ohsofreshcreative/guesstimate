# STAGE-14 — Odbior

**Status: DONE, wdrożenie produkcyjne NIEGOTOWE** (2026-11-02, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–13)

Dokumentacja jest gotowa, demo przeszło na kopii bazy, a pełny zestaw testów przechodzi. Modułu umów nie wolno jeszcze wdrażać: nie ma prawdziwego wzoru umowy, 6 z 8 decyzji blokuje produkcję umów, a błędu w zatwierdzonej umowie nie da się poprawić z interfejsu.

| Pozycja | Wynik |
|---|---|
| Dokumentacja | `docs/ODBIOR.md`: role, wymagania, migracja, build, QA, rollback, plan produkcji, decyzje |
| Migracje | **brak nowych**; `000011` z etapu 07 czeka na uruchomienie w Local |
| `PublicOffer::SCHEMA`, `Pages::blank` | bez zmian |
| Build | **nie dotyczy**: bez zmian w assetach w tym etapie |
| Demo | `/Users/Arek/Websites/guesstimate/app/backups/2026-11-02-stage-14-demo` |

## Kryteria odbioru

| Kryterium | Wynik |
|---|---|
| Wdrożenie wyłącznie na LocalWP | **potwierdzone, w zakresie niżej** |
| Lista decyzji otwartych | `docs/ODBIOR.md`, sekcja 8: 8 decyzji i 7 spraw dodatkowych |
| Plan produkcji | `docs/ODBIOR.md`, sekcja 7; **plan, bez rozpoznania produkcji** |

### Co dokładnie potwierdzam

- **Produkcja:** żadnego połączenia, commitu, pusha ani deployu. HEAD = lokalny `origin/main` = `47855e2`; reflog nie ma nowych wpisów. Stanu zdalnego nie pobierano.
- **Kod:** cała praca jest w drzewie roboczym motywu w Local.
- **Baza Local:** w etapach nietknięta. Migracja i testy szły wyłącznie na prywatnych kopiach na porcie 33177. Local była przy tym sprawdzeniu wyłączona (brak socketu), więc stanu jej bazy nie odczytano.
- **Czyli słowo „wdrożenie” jest tu na wyrost:** aplikacja w Local po uruchomieniu nie ma jeszcze tabel umów. Pełny obieg działał tylko na kopii.

## Demo

Skrypt `tests/ui/demo.mjs`, Chromium headless, kopia bazy, oferta OSF/2026/0010. Treść umowy jest sztuczna, to nie jest wzór prawny. Nikt nie oglądał demo na żywo; dowodem są zrzuty i pliki. Sprawdziłem je wzrokowo.

| Plik | Ekran |
|---|---|
| `01-oferta-z-umowami.png` | karta oferty z sekcją umów |
| `02-szablony.png` | lista szablonów |
| `03-nowa-umowa-z-oferty.png` | przygotowanie, ostrzeżenie o brakach |
| `04-szkic-podglad.png` | szkic: podgląd i wartości |
| `05-platnosci.png` | kwota, etapy, zadatek |
| `06-potwierdzenie.png` | potwierdzenie zatwierdzenia |
| `07-zatwierdzona.png` | wersja zatwierdzona, przyciski pobierania |
| `08-lista-umow.png` | lista z filtrami |
| `umowa-demo.pdf`, `umowa-demo.docx` | pobrane pliki |

Demo nie obejmuje edytora oferty i biblioteki; te ekrany są w zrzutach `2026-11-02-stage-13-zrzuty`.

## Testy

Końcowy pełny przebieg, po wszystkich zmianach kodu, na świeżych kopiach:

| Zestaw | Wynik |
|---|---|
| `composer test` | 257 OK (778 asercji) |
| `node --test 'tests/js/*.test.mjs'` | 23 OK |
| Dane przed testami / po testach | 40/40, 17/17 |
| Render legacy | 48/48, różnic 0 |
| Układ, kompatybilność, wygląd, druk | 75, 56, 86, 29: bez błędów |
| Biblioteka | 26/26 |
| Responsywność | 99/99 |
| Umowy UI: szablony, generator, płatności, eksport, obieg | 66, 27, 26, 27, 58: bez błędów |
| Umowy, integracja: lifecycle, from-template, values, payments | 67, 25, 31, 20: bez błędów |

To domyka zastrzeżenie z `STAGE-13.md`: tym razem wszystkie zestawy przeszły po ostatnich poprawkach. Podział mock i real jest tam opisany i nadal obowiązuje.

## Nowe ustalenia

- **Auto-deploy jest niesprawdzony.** Commit nazywa się „Automatyczny deploy”, a w repozytorium nie ma workflow. Jeśli mechanizm jest po stronie Hostingera lub GitHuba, zwykły push wyśle kod bez migracji. Do czasu sprawdzenia pracuj na osobnej gałęzi.
- **`ext-zip` i `ext-sodium` nie są w `composer.json`.** Instalacja nie zgłosi ich braku na serwerze. Nie dodawałem ich, bo zmiana wykracza poza etap.
- **PHP:** aplikacja działała wyłącznie na 8.5.3, przy deklarowanym >= 8.2.

## Korekty

W `docs/ODBIOR.md` od razu podaję ograniczenie z etapu 07: bez migracji trwałe usunięcie oferty i klienta kończy się błędem SQL. Moja pierwsza wersja tekstu mówiła tylko o instrukcji 503.

## Pliki

- Nowe: `docs/ODBIOR.md`, `tests/ui/demo.mjs`, `tests/ui/demo-prepare.php`, `STAGE-14.md`.
- Zmienione: `AGENTS.md` (wpis o demo).

## Ograniczenia

- Komenda migracji w `docs/ODBIOR.md` nie była uruchamiana na bazie Local; na kopii szła przez skrypt z blokadą portu.
- `CHECKLISTA_QA.md`, `README.md` roadmapy, `MODEL_ZMIENNY.md`, `SZABLON_UMOWY_NOTATKI.md` nie zostały przekazane. Lista QA powstała z raportów etapów.
- Plan produkcji zakłada kroki, których nie da się zweryfikować bez rozpoznania serwera.
- Wszystkie ograniczenia z `STAGE-13.md` zostają: Word, formularz logowania, Safari, Firefox, OpenAI, ClickUp, dotyk.

## Rollback

Usunięcie `docs/`, `tests/ui/demo*` i wpisu w `AGENTS.md`. Aplikacji etap nie zmienia. Pełny rollback projektu: `docs/ODBIOR.md`, sekcja 6.

## Wymagane od użytkownika

1. Sprawdzenie auto-deployu przed jakimkolwiek pushem.
2. Backup i migracja w Local (`docs/ODBIOR.md`, sekcja 3).
3. Przekazanie prawdziwego wzoru umowy i brakujących dokumentów.
4. Decyzje z sekcji 8, w tym „Nowa wersja” zatwierdzonej umowy.
5. Wymiana klucza `key` i hasła produkcji z `deploy/pull-db.sh`.
6. Commit razem z `public/build`.

## Do zweryfikowania ręcznie

Blok A listy QA w `docs/ODBIOR.md`, a przede wszystkim: DOCX w Wordzie, logowanie, import prawdziwego wzoru i porównanie eksportu z oryginałem.
