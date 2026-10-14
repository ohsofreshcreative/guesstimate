AGENTS.md

Wspólne instrukcje dla Codex, GitHub Copilot i Claude Code.
Edytuj wyłącznie ten plik. CLAUDE.md jest dowiązaniem do AGENTS.md.

Tworzenie bloków uruchamiaj zwykłą prośbą, np. „Nowy blok quote” z projektem lub screenshotem.
Nie wymagaj osobnego skilla ani komendy /nowy-blok. Jeśli brakuje nazwy lub projektu nowego bloku,
zapytaj o brakujące dane. Dalej stosuj anatomię bloku, analizę wzorców i walidację z tego pliku.
W podsumowaniu wymień zmienione pliki, sprawdzenia i wymagane komendy użytkownika.

Gdy zadanie zaczyna się od linku do frame'a w Figmie, obowiązuje skill `figma-block`
(Figma → blok → WP-CLI). Reszta zasad z tego pliku dalej obowiązuje.

⸻

Project overview

This repository contains a custom WordPress project built with Roots Sage 11.

The project is developed as a custom implementation. Existing code in the repository is the primary source of truth for architecture, conventions, naming, structure, styling, and implementation patterns.

Before implementing anything, inspect the existing project and understand how similar functionality is already implemented.

Do not introduce a new architectural pattern when an established pattern already exists in the repository.

⸻

Repository map

