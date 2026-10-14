/*--- GUESSTIMATE: WYBÓR KLIENTA (lokalni + ClickUp) ---*/
// Wyszukiwanie z opóźnieniem (debounce w widoku), poprzednie zapytanie jest przerywane, a odpowiedzi
// spóźnione są ignorowane. Firmy z ClickUp pochodzą z lokalnego indeksu użytkownika (bez zapytań do ClickUp
// przy wpisywaniu). Brak połączenia / synchronizacji ma własne komunikaty; błąd nigdy nie udaje pustej listy.

import { postJson } from './http.js';

// Stany prywatnego indeksu ClickUp zalogowanego użytkownika (ClientLookupController::search).
const CLICKUP_MESSAGES = {
  not_connected: 'Połącz swoje konto ClickUp, aby wyszukiwać klientów.',
  reconnect: 'Połączenie z ClickUp wygasło lub zostało cofnięte. Połącz ponownie.',
  not_configured: 'Dokończ konfigurację ClickUp (Workspace, listy, pole z nazwą firmy), aby wyszukiwać klientów.',
  not_synced: 'Zsynchronizuj firmy z ClickUp, aby rozpocząć wyszukiwanie.',
};

export default (config) => ({
  selected: config.selected,
  newClient: config.newClient,
  q: '',
  open: false,
  loading: false,
  importing: false,
  error: null,
  canImport: false,
  clickupStatus: null,
  clickupSyncing: false,
  integrationsUrl: null,
  results: { local: [], clickup: [] },
  choice: null,
  seq: 0,
  controller: null,

  get clickupMessage() {
    if (!this.clickupStatus || ['ok', 'short'].includes(this.clickupStatus)) return null;
    if (this.clickupStatus === 'not_synced' && this.clickupSyncing) return 'Trwa synchronizacja firm z ClickUp — spróbuj za chwilę.';
    return CLICKUP_MESSAGES[this.clickupStatus] || null;
  },

  get nothingFound() {
    return !this.loading && !this.error && this.q.trim().length >= 1 && !this.results.local.length && !this.results.clickup.length;
  },

  details(item) {
    return [item.list ? `Lista: ${item.list}` : null, item.website_url, item.contact_name, item.email, item.phone, item.nip ? 'NIP ' + item.nip : null].filter(Boolean).join(' · ');
  },

  async search() {
    const seq = ++this.seq;
    this.error = null;
    this.choice = null;
    this.controller?.abort();

    if (this.q.trim().length < 1) {
      this.results = { local: [], clickup: [] };
      this.clickupStatus = null;
      this.open = false;
      return;
    }

    this.loading = true;
    this.open = true;
    this.controller = new AbortController();

    try {
      const response = await fetch(`${config.searchUrl}?q=${encodeURIComponent(this.q.trim())}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        signal: this.controller.signal,
      });
      const data = await response.json().catch(() => null);
      if (seq !== this.seq) return;
      if (!response.ok || !data) throw new Error(data?.error || data?.message || `Błąd serwera (${response.status}).`);
      this.results = { local: data.local || [], clickup: data.clickup || [] };
      this.clickupStatus = data.clickup_status || null;
      this.clickupSyncing = !!data.clickup_syncing;
      this.integrationsUrl = data.integrations_url || null;
      this.canImport = !!data.can_import;
    } catch (error) {
      if (error.name === 'AbortError') return;
      if (seq === this.seq) this.error = error.message;
    } finally {
      if (seq === this.seq) this.loading = false;
    }
  },

  choose(item) {
    this.selected = item;
    this.open = false;
    this.q = '';
    this.$dispatch('input');
  },

  async importClickUp(item, options = {}) {
    if (!this.canImport) {
      this.error = 'Nie masz uprawnień do dodawania klientów.';
      return;
    }
    this.importing = true;
    this.error = null;

    try {
      const data = await postJson(config.importUrl, { clickup_id: item.clickup_id, ...options });
      this.choice = null;
      this.choose(data.client);
      if (data.skipped?.length) {
        this.selected = { ...data.client, note: `Pominięto nieprawidłowe pola: ${data.skipped.join(', ')}` };
      }
    } catch (error) {
      if (error.status === 409 && error.data?.needs_choice) {
        // Niepewne dopasowanie: użytkownik wybiera istniejącego klienta albo tworzy nowego.
        this.choice = { item, candidates: error.data.candidates || [], deleted: error.data.deleted || null, canRestore: !!error.data.can_restore };
        this.open = true;
      } else {
        this.error = error.message;
      }
    } finally {
      this.importing = false;
    }
  },

  useExisting(client) {
    if (this.choice) this.importClickUp(this.choice.item, { client_id: client.id });
  },

  restoreDeleted() {
    if (this.choice) this.importClickUp(this.choice.item, { restore: true });
  },

  createNew() {
    if (this.choice) this.importClickUp(this.choice.item, { create_new: true });
  },

  clear() {
    this.selected = null;
    this.$dispatch('input');
    this.$nextTick(() => document.getElementById('f-client-search')?.focus());
  },
});
