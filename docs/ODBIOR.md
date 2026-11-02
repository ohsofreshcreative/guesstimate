# Guesstimate: dokumentacja odbioru

Dla zespołu wdrażającego i testującego. Stan z 2026-11-02, commit bazowy `47855e2` plus niezacommitowane zmiany etapów 00–14.
Szczegóły każdego etapu są w `STAGE-00.md` … `STAGE-14.md`, a architektura w `AGENTS.md`.

**Ten kod nie był wdrażany na produkcję: nie ma commitu ani pusha (HEAD = `origin/main` = `47855e2`). Migracja była uruchamiana tylko na prywatnych kopiach bazy, nie w bazie Local.**

## 1. Co doszło

| Obszar | Gdzie | Co robi |
|---|---|---|
| Biblioteka grafik | `/app/biblioteka` | foldery, tagi, opisy, wykrywanie duplikatów, miejsca użycia, archiwum |
| Edytor oferty | `/app/oferty/{id}/dokument` | przesuwanie pól, rozmiar, warstwy, siatka, cofanie, tryb starego układu, kadry, opcje wyglądu |
| Szablony umów | `/app/szablony-umow` | edytor, wersje, zmienne, import DOCX |
| Umowy | `/app/umowy`, karta oferty | generowanie z oferty, wartości, płatności, zatwierdzanie, eksport PDF i DOCX |

Nic nie podpisuje ani nie wysyła umów. Kod nie zmienia klauzul.

### Role

Podział umów jest propozycją z decyzji 8.

| Działanie | Pracownik | Manager | Administrator |
|---|---|---|---|
| Biblioteka | przesyła, edytuje własne | wszystkie, foldery | wszystkie, foldery |
| Umowy: podgląd i pobieranie | własne i do własnych ofert | wszystkie | wszystkie |
| Umowy: generowanie | z własnych ofert | z każdej | z każdej |
| Umowy: edycja szkicu | gdy jest autorem | wszystkie | wszystkie |
| Zatwierdzanie umów | nie | tak | tak |
| Szablony umów | nie | tak | tak |

## 2. Wymagania

| Składnik | Wymaganie | Sprawdzono |
|---|---|---|
| PHP | >= 8.2 | **tylko 8.5.3**; na 8.2–8.4 nie uruchamiano |
| Rozszerzenia PHP | `dom`, `mbstring`, `fileinfo`, `openssl`, `sodium`, `zip`, `json`, `pdo`; `imagick` wymaga wcześniejszy pakiet `spatie/pdf-to-image` | lokalnie obecne |
| MySQL | jak w Local, z kolumnami JSON | 8.4.0 |
| Node | >= 20, tylko do builda | 26.0.0 |
| Menedżer pakietów | yarn, composer | yarn 1.22.22 |
| Katalog tymczasowy | zapisywalny `sys_get_temp_dir()`: DOCX i cache fontów dompdf | lokalnie |

`zip` jest potrzebny do importu i eksportu DOCX, ale **nie jest zadeklarowany w `composer.json`**, więc `composer install` nie zgłosi jego braku. To samo dotyczy `sodium` używanego przez `Support\Secrets`.

Nowa zależność: `dompdf/dompdf` ^3.1 (LGPL-2.1) i jej pakiety zależne. Jest w `composer.lock`, a `vendor/` nie jest w gicie.

## 3. Migracja w Local

Jedyna nowa migracja: `database/migrations/2026_11_02_000011_create_gst_contract_tables.php`. Tworzy 5 tabel `gst_contract*` i niczego nie zmienia w istniejących. Sprawdzona na kopii bazy, także rollback.

1. Uruchom stronę `guesstimate` w Local (ID `KCw3RMQNE`).
2. Zrób **nowy** backup bazy. Procedura i dostęp: `app/backups/2026-11-01-stage-00/RESTORE.md`. Tamten backup jest ze stanu sprzed etapów.
3. W katalogu motywu:

   ```bash
   composer install
   S="$HOME/Library/Application Support/Local/run/KCw3RMQNE/mysql/mysqld.sock"
   php -d "mysqli.default_socket=$S" -d "pdo_mysql.default_socket=$S" \
     /opt/homebrew/bin/wp --path=/Users/Arek/Websites/guesstimate/app/public acorn migrate
   ```

4. Otwórz `/app`. Role zsynchronizują się same (`Roles::VERSION` = 5).
5. Przejdź listę QA z sekcji 5.

