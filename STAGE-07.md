# STAGE-07 — Umowy Model

**Status: DONE** (2026-11-02, baza: commit `47855e2` + niezacommitowane wyniki etapów 00–06)

Powstał model umów: szablony, umowy, wersje, statusy, snapshot danych, dziennik i uprawnienia. Etap nie ma interfejsu, tras ani treści szablonu. Edytor i tokeny to kolejne etapy.

| Pozycja | Wynik |
|---|---|
| Migracje | **1 nowa**, tylko nowe tabele; wykonana na kopii, **nie w Local** |
| `PublicOffer::SCHEMA`, `Pages::blank` | bez zmian |
| Build | nie dotyczy: bez zmian w assetach |
| Testy PHP | `composer test`: 179 OK (573 asercje; przed etapem 169) |
| Cykl życia umowy | **67/67 OK** na kopii bazy, prawdziwe role i oferty |
| Migracja i rollback | OK na kopii: pozostałe tabele i role bez zmian (suma kontrolna) |
| Render legacy | **48/48** pozycji identycznych ze wzorcem, po migracji |
| Testy UI | nie dotyczy: etap nie ma interfejsu |

## Kryteria odbioru

| Kryterium | Jak sprawdzono | Wynik |
|---|---|---|
| Wiele umów dla jednej oferty | 3 umowy do jednej oferty, każda z własnymi wersjami | OK |
| Bez nadpisywania historii | zatwierdzona wersja odrzuca zmianę i usunięcie w modelu i usłudze; v1 nietknięta po v2; skrót wykrywa podmianę przez SQL | OK |
| Bez kasowania oferty | usunięcie umowy nie dotyka oferty; oferta w koszu zachowuje umowy; trwałe usunięcie oferty z umowami odrzucone | OK |

## Co powstało

- **Tabele:** `gst_contract_templates`, `gst_contract_template_versions`, `gst_contracts`, `gst_contract_versions`, `gst_contract_events`.
- **Wersje:** umowa i szablon mają wersje ze statusem szkic lub zatwierdzona. Naraz najwyżej jeden szkic; poprawka zatwierdzonej to nowa wersja.
- **Niezmienność:** blokada w modelu (`FreezesOnApproval`), więc obowiązuje też przyszłe kontrolery. `content_hash` obejmuje zapisane bajty treści i snapshotu.
- **Treść prawna:** umowa to kopia szablonu co do bajtu. Sprawdzono to na polskich znakach, emoji, twardej spacji, CRLF i podwójnych spacjach. Kod niczego nie czyści ani nie dopisuje.
- **Snapshot:** klient, oferta, wersja i cały wynik kalkulacji. Nie zmienia się po zmianie oferty lub klienta.
- **Dziennik:** tylko dopisywanie. Zapis szkicu ma skróty przed i po, a nie treść.
- **Usuwanie:** umowy bez zatwierdzonej wersji tylko miękko, z zatwierdzoną wcale; trwałego usuwania nie ma. Szablony tylko archiwizacja.
- **Klient z umowami** nie może zostać usunięty, także gdy oferta dostała innego klienta.

## Dostęp

| Rola | Umowy | Zatwierdzanie | Szablony |
|---|---|---|---|
| Pracownik | własne i do własnych ofert (podgląd); edycja własnych | nie | nie |
| Manager | wszystkie | tak | tak |
| Administrator | wszystkie | tak | tak |

Doszły `gst_approve_contracts` i `gst_manage_contract_templates`; `Roles::VERSION` = 5, role zsynchronizują się same. Podział jest **propozycją z decyzji 8**, istniejące uprawnienia nie miały odpowiednika. Usługi sprawdzają uprawnienia same (`AccessDenied` = 403).

## Decyzje

- **Decyzja 3:** statusy jak w zadaniu etapu, szkic i zatwierdzona. Kolumna jest tekstowa, więc „podpisana ręcznie” nie wymaga zmiany schematu. Własnej numeracji umów **nie dodano**.
- **Decyzja 4:** model jej nie rozstrzyga. Snapshot trzyma wszystkie warianty i wybrany wariant, ale nie wskazuje kwoty umowy.
- **Treść i snapshot są w LONGTEXT, nie JSON:** MySQL przebudowuje JSON, a skrót musi potwierdzać zapisane bajty.
- Decyzje 5, 6, 7 nie dotyczą tego etapu.
- Nic nie podpisuje ani nie wysyła umów. Nie wprowadzono żadnej klauzuli.

## Błędy znalezione przez testy

1. **Archiwizacja szablonu nie blokowała tworzenia umowy,** gdy obiekt szablonu odczytano wcześniej. `ContractService::create` czyta teraz stan pod blokadą, w transakcji.
2. Pierwszy zestaw testów jednostkowych tworzył modele przez `new Contract([...])`, co wymaga bazy. Poprawiono sam test.

