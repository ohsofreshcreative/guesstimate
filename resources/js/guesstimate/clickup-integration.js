/*--- GUESSTIMATE: MOJE INTEGRACJE → CLICKUP (konfiguracja i synchronizacja własnego konta) ---*/
// Przeglądarka nie widzi tokenu — wszystkie zapytania do ClickUp wykonuje serwer w imieniu zalogowanego użytkownika.
// Zapisane wartości (Workspace, Space, listy, pola) są wstawiane jako opcje od razu, więc wybór nie „znika”
// do czasu pobrania pełnych list z ClickUp.

import { postJson } from './http.js';

const POLL_MS = 2000;

export default function clickupIntegration(initial = {}) {
  const config = initial.config || {};

  return {
    urls: initial.urls || {},
    targets: initial.targets || {},
    teams: initial.teams || [],
    spaces: config.space_id ? [{ id: config.space_id, name: config.space_name || config.space_id }] : [],
    lists: [],
    fields: Object.entries(config.field_labels || {}).map(([id, name]) => ({ id, name })),
    teamId: config.team_id || '',
    spaceId: config.space_id || '',
    selected: (config.lists || []).map((l) => ({ id: String(l.id), name: l.name || String(l.id) })),
    mapping: { ...(config.mapping || {}) },
    saved: { team: config.team_name || '', lists: (config.lists || []).map((l) => l.name).join(', ') },
    loading: null,
    error: '',
    errors: {},
    message: '',
    report: null,
    sync: initial.sync || null,
    missing: initial.missing || [],
    dirty: false,
    pollTimer: null,

    async init() {
      if (this.teamId && !this.teams.find((t) => t.id === this.teamId)) {
        this.teams.push({ id: this.teamId, name: config.team_name || this.teamId });
      }
      if (this.sync?.active) this.poll();
      // Kolejno, żeby komunikaty błędów się nie nadpisywały; zapisany wybór jest widoczny od razu.
      if (initial.connected && this.teamId) await this.loadSpaces(true);
      if (initial.connected && this.spaceId) await this.loadLists();
      if (initial.connected && this.selected.length) await this.loadFields();
    },

    async options(level, id = '') {
      const data = await postJson(this.urls.options, { level, id });
      return data.items || [];
    },

    async run(label, fn) {
      this.loading = label;
      this.error = '';
      try {
        await fn();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.loading = null;
      }
    },

    loadTeams() {
      return this.run('teams', async () => {
        this.teams = await this.options('teams');
      });
    },

    loadSpaces(keep = false) {
      if (!this.teamId) return null;
      return this.run('spaces', async () => {
        const items = await this.options('spaces', this.teamId);
        if (keep && this.spaceId && !items.find((s) => s.id === this.spaceId)) {
          items.unshift({ id: this.spaceId, name: `${config.space_name || this.spaceId} (niedostępny)` });
        }
        this.spaces = items;
      });
    },

    loadLists() {
      if (!this.spaceId) return null;
      return this.run('lists', async () => {
        this.lists = await this.options('lists', this.spaceId);
      });
    },

    loadFields() {
      if (!this.selected.length) return null;
      return this.run('fields', async () => {
        const seen = new Map(this.fields.map((f) => [f.id, f]));
        for (const list of this.selected) {
          for (const field of await this.options('fields', list.id)) {
            seen.set(field.id, field);
          }
        }
        this.fields = [...seen.values()];
      });
    },

    changeTeam() {
      this.spaceId = '';
      this.spaces = [];
      this.lists = [];
      this.dirty = true;
      this.loadSpaces();
    },

    changeSpace() {
      this.lists = [];
      this.dirty = true;
      this.loadLists();
    },

    isSelected(id) {
      return this.selected.some((l) => l.id === id);
    },

    toggleList(list) {
      this.dirty = true;
      if (this.isSelected(list.id)) {
        this.selected = this.selected.filter((l) => l.id !== list.id);
      } else {
        this.selected.push({ id: list.id, name: list.label || list.name });
        this.loadFields();
      }
    },

    removeList(id) {
      this.dirty = true;
      this.selected = this.selected.filter((l) => l.id !== id);
    },

    fieldName(id) {
      if (id === 'name') return 'Nazwa zadania';
      return this.fields.find((f) => f.id === id)?.name || id;
    },

    save() {
      return this.run('save', async () => {
        this.errors = {};
        const labels = {};
        Object.values(this.mapping).forEach((id) => {
          if (String(id).startsWith('cf:')) labels[id] = this.fieldName(id);
        });
        try {
          const data = await postJson(this.urls.save, {
            team_id: this.teamId,
            team_name: this.teams.find((t) => t.id === this.teamId)?.name || '',
            space_id: this.spaceId,
            space_name: this.spaces.find((s) => s.id === this.spaceId)?.name || '',
            lists: this.selected,
            mapping: this.mapping,
            field_labels: labels,
          });
          this.message = data.message;
          this.saved = { team: data.config?.team_name || '', lists: (data.config?.lists || []).map((l) => l.name).join(', ') };
          this.missing = data.missing || [];
          this.sync = data.sync;
          this.dirty = false;
          if (data.sync?.active) this.poll();
        } catch (error) {
          this.errors = error.data?.errors || {};
          throw error;
        }
      });
    },

    test() {
      return this.run('test', async () => {
        this.report = await postJson(this.urls.test, {});
      });
    },

    startSync() {
      return this.run('sync', async () => {
        this.sync = await postJson(this.urls.sync, {});
        this.poll();
      });
    },

    poll() {
      clearTimeout(this.pollTimer);
      this.pollTimer = setTimeout(async () => {
        try {
          const data = await postJson(this.urls.status, {});
          this.sync = data;
          this.missing = data.missing || this.missing;
          if (data.active) this.poll();
        } catch (error) {
          this.error = error.message;
        }
      }, POLL_MS);
    },

    statusLabel() {
      const s = this.sync;
      if (!s || s.status === 'never') return 'Nie zsynchronizowano';
      return { done: 'Aktualne', running: 'Synchronizacja trwa', queued: 'Oczekuje', waiting: 'Oczekuje', error: 'Błąd synchronizacji' }[s.status] || s.status;
    },

    syncLabel() {
      const s = this.sync;
      if (!s) return '';
      const mode = s.mode === 'incremental' ? 'przyrostowa' : 'pełna';
      switch (s.status) {
        case 'queued':
          return `Oczekuje — synchronizacja ${mode} zaraz się rozpocznie`;
        case 'running':
          return `Synchronizacja trwa (${mode})`;
        case 'waiting':
          return `Oczekuje — wznowienie ok. ${s.retry_at || 'za chwilę'}`;
        case 'done':
          return `Aktualne — ostatnia synchronizacja ${mode}`;
        case 'error':
          return 'Błąd synchronizacji';
        default:
          return 'Nie zsynchronizowano';
      }
    },

    stageLabel() {
      const s = this.sync;
      if (!s) return '';
      const where = s.active && s.current_list ? `Etap: ${s.current_list}, strona ${s.current_page}. ` : '';
      return `${where}Przetworzone rekordy w tym przebiegu: ${s.processed}. Firm w indeksie: ${s.companies}.`;
    },
  };
}