Komendy w tej postaci nie uruchamiano na bazie Local, bo tam migracja nie była wykonywana. Po niej sprawdź `wp acorn migrate:status` (ta sama forma wywołania): pozycja `000011` ma status „Ran”.

Bez migracji aplikacja startuje, a moduł umów pokazuje instrukcję (503). **Trwałe usunięcie oferty i usunięcie klienta kończą się wtedy błędem SQL**, bo sprawdzają tabelę umów. Kod bez migracji nie powinien więc zostawać na dłużej.

## 4. Build

Assety są w gicie (`public/build`). Etapy 00–10 je zmieniły, a etapy 11–14 nie.

```bash
yarn build
```

Build jest wymagany po każdej zmianie w `resources/css` lub `resources/js` oraz gdy Blade używa klasy Tailwind, której nie ma w obecnym CSS. Wynik trzeba zacommitować: nowe pliki `public/build/assets/*` i usunięcie starych.

## 5. Lista QA

Ta lista powstała z raportów etapów, bo `CHECKLISTA_QA.md` nie została przekazana. **Jeśli zespół ma własną listę, ta jej nie zastępuje.**

Bloki A–C dotyczą rzeczy, których testy automatyczne nie sprawdziły.

### A. Pierwszeństwo

- [ ] DOCX otwiera się w Wordzie bez komunikatu o naprawie pliku
- [ ] DOCX: podział stron, „Strona n z m”, nagłówek, stopka, listy, tabele
- [ ] Logowanie, wylogowanie, reset hasła, „Nie wylogowuj mnie”
- [ ] Prawdziwy wzór umowy: import, mapowanie zmiennych, zatwierdzenie
- [ ] Prawnik lub osoba odpowiedzialna porównuje eksport z oryginałem wzoru

### B. Oferty

- [ ] Stara oferta i publiczny link wyglądają jak przed zmianami
- [ ] Migracja jednej oferty: podgląd, „Anuluj”, „Zapisz migrację”, powrót do starego trybu
- [ ] Przeciąganie, rozmiar, warstwy, siatka, cofanie (Ctrl/Cmd+Z)
- [ ] Publikacja blokowana przy przepełnionym polu
- [ ] Realizacje z prawdziwymi grafikami: proporcje, kadr
- [ ] Wydruk publicznej oferty z Safari, Firefoksa i telefonu
- [ ] Włączone wtyczki: zdjęcia, ACF, strona publiczna motywu

### C. Biblioteka i umowy

- [ ] Przesłanie grafiki na kontach trzech ról
- [ ] Grafika w dwóch ofertach, usunięcie: trafia do archiwum, plik zostaje
- [ ] Umowa: generowanie, wartości, płatności, zatwierdzenie, pobranie
- [ ] Zadatek obu typów na prawdziwej sprawie
- [ ] Pracownik wpisuje ID cudzej umowy: 403
- [ ] Ekran umowy i lista na prawdziwym telefonie
- [ ] Wiersz tabeli dłuższy niż strona: czytelny komunikat PDF

### D. Automatyczne

```bash
composer test
node --test 'tests/js/*.test.mjs'
```

Testy UI i `tests/integration/*.php` działają **wyłącznie** na prywatnej kopii bazy na porcie 33177 (`STAGE-00.md`). Kolejność i wymagania są w nagłówkach plików. Nigdy nie uruchamiaj ich na bazie Local ani produkcji.

## 6. Rollback

**Przed użyciem przeczytaj:** rollback migracji usuwa umowy i szablony.

| Krok | Skutek |
|---|---|
| Przywróć kod i `public/build` do poprzedniego stanu | aplikacja wraca do starych funkcji |
| `composer install` na starym `composer.lock` | usuwa dompdf |
| `wp acorn migrate:rollback --step=1` | usuwa 5 tabel **razem z umowami i szablonami** |
| Przywróć backup bazy | wyłącznie gdy potrzebny pełny powrót; kasuje nowsze dane |

Co zostaje po cofnięciu kodu, nieszkodliwie: taksonomie i meta biblioteki, klucze dokumentu (`fields`, `polish`, `image_crop`, `layout_mode`), capabilities ról. Stary kod je ignoruje. Zmieniona kolejność realizacji zostaje. Grafiki w archiwum znów pojawią się w wyborze.

Opublikowane snapshoty nie są zmieniane przez żaden krok poza przywróceniem backupu.

## 7. Plan produkcji

**Plan, nic z tego nie zostało wykonane.** Produkcji nie badano, więc jej PHP, rozszerzenia i sposób dostarczania `vendor/` są nieznane.