## Korekta wcześniejszych raportów

- **„Prywatna baza zatrzymana” (etapy 04–06) nie było prawdą w całości.** Na podstawie braku nasłuchu uznałem serwer za zatrzymany, ale `mysqladmin shutdown` jest asynchroniczny, a dane kasowałem od razu.
  Zostało **6 osieroconych procesów `mysqld`**, każdy zużywał około 80% CPU. Razem zapisały **112 GB logu** w katalogu tymczasowym.
  Zatrzymano je i wyczyszczono log; `mysql-stop.sh` czeka teraz na wyjście procesu, a na końcu etapu działa 0 procesów. Procesy miały otwarte wyłącznie usunięte pliki i nie nasłuchiwały, więc wcześniejszych wyników nie podważają. MySQL z Local nie został dotknięty.
- **„49/49” w etapach 05 i 06 to 48 pozycji:** wzorzec ma pustą pierwszą linię. Wszystkie 48 pozycji było i jest zgodnych.

## Pliki

- Nowe:
  - `app/Guesstimate/Contracts/*` (11 plików)
  - `app/Guesstimate/Auth/AccessDenied.php`
  - `database/migrations/2026_11_02_000011_create_gst_contract_tables.php`
  - `tests/Unit/Contracts/ContractModelTest.php`
  - `STAGE-07.md`
- Zmienione:
  - `app/Guesstimate/Auth/Capabilities.php`, `Roles.php`
  - `app/Guesstimate/Offers/TrashService.php`, `app/Guesstimate/Clients/ClientService.php`
  - `AGENTS.md`

## Ochrona danych

- Testy szły wyłącznie na kopii z backupu `2026-11-01-stage-00`. **Zwykłe połączenie Acorna nie wskazywało kopii**, więc migrację uruchamiał skrypt z blokadą, która wymaga potwierdzenia portu kopii. `wp acorn migrate` w tej konfiguracji nie był użyty.
- Snapshoty publikacji identyczne, wersje ofert, klienci i media bez zmian.
- Publikacja 5 została na kopii wycofana przez test kosza. To zamierzone działanie kosza, a snapshot się nie zmienił.
- Realny katalog uploads ma nadal 39 plików.

## Ograniczenia

- **Kod wymaga migracji.** Bez niej aplikacja działa dalej i pokazuje baner, ale trwałe usunięcie oferty i usunięcie klienta kończą się błędem SQL, bo odpytują tabelę umów. *Korekta z etapu 08: pierwotnie napisano tu, że aplikacja pokazuje instrukcję zamiast działać. To było nieprawdą, `EnsureSchema` sprawdza tylko `gst_offer_versions`.* Nie mogłem sprawdzić bazy guesstimate w Local: odpowiedział tylko socket innej strony, odczyt bez zmian.
- Testy w tle nie obejmują prawdziwej równoległości: blokady wierszy są w kodzie, ale dwóch transakcji naraz nie uruchomiono.
- Blokada dotyczy Eloquenta. SQL wprost ją omija; wtedy `isIntact()` wykrywa zmianę, ale nikt tego jeszcze nie wyświetla. Interfejs powinien to pokazać.
- Szkic można edytować, a jego wcześniejsze treści nie są przechowywane, tylko ich skróty.
- Migawka powstaje przy tworzeniu wersji. Zmiana oferty przed zatwierdzeniem szkicu nie aktualizuje umowy; w tym etapie nie ma odświeżania.
- Dziennik chroni się w modelu, nie w bazie.
- Trwałe usunięcie oferty z umowami zwraca czytelny błąd, ale komunikatu w kontrolerze kosza nie sprawdzono w przeglądarce.

## Rollback

`wp acorn migrate:rollback --step=1` usuwa 5 nowych tabel **razem z umowami**; przetestowane na kopii. Potem cofnięcie kodu. Dodatkowe capabilities zostaną w rolach do czasu `Roles::sync()` ze starszymi definicjami. Przed rollbackiem na danych z umowami zrób backup.

## Wymagane od użytkownika

- Backup, potem `wp acorn migrate` w Local. Tak samo na produkcji, gdy kod tam trafi.
- Commit (nic nie zostało zacommitowane). Ten etap nie zmienia `public/build`.
- Potwierdzenie decyzji 8: Administrator i Manager zatwierdzają i zarządzają szablonami.
- Decyzje 3 i 4 przed kolejnymi etapami umów.

## Do zweryfikowania ręcznie

Po migracji w Local:

1. Aplikacja startuje, lista ofert i kosz działają.
2. Konto managera: zsynchronizowane nowe uprawnienia (`gst_roles_version` = 5).
3. Próba trwałego usunięcia oferty, gdy będzie miała umowę: komunikat w koszu.