Theme root: `wp-content/themes/guesstimate` (Sage 11 + Acorn 5, PHP >= 8.2, namespace `App\` → `app/`).

Motyw powstał z kopii motywu strony h2otwock: bloki ACF, `variables.scss` i layout `app` to pozostałość
strony publicznej. Właściwym produktem jest aplikacja **Guesstimate** pod `/app/` — patrz sekcja
„Guesstimate — aplikacja /app/” niżej.

| Ścieżka | Zawartość |
|---|---|
| `app/Blocks/*.php` | 39 bloków ACF Composer (`Log1x\AcfComposer\Block`) |
| `app/Options/*.php` | strony opcji ACF (`OCta` → klasa `Octa`, `OLogos`, `OReviews` → klasa `Oreviews`, `OAbout`, `OEveryone`, `OGastro`) |
| `app/Fields/*.php` | grupy pól (`ThemeSettings`, `OfferFields`, `PostCategory`) |
| `app/Support/SectionClasses.php` | budowanie klas sekcji (jedna metoda: `fromMap()`) |
| `app/Support/SectionBackgrounds.php` | wspólna lista teł bloków (`choices()`) |
| `app/View/Composers/*.php` | View Composers (`App` działa na `*`, dodatkowo `Archive`, `Post`) |
| `app/Walkers/*.php` | walkery menu (`DropdownWalker`, `MobileDropdownWalker`) |
| `app/setup.php`, `app/filters.php`, `app/post-types.php` | ładowane z `functions.php` przez `collect([...])` |
| `resources/views/blocks/*.blade.php` | widoki bloków (nazwa = `$slug`) |
| `resources/views/components/*.blade.php` | **tylko** `x-button`, `x-alert`, `x-icon.arrow-up` |
| `resources/views/sections\|partials\|layouts` | header/footer/sidebar, partiale, layout `app` |
| `resources/css/variables.scss` | **cały design system** (~1400 linii) |
| `resources/css/blocks/*.scss` | styl per blok, importowany w `app.css` |
| `resources/js/blocks/*.js` | JS per blok, ładowany warunkowo z `app.js` |
| `public/build/**` | **artefakty builda są w gicie** (patrz „Build i assety”) |
| `app/Guesstimate/**` | aplikacja Guesstimate (moduły: `Auth`, `Http`, `Support`, kolejne: `Pricing`, `Offers`…) |
| `app/Providers/GuesstimateServiceProvider.php` | integracja aplikacji z WP (role, logowanie, wp-admin) |
| `routes/app.php` | trasy `/app/*` (Acorn router, grupa middleware `gst`) |
| `database/migrations/` | migracje tabel `wp_gst_*` (`wp acorn migrate`) |
| `resources/views/guesstimate/**`, `components/gst/**`, `errors/**` | widoki i komponenty aplikacji |
| `resources/css/guesstimate.css`, `resources/js/guesstimate.js` | osobne wejścia Vite aplikacji |
| `tests/Unit/**` | testy PHPUnit logiki bez WordPressa (`composer test`) |

Zanim dodasz nową abstrakcję, sprawdź `app/Support/SectionClasses.php` i `resources/css/variables.scss` —
większość rzeczy tam już jest.

**W tym motywie nie ma komponentu `x-picture`.** Obrazy wstawiaj tak jak istniejące bloki:
`wp_get_attachment_image($pole['ID'], 'rozmiar')` albo zwykły `<picture>`/`<img>` z `$pole['url']`
i `$pole['alt']` (wzorce: `values.blade.php`, `hero.blade.php`). Nie twórz `x-picture` „przy okazji”.

⸻

Guesstimate — aplikacja /app/

Wewnętrzne narzędzie OhSoFresh Creative do wyceny projektów WWW. Plan i model kalkulacji: dokument
„Guesstimate — analiza i plan MVP”. Zasady stylu bloków ACF powyżej **nie dotyczą** aplikacji —
obowiązuje to, co niżej.

**Moduły (`app/Guesstimate/`)**

| Katalog | Zawartość |
|---|---|
| `Pricing/` | silniki wyceny bez WordPressa: `BudgetEngine` 2.x (model budżetowy, nowe oferty) i `PricingEngine` 1.x (stary model, wersje historyczne); `BudgetInput`, `PricingInput`, `Stage`, `LineItem`, `Cost`, `CostTotals`, `Rates`, `Rounding`, `RiskLevel`, `CalcModel` |
| `Offers/` | modele `Offer`, `OfferVersion`, `Version/*` (snapshot wersji), `OfferService` (tworzenie, wersje, statusy, duplikaty, historia), `VersionPricing` (snapshot → PricingInput), `VersionCalculator`, `VersionCopier`, `OfferNumberGenerator`, `OfferPolicy`, `OfferQuery`, `OfferForm`, `OfferSummary`, `ResultPresenter` |
| `Offers/` (v2) | `BudgetPricing` (snapshot → BudgetInput, statystyki mapy), `BudgetSettings`, `BudgetPresenter`, `LegacyConverter` (kopia starej wersji → model budżetowy), `OfferContent` (treści + czyszczenie HTML), `PublicOffer` (snapshot dla klienta), `PublicationService`, `Publication`, `PublicationStatus`, `ViewTracker`, `OfferView` |
| `Sitemap/` | `PageList` (uproszczona mapa — v2); `SitemapPayload`, `SitemapService`, `SitemapStats` (stary edytor drzewa, tylko wersje historyczne) |
| `Document/` | oferta online jako dokument stron A4 poziomo: `Pages` (8 typów stron, układy `PAGE_LAYOUTS`, domyślne nagłówki), `DocumentSettings` (ustawienia dokumentu wersji, `fitHash`), `DocumentForm` (teksty — krok 5 i edytor; `design()` — układ, tło, zdjęcie strony, nagłówek, widoczność), `DocumentEditor` (edytor wizualny: stan, walidacja, dane widoku), `DocumentBuilder` (część snapshotu publikacji; tryb edytora z ukrytymi stronami), `DocumentView` (render, wartości domyślne dla starszych snapshotów), `DocumentDesigner` (biblioteka teł do wyboru), `MediaRefs` (ochrona załączników używanych w publikacjach), `Background`, `CaseStudy`, `Contacts`, `Media` (biblioteka mediów WP, `browse()`), `Markup` (`**akcent**`) |
| `Ai/` | `AiService` (limity, dziennik `gst_ai_requests`), `OpenAiClient`, `FeatureEstimator`, `ContentWriter` (treści, krótkie teksty stron `text:<pole>`, „Dopasuj tekst do strony” — akcja `fit`; zawsze propozycja do akceptacji), `OfferContext` (dane bez danych osobowych, cen i notatek) |
| `Integrations/` | `HttpJson` (wp_remote_request + timeouty, `IntegrationException::$apiCode` = ECODE z odpowiedzi), `ClickUp/*` — OAuth 2.0 per użytkownik, tylko odczyt: `OAuthApp` (Client ID/Secret/Redirect URI ze stałych `GST_CLICKUP_*` w wp-config lub env), `ClickUpAccount` (start/callback OAuth, `state`, token, konfiguracja, „Sprawdź połączenie”, rozłączenie), `ClickUpConnection` (model `gst_clickup_connections`), `ClickUpSync` (synchronizacja partiami w tle do `gst_clickup_index`), `ClickUpDirectory` (wyszukiwanie w indeksie użytkownika, import), `ClickUpClient` (Bearer), `ClickUpMapper` |
| `Clients/`, `Library/`, `Settings/` | klienci (+ `Nip`), biblioteka funkcjonalności i typów podstron, ustawienia (`SettingsRepository`, `Defaults`, `RatesValidator`) |
| `Dashboard/` | `DashboardStats` |
| `Auth/`, `Http/`, `Support/` | role i capabilities, kontrolery i middleware, formatowanie/parsowanie/walidacja (`Format`, `Input`, `Validation`, `Flash`, `SchemaStatus`) |

**Zasady wyceny (nie zmieniać bez decyzji biznesowej)**

Model budżetowy (silnik 2.x, `calc_model = budget`, wszystkie nowe wersje i duplikaty):

- Cena wariantu = suma godzin × stawka wariantu. Godziny: zakres UX + podstrony mapy (każda = 1 jednostka,
  godziny UI/Dev z oferty lub nadpisane) + jednostki rodzaju projektu (osobna pozycja, nie adresy URL)
  + funkcjonalności + prace dodatkowe.
- Warsztaty Discovery to kwota stała **zawarta** w cenie; reszta jest prezentowana jako podział UX/UI/Dev
  (domyślnie 20/30/50). UX i UI zaokrąglane do kroku, Development przejmuje resztę — suma = cena.
- Koszty zewnętrzne (jednorazowe i cykliczne) są poza ceną i bez podziału.
- Wybrany wariant (`selected_variant`) widzi klient; panel pokazuje wszystkie trzy.
- Wersje w starym modelu (hourly/percent) liczą się po staremu i są tylko do odczytu; nowa wersja/duplikat
  przechodzi przez `LegacyConverter` (dane wersji źródłowej się nie zmieniają).

Wspólne zasady:

- Trzy warianty ryzyka = trzy niezależne stawki (Low ≤ Mid ≤ High, > 0, maks. 2 miejsca po przecinku,
  limit techniczny 100 000 PLN/h). Godziny są wspólne; bez mnożników ryzyka.
- Widełki godzin [min, max] dają kwotę od–do w każdym wariancie. Kolumna „Wycena od–do” = min Low – max High.
- Szablon liczy się raz; podstrona indywidualna ma własny szablon; treść = sztuki × godziny/szt.
- UX wyłącznie z zakresu UX oferty (Zoptymalizowany / Pełny), nigdy z szablonów.
- Model procentowy = calc.xlsx: godziny × stawka, kwota stała Discovery w środku, reszta wg udziałów.
- Oferta i jej wersje mają własną kopię stawek, UX, etapów i zaokrągleń. Ustawienia globalne nigdy nie
  przeliczają zapisanych ofert. Wysłanie zamraża wersję; zmiany = nowa wersja.
- Kwoty w groszach (`BIGINT`), godziny `DECIMAL(10,2)`, obliczenia na `brick/math`, HALF_UP.

**Infrastruktura**

- **Routing:** Acorn router, trasy w `routes/app.php`, rejestrowane w `functions.php`
  (`withRouting(using: …)` w grupie `gst`). Nie używamy grupy `web` (sesje Laravela, APP_KEY).
  Publiczna oferta: `routes/public.php` w grupie `gst.public` (bez logowania i nonce, no-store, noindex) —
  `/offer/{token}/` (token 32 znaki) i `POST /offer/{token}/view` (sygnał wyświetlenia z przeglądarki).
- **Sekrety:** klucz OpenAI w `Support\Secrets` (libsodium, klucz z soli WP). Nigdy nie
  przekazuj ich do widoków ani JS — tylko `describe()` (maska). Wywołania AI/ClickUp wyłącznie z serwera.
  Tokeny OAuth ClickUp: `Secrets::seal($token, 'clickup-oauth-user-{id}')` w `gst_clickup_connections.token_ciphertext`
  (`$hidden` w modelu; szyfrogram związany z użytkownikiem). Client Secret tylko w wp-config/env — nigdy w bazie i repo.
  Stary wspólny Personal API Token (`clickup_token`) nie jest używany; w ustawieniach można go usunąć.
- **ClickUp (OAuth per użytkownik):** „Moje integracje” (`/app/moje-integracje`, `ClickUpController`) — każdy użytkownik
  z `CREATE_OFFERS` łączy własne konto. Start: POST + nonce → `state` (32 B, w transiencie tylko sha256, 10 min,
  jednorazowy, przypisany do użytkownika) → `https://app.clickup.com/api?client_id&redirect_uri&state`. Callback
  `GET /app/integrations/clickup/callback` → `POST /api/v2/oauth/token` (JSON `client_id`, `client_secret`, `code`).
  Token wg dokumentacji nie wygasa i nie ma refresh tokenu: 401 z OAUTH_019/021/025/077 → `reconnect_required`
  („Połącz ponownie”), OAUTH_023/026/027/029–045 → brak autoryzacji Workspace (połączenie zostaje). Brak endpointu
  revoke — „Rozłącz” usuwa token i indeks lokalnie. Konfiguracja per użytkownik w `config` (team, space, listy
  z nazwami, mapowanie, `field_labels`). Synchronizacja (`ClickUpSync`, jeden silnik dla przycisku, harmonogramu
  i pierwszej synchronizacji po zapisaniu konfiguracji): partia = 1 strona API (100 zadań), stan w `sync_state`.
  Tryby: `incremental` (`date_updated_gt` = znacznik listy − 5 min; aktualizuje, nie usuwa) i `full` (raz na dobę,
  po zmianie konfiguracji, dla list bez znacznika; po kompletnym przebiegu usuwa wpisy usunięte / przeniesione
  poza wybrane listy — tylko dla list pobranych w całości). Znaczniki per lista w `list_state` przesuwają się po
  poprawnym pobraniu listy. Blokada per użytkownik `add_option('gst_cu_sync_lock_{id}')` (90 s), 429 → `waiting`
  60 s, sieć/timeout/5xx → 3 próby, potem `error` (harmonogram wznawia tę samą partię); 401 → reconnect_required.
  Harmonogram (`ClickUpScheduler`): `next_sync_at` co 15 min, `runDue()` planuje należne i wykonuje partie
  round-robin (globalna blokada `gst_cu_runner_lock`). Wywołanie: systemowy cron → `wp guesstimate clickup sync-due`
  (rezerwa: WP-Cron `gst_clickup_tick` co 5 min; awaryjnie odpytywanie statusu w „Moich integracjach”).
  Diagnostyka: `wp guesstimate clickup status`, `wp guesstimate clickup sync --user-id=ID [--full]`
  (`--user` to globalny parametr WP-CLI — nie używać).
  Wyszukiwarka kreatora czyta tylko `gst_clickup_index` zalogowanego użytkownika (bez zapytań do ClickUp).
  Import: dopasowanie po `clickup_id` → wybór użytkownika → unikalny NIP → podobni (409 `needs_choice`) → nowy;
  uzupełnia tylko puste pola. Klient usunięty w Guesstimate nigdy nie jest przywracany automatycznie: 409 z `deleted`
  → „Przywróć klienta” (`restore`, tylko MANAGE_CLIENTS) albo „Zaimportuj jako nowego” (`create_new`; powiązanie
  z zadaniem przechodzi na nowy rekord). Klienci są wspólni, połączenia i indeksy prywatne.
- **Klienci — usuwanie:** soft delete (`deleted_at`, `deleted_by`), zakładka „Usunięci” z przywracaniem
  (`MANAGE_CLIENTS`: Administrator, Manager). `ClientService::delete()` w transakcji z `lockForUpdate` odmawia,
  gdy klient ma jakąkolwiek ofertę (`Offer::withTrashed`, także w koszu); tworzenie / zmiana klienta oferty
  wywołuje `ClientService::lockActive()` (ten sam wiersz) → brak wyścigu. Usunięcie nie dotyka ClickUp ani indeksów.
- **Logowanie:** `/login`, `/logout`, `/forgot-password`, `/reset-password` (`routes/auth.php`, grupa `gst.guest`,
  `AuthController`, `Auth\AppLogin`). `wp_signon()` (e-mail lub login, „Nie wylogowuj mnie” = 14 dni WordPressa),
  reset hasła: `retrieve_password` / `check_password_reset_key` / `reset_password` (e-mail z linkiem do
  /reset-password przez filtr `retrieve_password_notification_email`). `login_url` → /login (filtr dodawany na `init`,
  bo Acorn przy starcie pomija routing dla ścieżki `wp_login_url()`), wp-login.php GET (logowanie, lostpassword, rp) →
  nasze ekrany, pozostałe akcje natywnie; awaryjnie `/wp-login.php?native=1`. `LoginThrottle` (filtr `authenticate`:
  5 prób na IP+login, 20 na IP / 15 min — także wp-login.php i XML-RPC). Przekierowania: `AppLogin::safeRedirect()`.
- **Middleware `gst`:** `UnslashInput` (cofa `wp_magic_quotes` — bez tego cudzysłowy dostają ukośniki),
  `PreventCaching` (no-store + LiteSpeed no-cache), `Authenticate` (zalogowany użytkownik WP z
  `gst_access_app`), `EnsureSchema` (instrukcja zamiast błędu, gdy brak tabel), `VerifyNonce` (żądania
  nie-GET: pole `_gst_nonce` / nagłówek `X-GST-Nonce`, akcja `gst_app`), `SubstituteBindings`.
  Uprawnienia modułu na trasie: `->middleware('gst.can:' . Capabilities::X)`, uprawnienia do ofert:
  `OfferPolicy` w kontrolerze i w zapytaniach (`scope()`).
- **Role:** `Roles::definitions()` (Administrator, `gst_manager`, `gst_employee`). Po zmianie podbij
  `Roles::VERSION` — role zsynchronizują się na `init`.
- **Formularze:** bez sesji Laravela. Błąd walidacji → ponowny render widoku (422) z `$errors` i `$old`,
  sukces → przekierowanie z komunikatem `Flash` (transient użytkownika). Komunikaty walidacji po polsku
  w `Support\Validation`.
- **Baza:** migracje Acorna w `database/migrations`, tabele `gst_*` (prefiks WP dokłada `wp_`). Query
  builder dokłada prefiks także do aliasów — nie używaj `selectRaw` z nazwami `alias.kolumna`.
- **Widoki:** layout `guesstimate.layouts.app`, komponenty `x-gst.*`, tokeny wyłącznie w `@theme`
  w `resources/css/guesstimate.css` (`ink`, `canvas`, `surface`, `line`, `rail`, `accent`, `risk-low|mid|high`,
  `status-*`, `rounded-control|panel`, `shadow-panel`). Bez wartości arbitralnych i hexów w Blade.
  W jednym pliku Blade nie mieszaj `@php(...)` z blokami `@php … @endphp` (błąd parsera Blade).
  Wrappery tabel: `relative overflow-x-auto` (bez `relative` elementy `sr-only` rozpychają stronę na mobile).
- **JS:** Alpine (`resources/js/guesstimate/*.js`): `pageList`, `rowList`, `calcPreview`, `clientPicker`,
  `featurePicker`, `richText` (lekki edytor contenteditable), `offerContent`, `copyText`, `clickupIntegration`,
  `documentEditor` (edytor wizualny dokumentu).
  Przeglądarka nie liczy cen — podgląd w kroku 4 pyta serwer (`POST /app/oferty/{id}/podglad`).
- **Oferta online (dokument):** strony `public/partials/pages.blade.php` + `public/pages/*.blade.php` — jedna
  implementacja dla oferty publicznej i edytora (`html[data-mode]`: `public` | `editor` | `thumbs`). Wejścia Vite
  `resources/css/offer.css` (tokeny `night`, `paper`, `orange`, `viewer`…, `--aspect-a4`, font Sora z `resources/fonts/sora`)
  i `resources/js/offer.js`. Dwa sposoby prezentacji: **arkusz** (`.osf-sheet` — desktop od 1180 px, edytor, miniatury):
  proporcje A4 297/210, typografia i odstępy w `cqw` (wariant `sheet:` zamiast `md:`; w `.osf-page` `--spacing` jest
  w cqw, więc `mt-4`, `size-6` skalują się ze stroną — strona wygląda tak samo w każdej skali); **układ płynny**
  (telefon, tablet): dowolna wysokość, tekst w px. Nadmiar treści na arkuszu → strona „cd.” tego samego typu;
  wyjątek `Pages::FIT` („Sytuacja”): poziomy `data-fit-level` 0–5 (odstępy → interlinia → tekst do min. 1.06cqw),
  potem `data-overflow` (nic nie jest ucinane, publikacja blokowana). Układy: `osf-media-right|left` (zdjęcie strony
  `osf-media` — osobny element, nie tło), `full`, `tiles`. Grafiki tylko z biblioteki mediów WP; snapshot zapisuje
  ID, adresy, wymiary zdjęć i teł, układy, nagłówki, kontakt i realizacje.
- **Kompozycja stron:** na arkuszu logo zostaje u góry, a główny blok (`osf-column` → `osf-center`: nagłówek + treść
  jako jedna grupa) jest wyśrodkowany w pionie marginesami `auto` (przy dużej ilości treści marginesy maleją do 0,
  nic nie jest ucinane; podział „cd.” i dopasowanie działają bez zmian); dolny odstęp grupy = środek optyczny.
  Stopki pozycjonowane absolutnie (np. „Oferta ważna”) nie przesuwają się. Pomarańcz tekstów: token
  `--color-orange` (#F49E02) w `offer.css`; gradient dekoracyjny ma osobny `--color-gradient-start`. Usługi dodatkowe:
  minimalistyczna tabela `osf-table-row` (tylko poziome separatory `night-line`).
- **Edytor dokumentu** (`/app/oferty/{id}/dokument` i krok 6 kreatora): `DocumentController` + widok
  `guesstimate.editor.index` (układ `layouts.editor` bez menu bocznego) + Alpine `documentEditor`. Stan = istniejące pola
  wersji (`content`, `document`) — te same co w kroku 5. Podgląd LIVE: `POST …/dokument/podglad` renderuje strony dla
  niezapisanego stanu (nic nie zapisuje), ramki `…/dokument/ramka(?tryb=miniatury)` podmieniają strony przez
  `window.gstDoc.render()` (bez przeładowania). Zapis tylko przyciskiem („Zapisz”), bez przeliczania kalkulacji.
  Pomiar dopasowania z przeglądarki trafia do `document.fit_report` (ze skrótem stanu) — `PublicationService::blockers()`
  blokuje publikację przepełnionej strony. „Wersja robocza różni się od publikacji” = inny `version_id` albo inny
  odcisk snapshotu (`PublicationService::fingerprint`). Zamrożona wersja: edytor tylko do odczytu + „Utwórz wersję roboczą”.
- **Zdjęcia w publikacjach:** `gst_media_refs` (zapisywane przy publikacji) + filtr `pre_delete_attachment` — załącznika
  używanego w publikacji nie da się usunąć z biblioteki mediów (bez kopiowania plików). Referencje znikają przy trwałym
  usunięciu oferty.
- **Testy:** `composer test` — oba silniki (w tym przykład z calc.xlsx i brak podwójnego liczenia), mapowanie
  wersji, mapa strony, stawki, NIP, statusy, formatowanie, mapowanie ClickUp, estymator, skracanie IP.
- **Kolejność opcji:** MySQL normalizuje kolumny JSON (klucze sortowane po długości, potem alfabetycznie),
  więc kolejność z `gst_settings`/snapshotów jest przypadkowa. Kolejność rodzajów projektu i zakresów UX
  zawsze z kodu: `OfferVersion::SELECTABLE_TYPES` (Strona, Katalog, Sklep, Landing, Inny; `webapp` tylko dla
  starych ofert) przez `BudgetSettings::types()`, `BudgetSettings::uxScopes()` (Zoptymalizowany, Pełny).
  Domyślnie nowa oferta: `website` + `optimized`. „Inny” ma własną liczbę jednostek z kreatora
  (`custom_units` → `budget.type_units`, 0–999), nie z ustawień.
- **Kosz ofert:** soft delete (`deleted_at`, `deleted_by`), `TrashService` (trash/restore/forceDelete w
  transakcji), `TrashController`, trasy `oferty/kosz…`. Przeniesienie do kosza wycofuje publikację (link 404,
  historia zostaje); przywrócenie nie publikuje ponownie. Uprawnienia: `gst_manage_trash` (manager, admin —
  cały kosz, przywracanie), `gst_delete_offers` (tylko admin — usuwanie na stałe z wpisaniem USUŃ);
  pracownik przenosi i cofa tylko własne. Trwałe usunięcie kasuje wersje (+ tabele `gst_v_*`), publikacje,
  wyświetlenia i historię; klient i media WP zostają. Nowa tabela powiązana z ofertą → dopisz ją w
  `TrashService::forceDelete()`.
- **Blade + komponenty:** dyrektywy (`@js`, `@json`) nie działają w atrybutach `<x-gst.*>` — przekaż dane
  przez `data-*` z `{{ }}` albo użyj zwykłego elementu HTML.
- **Język:** interfejs po polsku; URL-e modułów po polsku (`/app/oferty`), nazwy tras i kod po angielsku.

⸻

Komendy

Menedżer pakietów: **yarn** (`.yarnrc.yml`, `nodeLinker: node-modules`). W repo leżą też
`package-lock.json`, `pnpm-lock.yaml` i `pnpm-workspace.yaml` — są nieaktualne, nie używaj npm ani
pnpm i nie aktualizuj tych plików.

```bash
yarn dev      # Vite dev server: guesstimate.local:6016 (strictPort, HMR przez ws)
yarn build    # produkcyjny build do public/build
```

```bash
composer install
composer test             # testy PHPUnit (tests/Unit)
wp acorn migrate          # migracje tabel aplikacji wp_gst_*
wp acorn acf:cache        # przebuduj cache pól ACF po zmianach w app/Blocks|Fields|Options
wp acorn view:clear       # gdy Blade zwraca stary widok
```

Node >= 20.

Build (`yarn build`) i komendy Acorn (`wp acorn migrate`, `wp acorn acf:cache`, `wp acorn view:clear`) uruchamia użytkownik
samodzielnie — nie wykonuj ich automatycznie. Jeśli zmiana tego wymaga, poinformuj o tym w podsumowaniu
zamiast odpalać komendę.

Cache pól ACF **nie jest obecnie zbudowany**, więc nowe bloki rejestrują się dynamicznie i działają
od razu po dodaniu plików. `acf:cache` jest potrzebne dopiero, gdy ktoś ten cache wygeneruje.

WP-CLI (odczyt treści, import mediów, wstawianie bloków) możesz uruchamiać samodzielnie. Strona stoi
na Local i wymaga podania socketu MySQL:

```bash
php -d "mysqli.default_socket=$HOME/Library/Application Support/Local/run/<ID>/mysql/mysqld.sock" \
  /opt/homebrew/bin/wp --path=/Users/Arek/Websites/guesstimate/app/public <komenda>
```

`<ID>` strony guesstimate w Local: `ls ~/Library/Application\ Support/Local/run/` (do uzupełnienia).
Local musi mieć uruchomioną stronę, inaczej socketu nie ma. `wp db query` nie zadziała (brak binarki
`mysql`) — używaj `wp eval` / `wp eval-file`.

Nie uruchamiaj `vendor/bin/pint` na całym repo — w projekcie **nie ma `pint.json`**, więc Pint użyje
presetu Laravel (4 spacje) i przeformatuje wszystkie pliki PHP, które są pisane **tabami**
(patrz „Formatowanie i język kodu”). Pint co najwyżej na własnym, nowym pliku.

⸻

Anatomia bloku ACF (najczęstsze zadanie w tym repo)

Nazwa bloku jest zawsze jednowyrazowa, lowercase, bez myślników i podkreśleń — ta sama forma
w klasie PHP (`About`, `Whyus`, `Values`), `$slug` (`about`, `whyus`, `values`), pliku blade
(`about.blade.php`) i klasie CSS sekcji (`b-about`). Tak jest we wszystkich 39 istniejących blokach.

Nazwy bloku nie wymyślaj samodzielnie — gdy zadanie dotyczy nowego bloku (zwłaszcza na podstawie
screena/designu), użyj nazwy podanej przez użytkownika w prompcie. Jeśli nazwa nie została podana,
zapytaj, zamiast zgadywać.

Blok = 3–4 pliki:

1. `app/Blocks/Nazwa.php` — klasa `App\Blocks\Nazwa extends Log1x\AcfComposer\Block`
2. `resources/views/blocks/nazwa.blade.php` — widok (nazwa pliku = `$slug`)
3. `resources/css/blocks/nazwa.scss` — **twórz zawsze**, nawet pusty, z gotowym pustym selektorem
   `.b-<slug> { }` (patrz np. `resources/css/blocks/map.scss`)
4. `resources/js/blocks/nazwa.js` — opcjonalnie, **musisz** dodać warunkowy import w `resources/js/app.js`

Import nowego pliku scss w `resources/css/app.css` dodawaj zawsze na dole listy pod komentarzem
`/*-- USED ---*/` i nad `/*-- NOT USED ---*/` (nowy blok jest od razu używany, więc trafia do sekcji
USED, a nie do listy nieużywanych na dole pliku).

Rejestracja jest automatyczna (ACF Composer skanuje `app/Blocks`) — nie dopisuj bloków ręcznie
do `functions.php` ani do `ThemeServiceProvider`.

Klasa PHP — obowiązkowy szkielet

Wzorzec referencyjny: `app/Blocks/Hero.php` (grupa) i `app/Blocks/Values.php` (grupa + repeater).

```php
public $name = 'Hero';          // nazwa widoczna w edytorze
public $description = 'hero';
public $slug = 'hero';          // lowercase, bez spacji; = nazwa pliku blade
public $category = 'formatting';
public $icon = 'align-full-width';
public $keywords = ['tresc', 'zdjecie'];
public $mode = 'edit';
public $supports = [
	'align' => false,
	'mode' => true,
	'jsx' => true,
];
```

W `fields()`:

- `->setLocation('block', '==', 'acf/<slug>')` — zawsze, inaczej pola się nie pokażą,
- nie dodawaj pola `block-title` ani akordeonu „Treści bloku”; definicję treści zaczynaj od zakładki,
- zakładka treści: najczęściej `->addTab('Elementy', ['placement' => 'top'])` (17 bloków),
  spotykane też `'Treści'` / `'Treść'`; dla kafelków `->addTab('Kafelki', ['placement' => 'top'])`,
- główne pola w grupie `g_<slug>` (`->addGroup('g_hero', ['label' => ''])` … `->endGroup()`),
- powtarzalne w repeaterze `r_<slug>` (`'layout' => 'table'`) … `->endRepeater()`,
- unikaj pól luźnych poza grupą/repeaterem — luźne pole tylko gdy naprawdę nie ma co grupować,
- **gdy blok ma zarówno grupę, jak i repeater — każda może dostać własny Tab** (wzorzec: `Faq.php`,
  `Cards.php`, `Tabs.php`),
- **jeśli poza kafelkami jest tylko jeden nagłówek / jedna główna treść, nie rozbijamy tego na osobny tab**;
  w prostych blokach cały układ mieści się w jednej grupie `g_<slug>` z repeaterem `r_<slug>` w środku
  (wzorzec: `Values.php`),
- **ostatnia zakładka zawsze**: `->addTab('Ustawienia bloku', ['placement' => 'top'])` z polami
  `section_id`, `section_class`, przełącznikami `nolist` / `flip` / `wide` / `nomt` / `gap`
  i selectem `background`.

Nagłówek i opis — zawsze ten sam typ pola, niezależnie od tego, czy pole jest w grupie czy w repeaterze:
- nagłówek zawsze jako zwykły tekst: `->addText('header', ['label' => 'Nagłówek'])`,
- opis/treść zawsze jako WYSIWYG: `->addWysiwyg('text', ['label' => 'Treść', 'tabs' => 'all', 'toolbar' => 'full', 'media_upload' => true])` —
  nie używaj `addTextarea` do opisu.

Etykiety pól po polsku, nazwy pól po angielsku. Przełączniki zawsze
`'ui' => 1, 'ui_on_text' => 'Tak', 'ui_off_text' => 'Nie'`.

Lista teł jest zdefiniowana globalnie w `app/Support/SectionBackgrounds.php`.
W blokach dodaj `use App\Support\SectionBackgrounds;` i korzystaj z helpera:

```php
'choices' => SectionBackgrounds::choices(),
```

Nie kopiuj listy do kolejnych bloków. Nowe warianty dodawaj w `SectionBackgrounds::choices()`.
Jeśli blok celowo pomija wybrane tła, przekaż ich klucze jako wykluczenia, np.
`SectionBackgrounds::choices(['section-gray'])`. Zachowaj nazwę pola `background` oraz jego
wartość domyślną `none`. Przy korzystaniu z cache pól ACF po zmianie wspólnej listy użytkownik
musi ponownie uruchomić `wp acorn acf:cache`.

`with()` — obowiązkowy fragment

```php
'background' => get_field('background') ?: 'none',

$fields['sectionClass'] = SectionClasses::fromMap($fields, [
	'flip' => 'order-flip',
	'wide' => 'wide',
	'nomt' => '!mt-0',
	'gap'  => 'wider-gap',
	// + mapowania specyficzne dla bloku, np. 'nolist' => 'no-list'
]);
```

W tym motywie **nie ma** opcji `default_block_background` — nie dopisuj fallbacku do opcji motywu.
Booleany rzutuj przez `(bool) get_field(...)`. Repeater wyciągaj osobno, tak jak w `Values.php`:
`'values' => get_field('g_values')['r_values'] ?? []`.

Widok Blade — obowiązkowy szkielet

```blade
<!--- nazwa --->

<section
	data-gsap-anim="section"
	@if(!empty($section_id)) id="{{ $section_id }}" @endif
	@class([ 'b-nazwa relative -smt' ,
	$sectionClass=> filled($sectionClass),
	$section_class => filled($section_class),
	$background => filled($background) && $background !== 'none',
	])>

	<div class="__wrapper c-main">
		{{-- treść --}}
	</div>
</section>
```

- klasa główna sekcji: `b-<slug>`,
- elementy wewnętrzne w konwencji `__nazwa`: `__wrapper`, `__col`, `__top`, `__content`, `__img`,
  `__card`, `__txt`, `__inside`, `__header`, `__prev`, `__next`,
- `{{ }}` dla tekstu, `{!! !!}` **tylko** dla WYSIWYG,
- każde opcjonalne pole owinięte w `@if (!empty(...))`.

**Odstęp i tło sekcji — zawsze przez ten sam mechanizm, nigdy na sztywno:**

- w bazowej liście klas sekcji ma być **zawsze `-smt`**, nigdy `-spt`/`-spb` obok niego —
  `-smt` to jedyny odstęp, jaki blok wymusza sam z siebie,
- **nie dopisuj do bazowej listy klas** żadnego koloru tła (`bg-third`, `bg-background-navy`,
  `bg-white` itd.), nawet jeśli makieta pokazuje konkretny, stały kolor. Tło daje wyłącznie
  select „Kolor tła" przez `$background => filled($background) && $background !== 'none'` —
  to jest jedyny kanał, przez który blok dostaje tło,
- wybrany `.section-*` (np. `.section-dark`, patrz `variables.scss`) **sam niesie własny
  padding** (`padding: var(--smt) 0`) — dlatego bazowa klasa nie dokłada `-spt`/`-spb` na
  wypadek wybrania tła: dublowałoby to odstęp,
- jeśli żaden z gotowych wpisów `choices` w select nie pasuje kolorem do makiety, **nie
  wymyślaj nowej klasy `bg-*` w Blade** — zgłoś to w podsumowaniu jako brakujący wariant
  tła w `variables.scss` zamiast obchodzić select.

⸻

Core principle

Istniejący kod jest głównym wzorcem architektury i konwencji. Przed zmianą przeczytaj podobne implementacje, zrozum ich działanie i wykorzystaj istniejące rozwiązania. Nowe podejście wprowadzaj tylko przy braku odpowiedniego wzorca. Rozbieżności instrukcji z kodem oceniaj świadomie; nie kopiuj błędów.

⸻

Technology stack

Projekt może używać WordPress, Sage 11, Acorn, Blade, Tailwind, Vite, ACF Composer, WooCommerce, CF7, Swiper i JavaScript. Przed użyciem sprawdź obecność danej technologii. Nie instaluj zbędnych zależności ani nie zastępuj istniejącej biblioteki bardziej znajomą.

⸻

Before writing code

Przed implementacją sprawdź strukturę repozytorium, Blade, ACF/PHP, komponenty, SCSS i JS. Rozpoznaj nazewnictwo, kontenery, grid, odstępy, breakpointy, typografię, assety, obrazy i przyciski. Dla projektu graficznego znajdź podobny istniejący UI. Nie generuj plików przed analizą.

⸻

Reuse before creation

Wyszukaj i wykorzystaj istniejące komponenty, helpery, tokeny i wzorce: przyciski, nagłówki, kontenery, karty, ikony, formularze, slidery, akordeony, modale, breadcrumbs, menu, obrazy, ACF i responsywność. Nie twórz niemal identycznych wersji.

⸻

Working from screenshots or designs

Screen lub projekt jest specyfikacją: zachowaj układ, hierarchię, proporcje, odstępy, wyrównanie i kadrowanie. Używaj istniejących tokenów oraz ograniczeń sekcji Styling. Nie wymyślaj dekoracji ani nie upraszczaj istotnych szczegółów tylko dla wygody.

Makiety w Figmie są nazwane konwencją motywu (`__wrapper`, `__top`, `__cards`, `__card`, `__content`) —
trzymaj się nazw z warstw zamiast wymyślać własne.

⸻

Responsive implementation

Stosuj breakpointy projektu. Z desktopowego projektu wyprowadź logiczny układ mobilny: czytelna hierarchia, brak overflow, naturalne ułożenie kolumn, sensowne odstępy, widoczne CTA i odpowiednie kadrowanie. Nie skaluj wszystkiego proporcjonalnie i nie dodawaj zbędnych breakpointów.

⸻

WordPress

Follow WordPress best practices while respecting the architecture already established by Sage and the repository.

Avoid:

* unnecessary global state,
* unnecessary queries,
* hardcoded URLs,
* hardcoded attachment URLs,
* hardcoded site-specific paths,
* duplicated WordPress queries,
* unnecessary plugin dependencies.

Use existing project helpers and abstractions where available.

Escape output appropriately.

Sanitize user-controlled input appropriately.

Do not modify WordPress core files.

⸻

Sage 11 / Blade

Sprawdź podobne widoki. Używaj istniejących komponentów, partiali i View Composerów. Widoki mają być czytelne, z minimalną logiką biznesową. Nie wprowadzaj konkurencyjnego sposobu przygotowania danych.

⸻

ACF and blocks

Przestrzegaj wzorców istniejących pól: nazw i kluczy, grup, tabów, warunków, wartości domyślnych, rejestracji, podglądów, pobierania danych, obrazów, linków, repeaterów i flexible content. Dodawaj tylko potrzebną edytorowi konfigurację treści; decyzje strukturalne i wizualne pozostaw w kodzie.

Bloki ACF działają w `acf_block_version 3` / `api_version 3`, z `expanded_editor_buttons` i
`hide_fields_in_sidebar` (filtr `acf/register_block_type_args` w `app/setup.php`).
Block bindings są wyłączone (`acf/settings/enable_block_bindings` → `__return_false`).

⸻

Styling

Use the styling system already established in the project.

### Minimum CSS — Tailwind w Blade jest domyślne

Dodawaj minimalną liczbę klas Tailwind potrzebną do układu i działania. Nie dopisuj klas redundantnych, dekoracyjnych, zbędnych nadpisań ani wariantów „na zapas”. Mniej klas nie oznacza przenoszenia ich do SCSS — najpierw usuń zbędną stylizację, a potrzebny układ zapisz utility classes. Plik SCSS bloku i jego import twórz zawsze, nawet gdy selektor pozostaje pusty.

Nowe bloki implementuj przede wszystkim klasami Tailwind bezpośrednio w widoku Blade. Dotyczy to
w szczególności:

* `display`, grid i flex,
* szerokości, wysokości oraz `min-height` / `max-width`,
* pozycjonowania i `inset`,
* paddingów, marginesów i gapów,
* kolorów i podstawowych struktur przestrzennych,
* kolejności elementów,
* breakpointów i całego zachowania responsywnego.

Ważne: nie dodawaj niestandardowych klas CSS/SCSS do nowych bloków tylko po to, żeby odwzorować
screen. Dla mockupów lepiej zrobić prosty układ i zostawić resztę użytkownikowi do dopracowania.
Nie twórz dekoracyjnych klas typu `__shape`, `__glow`, `__icon`, `__grid` z osobnym SCSS, jeśli nie jest
to konieczne dla poprawnego działania. Jeśli element dekoracyjny ma być w markupie, dodaj tylko prosty
semantyczny znacznik bez osobnej stylizacji, a nie cały zestaw customowych klas i reguł.

Nie dodawaj automatycznie `gap-6` do wrapperów treści ani jako jednakowego odstępu między wszystkimi elementami bloku. Odstępy między nagłówkiem, treścią, podpisem i innymi elementami są dobierane indywidualnie przez użytkownika. Nie zastępuj `gap-6` inną arbitralnie wybraną klasą `gap-*`; dodawaj takie odstępy tylko na wyraźną prośbę użytkownika lub zgodnie z ustalonym wzorcem konkretnego układu.

Zamiast jednego `gap-*` na całym kontenerze `flex-col` (nagłówek + treść + przyciski razem), odstęp pod
konkretnym elementem dawaj przez dedykowany utility na tym elemencie: `m-header` na nagłówku (h1–h6 /
`text-hX`), `m-title` na labelu nad nagłówkiem, `m-img` na obrazku, `m-btn` na wrapperze `.inline-buttons`
otaczającym `<x-button>` (nie na samym `<x-button>`). Wzorzec: `hero.blade.php`, `checks.blade.php`,
`history.blade.php`. Różne elementy mają różne, celowo odmienne odstępy (16 / 16 / 40 / 40px) — nie da
się tego oddać jednym wspólnym `gap-*`.

**Zasada absolutnego braku mikro-typografii i klas ozdobnych:**
Nie dodawaj w szablonie żadnych klas związanych z dokładnym rozmiarem pisma (`text-sm`, `text-xs`, `text-lg`), wagą czcionki (`font-medium`, `font-semibold`, `font-bold`), wysokością linii (`leading-relaxed`, `leading-normal`) czy zaokrągleniami oraz mikromarginesami tekstowymi, jeśli nie zostaniesz o to wyraźnie poproszony. Tworzymy wyłącznie czysty szkielet strukturalny (grid, flex, gap, paddingi sekcji, bordery i kolory tła). Cała typografia i niestandardowy wygląd tekstu są dopracowywane bezpośrednio przez użytkownika we własnym zakresie. Zasada ta **nie dotyczy** skali nagłówków `text-h1`–`text-h7` / `text-big` — to nie jest mikro-typografia, tylko token design systemu i element odwzorowania makiety.

Nie używaj ad-hoc klas typu `min-h-*`, `max-h-*`, `rounded-*`, `rounded-[...]`, `min-h-[...]`,
`radius-*` (jeśli nie istnieje to w projekcie jako token), chyba że dana klasa jest już zdefiniowana
w design systemie motywu. W tym repo preferowane są istniejące klasy typu `radius`, `radius-img`,
`c-main`, `m-header`, `m-btn`, `m-img`, a nie arbitralne wartości na siłę.

Tak samo nie używaj arbitralnych klas typograficznych typu `text-[42px]`, `leading-[1.1]` tam, gdzie
projekt nie ma już gotowego tokenu lub wzorca. Nie tworzymy nowych klas „na szybko” dla jednego
mockupu; jeśli czegoś nie ma w design systemie, lepiej zostawić prosty układ i pozwolić użytkownikowi
dopracować styl ręcznie.

Plik `resources/css/blocks/<slug>.scss` nadal utwórz i zaimportuj, ale domyślnie zostaw w nim tylko:

```scss
.b-<slug> {
}
```

Nie przenoś klas możliwych do zapisania w Tailwindzie do selektorów `.__wrapper`, `.__content`,
`.__media`, `.__img`, `.__txt` ani do lokalnych `@media`. Nie twórz w SCSS kompletnego layoutu bloku
ani jego osobnej implementacji responsywnej.

Custom CSS dodawaj wyłącznie wtedy, gdy jest rzeczywiście niezbędny i nie da się go rozsądnie zapisać
istniejącymi utility classes. Typowe wyjątki to pseudoelementy, stylowanie HTML generowanego przez
WYSIWYG lub zewnętrzną wtyczkę oraz złożony selektor niemożliwy do wyrażenia w Blade. Nawet wtedy
dodaj absolutne minimum deklaracji potrzebnych dla tego wyjątku.

Nie dodawaj zapasowych wariantów, dodatkowych breakpointów, stanów, klas typu `order-flip` ani
rozbudowanych styli „na przyszłość”, jeśli użytkownik nie poprosił o nie w danym zadaniu. Użytkownik
samodzielnie rozbuduje później styling, jeśli będzie potrzebny.

Używaj istniejących tokenów i standardowych klas projektu. Wartości arbitralne Tailwinda stosuj tylko
wtedy, gdy konkretna wartość wynika bezpośrednio z projektu i nie ma odpowiadającego jej tokenu.

⸻

Design tokens

Respect existing design tokens.

Before introducing a new:

* color,
* font size,
* spacing value,
* border radius,
* shadow,
* container width,
* breakpoint,

check whether an equivalent token or convention already exists.

Do not redefine existing tokens locally.

Do not use approximate colors when an appropriate project color already exists.

⸻

Design system — używaj tokenów, nie wartości

Wszystko jest w `resources/css/variables.scss` i w bloku `@theme` w `resources/css/app.css`
(Tailwind v4, konfiguracja CSS-first — `tailwind.config.js` zawiera tylko plugin `forms`,
nie dopisuj tam kolorów ani spacingu).

**Kontenery** (nie rób własnych `max-w-*`):

W nowych blokach nie dodawaj klas `max-w-*`, `min-h-*`, `leading-normal` ani klas rozmiaru
fontu spoza skali nagłówków (`text-base`, `text-lg`, `text-xl` itd.), chyba że użytkownik
wyraźnie poprosi o konkretną klasę. **Wyjątkiem jest skala nagłówków `text-h1`–`text-h7`
i `text-big` — tę stosuj zawsze, gdy makieta nadaje tekstowi styl nagłówka** (patrz
„Nagłówki z makiety" niżej). Nie ograniczaj nimi nagłówków, treści ani wrapperów na podstawie własnych założeń.
Nie dodawaj też elementom tekstowym klas marginesu (`mt-*`, `mb-*`, `mx-*`, `my-*`, `m-*`) bez
wyraźnej prośby użytkownika.

| Klasa | Max-width |
|---|---|
| `c-main` | 1376px — domyślny wrapper bloku |
| `c-narrow` | 1176px — węższe treści |
| `c-wide` / `.wide .c-main` | 100% — tryb `wide` |

**Odstępy sekcji** — nie używaj `mt-*` na `<section>`, tylko: `-smt` / `-spt` / `-smb` / `-spb`
(104px, na mobile 56px), oraz `-menu-mt` / `-menu-pt` (88px). Marginesy wewnętrzne: `m-header` (16px),
`m-title` (16px), `m-btn` (40px), `m-img` (40px) — utility zdefiniowane w `app.css`.

**Typografia**: `text-h1` … `text-h7`, `text-big`, `text-gradient`, `text-underline`, `text-action`,
`font-header` (eurostile-extended).

**Nagłówki z makiety** — `text-h1`–`text-h7`, `text-big`:

Gdy w Figmie tekst ma styl nagłówka (H1–H6, „Heading", wyraźnie większy stopień pisma), **odwzoruj
to klasą ze skali** — bez niej tytuł kafelka dziedziczy rozmiar akapitu i sekcja rozjeżdża się
względem projektu. Tak robią wszystkie istniejące bloki w tym repo (`overlap`, `cards`, `history`,
`proces`, `values`, `checks`, `whyus`).

Mapowanie jest 1:1 z numeracją z Figmy: H2 → `text-h2`, H6 → `text-h6` itd. Poziom z makiety
opisuje **wygląd**, nie semantykę.

Semantykę dobieraj osobno i oszczędnie: `<h2>` dla nagłówka sekcji, a **tytuły kafelków, slajdów
i pozycji list zapisuj jako `<p class="text-hN">`** — tak jak reszta repo. Nie produkuj `<h3>`/`<h4>`
dla każdego kafelka; zagnieżdżone nagłówki psują hierarchię dokumentu i dostępność, a wyglądowo
`<p class="text-h6">` daje dokładnie to samo.

```blade
<p class="__title text-h6">{{ $card['title'] }}</p>
```

**Kolory**: `--color-primary*` (granat #1A3955), `--color-secondary*` (brąz #492D28),
`--color-third*` (jasny niebieski #BBD6F3), `--color-page` (#F4F9FF), `--color-gray`, `--color-action`,
`--color-background-light|brand|dark|navy`. Każda skala ma 50–900 plus `-hover` i `-dark`.
Nie wpisuj hexów w Blade.

**Tła sekcji** (klasy w `variables.scss`): `section-white`, `section-light`, `section-s-light`,
`section-brand`, `section-primary`, `section-gradient`, `section-dark`. Pomocnicze: `section-py`,
`section-gap`, `bg-background`, `bg-gradient`, `bg-gradient-light`, `bg-light`, `bg-lighter`.

**Obrazy**: klasy wysokości `img-xs` (176px), `img-s`, `img-m`, `img-md`, `img-l`, `img-xl`, `img-2xl`,
`img-3xl` (664px); zaokrąglenia `radius`, `radius-left`, `radius-img`; cień `b-shadow`.

**Przyciski** — wyłącznie przez komponent `x-button`:

```blade
<x-button :href="$g_hero['button1']['url']" variant="primary" data-gsap-element="btn">
	{{ $g_hero['button1']['title'] }}
</x-button>
```

Dostępne warianty (`.btn-<variant>` w `variables.scss`): `primary`, `secondary`, `white`, `underline`,
`outline-primary`, `outline-secondary`, `primary-small`, `secondary-small`.
Grupę przycisków owijaj w `<div class="inline-buttons m-btn">`.

Pola obrazów zawsze `'return_format' => 'array'`, pola linków też `'array'` (`['url']`, `['title']`).

⸻

Animacje GSAP

GSAP + ScrollTrigger ładowane są **z CDN** w `app/setup.php` (`gsap-cdn`, `gsap-st-cdn`, wersja 3.12.5)
i dostępne jako globalne `gsap` / `ScrollTrigger`. Pakiet `gsap` z `package.json` nie jest importowany
w `app.js` — nie zmieniaj tego bez potrzeby.

Animacje są sterowane atrybutami, nie kodem per blok:

- `data-gsap-anim="section"` na `<section>`,
- `data-gsap-element="img|header|txt|text|card|btn|arrows"` na animowanych elementach,
- `data-gsap-element="stagger"` + `data-gsap-edit="delay-0.2"` dla animacji kaskadowych.

Nie pisz własnych `gsap.from()` w widoku bloku, jeśli wystarczą te atrybuty.

⸻

JavaScript

Używaj JS tylko gdy potrzebny; preferuj możliwości przeglądarki i rozwiązania projektu. Sprawdź strukturę, podobne funkcje i dostępność bibliotek. Trzymaj skrypty w ich zakresie, bez globalnych zmiennych i wielokrotnej inicjalizacji.

`resources/js/app.js` ładuje JS bloków **warunkowo**, po obecności klasy bloku w DOM:

```js
if (document.querySelector('.b-nazwa')) import('./blocks/nazwa');
```

Dodając JS bloku, dopisz taki warunek pod komentarzem `/*--- USED ---*/` — nie importuj modułu
bezpośrednio na górze pliku. Sliderem w projekcie jest **Swiper 11** (wzorzec:
`resources/js/blocks/slider.js`), lightboxem **baguetteBox** (`.lightbox-gallery`). Dostępne są też
Alpine.js (`window.Alpine`, wystartowany) i jQuery. React jest w zależnościach, ale nie jest używany
we froncie — nie buduj na nim UI.

Aliasy Vite: `@scripts`, `@styles`, `@fonts`, `@images`.

⸻

Third-party libraries

Do not add a dependency without a clear reason.

Before adding one:

* check whether the repository already contains a solution,
* check whether the browser can handle the functionality natively,
* evaluate whether the dependency is justified.

If an existing library such as Swiper is already used for the required functionality, reuse it instead of adding another slider library.

⸻

WooCommerce

If WooCommerce is present, preserve WooCommerce compatibility.

Before modifying WooCommerce behavior:

* inspect existing overrides,
* inspect hooks and filters,
* inspect Sage/WooCommerce integration,
* check for project-specific helpers.

Prefer hooks and filters over unnecessarily copying WooCommerce templates.

Only override templates when there is a clear reason.

Do not modify WooCommerce plugin files.

⸻

Forms

When working with forms, inspect the existing form implementation first.

Preserve:

* validation,
* accessibility,
* error handling,
* success states,
* required fields,
* spam protection,
* existing Contact Form 7 conventions where applicable.

Do not create a custom form system if the project already uses Contact Form 7 or another established solution unless explicitly requested.

⸻

Accessibility

New UI should be reasonably accessible by default.

Pay attention to:

* semantic HTML,
* heading hierarchy,
* labels,
* keyboard interaction,
* focus states,
* button vs link semantics,
* alt text handling,
* ARIA attributes where genuinely necessary,
* sufficient interactive target sizes.

Do not add ARIA attributes unnecessarily when native HTML semantics already provide the correct behavior.

⸻

Performance

Avoid unnecessary performance regressions.

Pay attention to:

* image sizes,
* responsive images,
* lazy loading,
* unnecessary JavaScript,
* duplicate queries,
* expensive loops,
* unnecessary DOM complexity,
* unnecessary dependencies.

Use WordPress image functions and existing project image helpers when available instead of hardcoding image URLs.

⸻

Code quality

Kod ma być produkcyjny, czytelny, prosty, utrzymywalny i spójny z projektem. Oddzielaj odpowiedzialności; unikaj nadmiernych abstrakcji, duplikowania logiki i skomplikowanych sztuczek. Nie twórz abstrakcji jednorazowych bez uzasadnienia architektonicznego.

⸻

Naming

Nazwy plików, klas, metod, zmiennych, komponentów, pól ACF i CSS mają odpowiadać konwencjom repozytorium oraz opisywać przeznaczenie. Nie wprowadzaj nowego systemu nazewnictwa.

⸻

Comments

Do not over-comment obvious code.

Comments should explain:

* non-obvious decisions,
* unusual workarounds,
* external limitations,
* important architectural reasoning.

Avoid comments that simply translate the code into English.

⸻

Scope discipline

Realizuj tylko zakres zadania. Bez potrzeby nie refaktoryzuj, nie zmieniaj nazw, zależności, konfiguracji ani formatowania innych plików. Drobne poprawki bezpośrednio związane z zadaniem są dopuszczalne; większe niezwiązane problemy zgłoś.

⸻

Existing functionality

Zachowaj istniejące funkcje i kompatybilność. Przed zmianą współdzielonych komponentów, globalnych styli, JS, hooków WordPress/WooCommerce, ACF lub konfiguracji sprawdź wszystkie zastosowania.

⸻

Assets

Before adding a new asset, inspect existing assets.

Reuse existing:

* icons,
* SVGs,
* logos,
* placeholders,
* decorative graphics,

when they match the design.

Do not embed large base64 assets directly in templates.

Follow the project's established asset pipeline.

⸻

Icons

Use the icon system already established in the repository.

Katalog `resources/views/components/icon/` zawiera na razie tylko `arrow-up`. Pozostałe ikony
w blokach są wklejane jako inline SVG (wzorzec: strzałki w `values.blade.php`).

Do not introduce another icon library simply for one icon.

Do not substitute random icons when a design clearly requires a specific one.

⸻

Content

Do not unnecessarily hardcode editable content into templates.

Determine whether content is:

* global,
* page-specific,
* block-specific,
* structural,
* dynamic.

Use the same content-management strategy as similar existing elements.

Do not turn every piece of text into an ACF field automatically.

⸻

Handling uncertainty

Rozstrzygaj niejasności na podstawie zadania, projektu, kodu i konwencji. Pytaj tylko o istotne decyzje, których nie da się ustalić z tych źródeł; drobne decyzje podejmuj zgodnie z repozytorium.

⸻

Implementation workflow

Zrozum zadanie → zbadaj repozytorium i podobne implementacje → znajdź elementy do ponownego użycia → wybierz najmniejszą zmianę → zaimplementuj → przejrzyj całość, błędy, responsywność i wpływ na istniejące funkcje. Samo utworzenie plików nie kończy zadania.

⸻

Validation

Wykonuj dostępne, adekwatne kontrole PHP, Blade, JS, importów i formatowania. Raportuj rzeczywiście
wykonane sprawdzenia i ograniczenia. Nie uruchamiaj `yarn build`, `yarn dev` ani komend Acorn —
wykonuje je użytkownik; przypomnij wymagane kroki.

Nie uruchamiaj przeglądarki ani nie rób screenshotów strony, żeby zweryfikować zmianę. Jeśli render
trzeba sprawdzić, zrób to serwerowo przez `wp eval-file` (`parse_blocks()` + `render_block()`).

⸻

File creation

Nie twórz zbędnych plików ani nowych struktur katalogów. Najpierw sprawdź, czy zmiana należy do istniejącego pliku lub komponentu. Nowe pliki umieszczaj zgodnie z repozytorium.

⸻

Refactoring

Refactoring is allowed when it directly supports the requested implementation.

Avoid large unsolicited refactors.

If existing code is problematic but unrelated to the current task, leave it alone unless it prevents implementation.

⸻

Build i assety

`.gitignore` ignoruje `public/*` **z wyjątkiem `public/build/**`** — skompilowane assety są śledzone w gicie.
Po każdej zmianie w `resources/css` lub `resources/js` przypomnij, że trzeba uruchomić `yarn build`
i uwzględnić `public/build` w commicie (robi to użytkownik samodzielnie) — inaczej produkcja dostanie
stary CSS/JS.

`theme.json` w rootcie jest źródłem preprocesowanym; realny `theme.json` powstaje w
`public/build/assets/theme.json` (podmiana przez filtr `theme_file_path` w `app/setup.php`).
Nie edytuj pliku w `public/build`.

Pliki `.scss` są importowane z `app.css`. Jeśli build wywali się na SCSS — sprawdź, czy `sass`
jest zainstalowany; nie ma go obecnie w `devDependencies`.

⸻

Formatowanie i język kodu

- PHP w `app/` jest pisany **tabami** (wszystkie bloki) — trzymaj się tabów, mimo że
  `.editorconfig` deklaruje spacje. Nie przeformatowuj istniejących plików „przy okazji”.
- Blade / JS / CSS: 2 spacje, LF, końcowy newline, single quotes.
- Nazwy techniczne (klasy, metody, pola ACF, klasy CSS, pliki) — po angielsku.
- Etykiety i instrukcje ACF, teksty w adminie, komentarze sekcyjne (`/*--- ... ---*/`) — po polsku,
  zgodnie z istniejącym kodem.

⸻

WordPress / WooCommerce — stan faktyczny

- CPT: `offer` (slug `oferta`) + taksonomia `offer_category` (`kategoria-oferty`) — `app/post-types.php`.
- Menu: `primary_navigation`; renderowane walkerami `App\Walkers\DropdownWalker` i `MobileDropdownWalker`.
- Edytor blokowy ma whitelistę bloków (`allow_only_selected_blocks` w `functions.php`): wszystkie `acf/*`,
  bloki z kategorii `formatting`, `_media`, `_con`, `_tekst`, plus `core/paragraph`, `core/heading`,
  `core/list`. Nowy blok core trzeba tam świadomie dopuścić.
- Woo: `add_theme_support('woocommerce')` + galeria (zoom, lightbox, slider). Pattern coming-soon leży
  w `patterns/woo-coming-soon.php`.
- FSE wyłączone (`remove_theme_support('block-templates')`), domyślne wzorce też
  (`remove_theme_support('core-block-patterns')`).
- Opcje globalne: strona `theme-settings` (rejestrowana w `ThemeServiceProvider`) + strony opcji
  `Octa`, `OLogos`, `Oreviews`, `OAbout`, `OEveryone`, `OGastro`. Dane globalne (logo, logo stopki, kontakt
  w stopce) są
  wstrzykiwane do wszystkich widoków przez `App\View\Composers\App` — nie wołaj
  `get_field(..., 'option')` w Blade, jeśli dane już tam są.
- **Bloki bez własnych pól na treść** — czytają ją skądinąd, więc nie wypełniaj ich payloadem:
  `Cta`, `Logos`, `Reviews`, `About`, `Everyone`, `Gastro` biorą treść ze stron opcji; `Offers`, `Products`,
  `Slider`, `Posts`, `Offer`, `News` odpytują CPT (`offer` / `post`).
- Aktywna wtyczka `svg-support` (upload SVG do biblioteki mediów działa).

⸻

Znane niespójności — nie „naprawiaj” ich mimochodem, ale nie kopiuj wzorca

Zgłoś je, jeśli wejdą w drogę; samodzielna naprawa tylko wtedy, gdy blokuje zadanie:

- `functions.php` (filtr `sage/acf-composer/fields`) i `app/Providers/ThemeServiceProvider.php`
  (`use App\Blocks\ExampleBlock`) odwołują się do klasy `App\Blocks\ExampleBlock`, która **nie istnieje**.
- `app/setup.php` globuje `app/Woo/*.php` — katalog nie istnieje.
- `app/filters.php` wskazuje `resources/views/patterns/coming-soon.php` — katalog `resources/views/patterns`
  nie istnieje (realny pattern to `patterns/woo-coming-soon.php`).
- `get_pdf_thumbnail_url()` jest zdefiniowany dwukrotnie: w `app/setup.php` i w `app/helpers.php`.
  Fatala nie ma tylko dlatego, że **`app/helpers.php` nie jest nigdzie ładowany** (nie ma go w
  `collect([...])` w `functions.php` ani w `autoload.files`). Nie dopisuj go do ładowanych plików
  bez usunięcia duplikatu.
- `resources/views/blocks/posts.php` nie ma rozszerzenia `.blade.php`, choć `app/Blocks/Posts.php`
  ma `$slug = 'posts'`.
- `section-gray` jest w selectach tła 26 bloków, ale **nie ma takiej klasy** w `variables.scss`.
  Odwrotnie: `section-primary` i `section-s-light` są zdefiniowane, ale nie ma ich w żadnym selekcie.
- `wider-gap` jest mapowane w `SectionClasses::fromMap()` w 22 blokach i doklejane w kilku widokach,
  ale **nie jest nigdzie zdefiniowane w CSS** — przełącznik „Większy odstęp” nic nie robi.
- `.font-body` ustawia `font-family: var(--text-body)`, a `--text-body` to **kolor**
  (`rgb(var(--p-800))` w `app.css`) — klasa jest zepsuta.
- `resources/css/blocks/category-posts.scss` i `resources/js/blocks/category-posts.js` istnieją,
  ale nie ma bloku o tym slugu.
- W repo są trzy lockfile'e (yarn / npm / pnpm) plus `pnpm-workspace.yaml` — aktualny jest `yarn.lock`.

⸻

Git

Do not rewrite Git history.

Do not force push.

Do not delete branches.

Do not discard unrelated local changes.

Do not commit unrelated files.

Before destructive Git operations, request explicit approval.

If commits are requested, keep them focused and use meaningful commit messages.

⸻

Security

Never expose or commit:

* passwords,
* API secrets,
* private keys,
* access tokens,
* database credentials,
* .env secrets,
* production credentials.

Do not print secrets into logs or responses.

Treat existing secrets found in the repository as sensitive.

W repozytorium są śledzone przez git pliki `key` i `key.pub` — `key` to prywatny klucz OpenSSH
(prawdopodobnie klucz deploymentu). Nie odczytuj jego zawartości, nie wypisuj jej w odpowiedziach,
nie kopiuj do innych plików i nie wysyłaj nigdzie. Jeśli zadanie dotyczy deploymentu — zgłoś to
użytkownikowi zamiast korzystać z klucza.

⸻

Definition of done

Zadanie kończy kompletna, przejrzana implementacja zgodna z projektem, repozytorium, responsywnością
i dostępnością. Nie wprowadzaj zbędnych duplikatów ani regresji. Sprawdź składnię i importy
w dostępnym zakresie. Nowy blok ma pełne ustawienia i `SectionClasses::fromMap()`, komponent
`x-button`, sekcyjne odstępy, import SCSS i warunkowy import potrzebnego JS. Przypomnij użytkownikowi
o wymaganym `yarn build` i commicie `public/build`; nie wykonuj tych komend.

⸻

Final rule

Understand the project before changing the project.

The repository is the source of truth.

When multiple technically correct solutions exist, prefer the solution that looks like it was written by the existing project team.

# Language

Communicate with the user in Polish.

All explanations, summaries, questions, implementation notes, and development-related communication should be written in Polish.

Code must follow the language conventions already established in the repository.

Unless the existing project uses a different convention, use English for:
- PHP class names,
- method and function names,
- variable names,
- file and directory names,
- ACF field names and keys,
- JavaScript identifiers,
- technical identifiers.

User-facing website content should remain in the language required by the project or provided design.

Do not translate existing code identifiers from English to Polish.