### Warunki wstępne

1. Rozstrzygnięte decyzje blokujące (sekcja 8).
2. Blok A listy QA zaliczony w Local.
3. Commit po etapach, razem z `public/build`, po przeglądzie 100+ zmienionych plików.

### Bezpieczeństwo przed wdrożeniem

- **Sprawdź, czy push na `main` nie wdraża sam.** Ostatni commit nazywa się „Automatyczny deploy”, a w repozytorium nie ma żadnego workflow. Jeśli auto-deploy jest ustawiony po stronie Hostingera lub GitHuba, zwykły push wyśle kod na produkcję bez migracji. Do czasu sprawdzenia pracuj na osobnej gałęzi.
- **`key` to prywatny klucz w gicie.** Należy go uznać za ujawniony: wymienić i usunąć z repozytorium.
- **`deploy/pull-db.sh` zawiera jawne hasło produkcji.** Zmienić hasło i przenieść je poza repozytorium.
- **`deploy/deploy-db.sh` nadpisuje bazę produkcji.** Nie wolno go użyć przy tym wdrożeniu.
- `composer audit` zgłasza 23 uwagi dla 8 starszych pakietów (m.in. guzzle, symfony, flysystem). Do oceny; nie dotyczą dompdf.
- Nie wdrażać `tests/`, `docs/`, `STAGE-*.md`.

### Kolejność

1. **Rozpoznanie (tylko odczyt):** PHP, rozszerzenia z sekcji 2, MySQL, sposób wdrożenia, `upload_max_filesize`, cache LiteSpeed.
2. **Staging z kopią produkcji:** to samo co niżej, plus lista QA.
3. **Okno wdrożenia:** poza godzinami pracy, osoba z dostępem do backupów pod ręką.
4. **Backup:** baza i uploads, z próbą odtworzenia.
5. **Kod:** motyw, `public/build`, `composer install --no-dev`.
6. **Migracja:** `wp acorn migrate`, jedna nowa pozycja.
7. **Weryfikacja:** MD5 snapshotów publikacji i liczby wierszy przed i po; publiczne linki; nowa oferta; kosz.
8. **Umowy:** najpierw jedna sprawa z prawdziwego wzoru, dopiero potem zespół.
9. **Decyzja o rollbacku:** kryterium to zmiana snapshotu, kwoty albo niedziałający link publiczny. Wtedy cofnięcie kodu, bez rollbacku migracji, dopóki nie ma potrzeby.

## 8. Decyzje otwarte

Nic z tego nie zostało rozstrzygnięte. Kolumna „Przyjęto” opisuje stan kodu, a nie decyzję.

| Nr | Decyzja | Przyjęto na razie | Blokuje produkcję umów |
|---|---|---|---|
| 1 | Przesuwanie pól na mobile | układ automatyczny na telefonie | nie |
| 2 | Grafiki globalne | wspólne dla tworzących oferty | nie |
| 3 | Numeracja i statusy umów | szkic, zatwierdzona; bez numeracji | **tak** |
| 4 | Podstawa ceny umowy | kwotę wpisuje człowiek | **tak** |
| 5 | Zadatek | tryb wybiera człowiek przy każdej umowie | **tak** |
| 6 | Rozbieżność §5 i §6 | oba zapisy widoczne, ostrzeżenie | **tak** |
| 7 | DOCX 1:1 czy uproszczenie | uproszczona typografia | **tak** |
| 8 | Kto zatwierdza | Manager, Administrator | **tak** |

Dodatkowe:

| Sprawa | Stan |
|---|---|
| Poprawka zatwierdzonej umowy („Nowa wersja”) i usuwanie szkicu | brak w interfejsie, metody w usłudze są; **błędu w zatwierdzonej umowie nie da się dziś poprawić bez kodu** |
| Prawdziwy wzór umowy | **nie został przekazany ani zaimportowany**; szablon referencyjny nie istnieje |
| `SZABLON_UMOWY_NOTATKI.md`, `MODEL_ZMIENNY.md`, `CHECKLISTA_QA.md`, `README.md` roadmapy | nieprzekazane; katalog tokenów i tabela płatności mogą nie pasować do wzoru |
| Autozapis w edytorze | działa, do potwierdzenia |
| Boczne napisy w nowych ofertach | domyślnie widoczne |
| Ceny ofert są netto | założenie, do potwierdzenia |
| VAT od zadatku | do konsultacji księgowej |
