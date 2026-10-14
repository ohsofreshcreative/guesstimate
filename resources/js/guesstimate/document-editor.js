/*--- GUESSTIMATE: EDYTOR WIZUALNY DOKUMENTU ---*/
// Stan edytora to wyłącznie istniejące pola wersji: `content` (treści z kroku 5) i `document` (teksty i wygląd stron).
// Każda zmiana odświeża podgląd LIVE (serwer renderuje te same komponenty stron co oferta publiczna) bez zapisu;
// zapis jest świadomą operacją. Kalkulacji edytor nie zmienia — cena w dokumencie pochodzi z kalkulatora.

import { nonce, postJson } from './http.js';

const DESIGN_WIDTH = 1123; // A4 poziomo przy 96 dpi — „100%”
const ZOOMS = { fit: null, 75: 0.75, 100: 1, 125: 1.25 };
const clone = (value) => JSON.parse(JSON.stringify(value));
const empty = (html) => !String(html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
const textLength = (html) => String(html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length;

export default (config) => ({
  editable: config.editable,
  state: clone(config.state),
  saved: JSON.stringify(config.state),
  pages: config.pages,
  library: config.library,
  images: config.images || {},
  urls: config.urls,
  selected: 'cover',
  field: null,
  tab: 'page',
  zoom: 'fit',
  showThumbs: true,
  showPanel: true,
  saving: false,
  publishing: false, // po wysłaniu „Opublikuj” — blokada podwójnego kliknięcia (serwer też nie dubluje publikacji)
  pending: null,
  previewing: false,
  ready: { frame: false, thumbs: false },
  html: null,
  error: null,
  errors: {},
  message: null,
  report: {},
  summary: [],
  fitReport: config.fitReport,
  outdated: config.outdated,
  blockers: config.blockers,
  picker: { open: false, items: [], q: '', page: 1, more: false, loading: false, uploading: false, error: null },
  proposal: null,
  busy: null,
  aiError: null,

  init() {
    // Powrót przyciskiem „Wstecz” (bfcache) po publikacji: przycisk znów aktywny.
    window.addEventListener('pageshow', (event) => { if (event.persisted) this.publishing = false; });
    // Ramka mogła się wczytać, zanim Alpine zaczął nasłuchiwać komunikatów „ready”.
    this.$nextTick(() => ['frame', 'thumbs'].forEach((key) => {
      const win = this.$refs[key]?.contentWindow;
      if (win?.gstDoc && !this.ready[key]) this.receive({ origin: location.origin, source: win, data: { gst: true, type: 'ready', ...win.gstDoc.summary() } });
    }));
    this.$watch('state', () => this.schedule(), { deep: true });
    this.$watch('zoom', () => this.applyZoom());
    this.$watch('showThumbs', () => this.$nextTick(() => this.applyZoom()));
    this.$watch('showPanel', () => this.$nextTick(() => this.applyZoom()));
  },

  /*--- Stan ---*/

  get dirty() { return JSON.stringify(this.state) !== this.saved; },
  get page() { return this.pages.find((p) => p.key === this.selected); },
  get pageState() { return this.state.document.pages[this.selected]; },
  get mediaLayout() { return !!this.page?.layouts.find((l) => l.key === this.pageState.layout)?.media; },
  get overflow() { return !!this.report.situation?.overflow; },
  get canPublish() { return !this.dirty && !this.overflow && !this.blockers.length && !this.saving && !this.publishing; },
  get blocks() { return this.state.content.blocks; },

  pageNumber(key) {
    let n = 0;
    for (const item of this.summary) {
      if (item.hidden) continue;
      n += 1;
      if (item.key === key) return n;
      n += item.count - 1;
    }
    return null;
  },

  hiddenReason(key) { return this.summary.find((p) => p.key === key)?.hidden || null; },

  fieldError(name) { return this.errors[name] || null; },

  /*--- Ramki podglądu ---*/

  frame() { return this.$refs.frame?.contentWindow; },
  thumbs() { return this.$refs.thumbs?.contentWindow; },

  receive(event) {
    if (event.origin !== location.origin || !event.data?.gst) return;
    const data = event.data;
    const fromFrame = event.source === this.frame();
    const fromThumbs = event.source === this.thumbs();
    if (!fromFrame && !fromThumbs) return;

    if (data.type === 'ready') {
      this.ready[fromFrame ? 'frame' : 'thumbs'] = true;
      if (this.html !== null) (fromFrame ? this.frame() : this.thumbs()).gstDoc.render(this.html);
      this.syncSelection();
      if (fromFrame) this.applyZoom();
    }

    if (fromFrame && (data.type === 'ready' || data.type === 'rendered')) {
      this.report = data.fit || {};
      this.summary = data.pages || [];
      this.reportFit();
    }

    if (data.type === 'select') this.select(data.page);
    if (data.type === 'edit') this.select(data.page, data.field);
  },

  syncSelection() {
    this.frame()?.gstDoc?.select(this.selected, this.field);
    this.thumbs()?.gstDoc?.select(this.selected);
  },

  applyZoom() {
    const frame = this.$refs.frame;
    if (!frame?.contentWindow?.gstDoc) return;
    const factor = ZOOMS[this.zoom];
    const available = frame.clientWidth - 48;
    const width = factor ? DESIGN_WIDTH * factor : Math.min(available, ((frame.clientHeight - 64) * 297) / 210);
    frame.contentWindow.gstDoc.setWidth(Math.max(320, width));
  },

  /** Podgląd LIVE: po krótkiej przerwie w pisaniu serwer renderuje strony dla niezapisanego stanu. */
  schedule() {
    clearTimeout(this.timer);
    this.pending = new Promise((resolve) => {
      this.timer = setTimeout(() => this.refresh().finally(resolve), 350);
    });
  },

  async refresh() {
    this.previewing = true;
    try {
      const data = await postJson(this.urls.preview, { content: this.state.content, document: this.state.document });
      this.html = data.html;
      this.errors = data.errors || {};
      if (this.ready.frame) this.frame().gstDoc.render(data.html);
      if (this.ready.thumbs) this.thumbs().gstDoc.render(data.html);
      this.syncSelection();
    } catch (error) {
      this.error = error.message;
    } finally {
      this.previewing = false;
    }
  },

  select(key, field = null) {
    if (!this.pages.find((p) => p.key === key)) return;
    this.selected = key;
    this.field = field;
    this.tab = 'page';
    this.syncSelection();
    if (field) this.$nextTick(() => this.focusField(field));
  },

  focusField(field) {
    const name = field.startsWith('block:') ? field : field;
    const el = this.$root.querySelector(`[data-field="${CSS.escape(name)}"]`);
    if (!el) return;
    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    el.querySelector('input, textarea, select, [contenteditable]')?.focus({ preventScroll: true });
  },

  go(delta) {
    const visible = this.pages.map((p) => p.key);
    const index = visible.indexOf(this.selected) + delta;
    if (index >= 0 && index < visible.length) this.select(visible[index]);
  },

  /*--- Zapis ---*/

  async save() {
    if (!this.editable || this.saving) return;
    this.saving = true;
    this.error = null;
    this.message = null;
    try {
      // Zapisujemy stan, który podgląd właśnie zmierzył (dopasowanie strony „Sytuacja”).
      clearTimeout(this.timer);
      await this.refresh();
      const response = await fetch(this.urls.save, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-GST-Nonce': nonce() },
        body: JSON.stringify({ content: this.state.content, document: this.state.document, fit: this.report }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        this.errors = data?.errors || {};
        throw new Error(data?.error || `Błąd serwera (${response.status}).`);
      }
      this.errors = {};
      this.state = data.state;
      this.saved = JSON.stringify(data.state);
      this.fitReport = data.fitReport;
      this.outdated = data.outdated;
      this.blockers = data.blockers;
      this.message = data.message;
    } catch (error) {
      this.error = error.message;
    } finally {
      this.saving = false;
    }
  },

  /** Pomiar zapisanego stanu (bez niezapisanych zmian) — serwer blokuje publikację przepełnionej strony. */
  async reportFit() {
    const fit = this.report.situation;
    if (!this.editable || this.dirty || !fit) return;
    const known = this.fitReport;
    if (known && !!known.overflow === !!fit.overflow && known.level === fit.level) return;
    try {
      const data = await postJson(this.urls.fit, { fit: this.report });
      this.fitReport = data.fitReport;
      this.blockers = data.blockers;
      this.outdated = data.outdated;
    } catch (error) {
      // pomiar jest pomocniczy — błąd nie przerywa pracy
    }
  },

  /*--- Wygląd strony ---*/

  setLayout(layout) { this.pageState.layout = layout; },
  setBackground(id) { this.pageState.background_id = id; },
  restoreBackground() {
    this.pageState.background_id = this.page.default_background_id;
    this.pageState.fit = null;
  },
  background(id) { return this.library.find((b) => b.id === id); },
  effectiveFit() { return this.pageState.fit || this.background(this.pageState.background_id)?.fit || 'cover'; },

  image(id) { return id ? this.images[id] || { id, name: `Zdjęcie #${id}`, thumb: null } : null; },

  openPicker() {
    this.picker.open = true;
    this.picker.error = null;
    if (!this.picker.items.length) this.loadMedia(true);
  },

  async loadMedia(reset = false) {
    const picker = this.picker;
    if (picker.loading) return;
    picker.loading = true;
    picker.error = null;
    try {
      const page = reset ? 1 : picker.page + 1;
      const response = await fetch(`${this.urls.media}?q=${encodeURIComponent(picker.q)}&page=${page}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error(`Błąd serwera (${response.status}).`);
      const data = await response.json();
      picker.items = reset ? data.items : picker.items.concat(data.items);
      picker.page = page;
      picker.more = data.more;
    } catch (error) {
      picker.error = error.message;
    } finally {
      picker.loading = false;
    }
  },

  pickImage(item) {
    this.images[item.id] = item;
    this.pageState.image_id = item.id;
    this.picker.open = false;
  },

  removeImage() { this.pageState.image_id = null; },

  async upload(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const picker = this.picker;
    picker.uploading = true;
    picker.error = null;
    try {
      const body = new FormData();
      body.append('file', file);
      const response = await fetch(this.urls.upload, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-GST-Nonce': nonce() }, body });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(data?.error || `Błąd serwera (${response.status}).`);
      picker.items.unshift(data);
      this.pickImage(data);
    } catch (error) {
      picker.error = error.message;
    } finally {
      picker.uploading = false;
      event.target.value = '';
    }
  },

  /*--- Treści ---*/

  addBlock() { if (this.blocks.length < config.maxBlocks) this.blocks.push({ title: '', html: '' }); },
  removeBlock(index) { if (this.blocks.length > config.minBlocks) this.blocks.splice(index, 1); },
  moveBlock(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= this.blocks.length) return;
    const [block] = this.blocks.splice(index, 1);
    this.blocks.splice(target, 0, block);
  },
  addRow(list, blank) { list.push({ ...blank }); },
  removeRow(list, index) { list.splice(index, 1); },
  toggleReference(id) {
    const ids = this.state.document.reference_ids;
    const index = ids.indexOf(id);
    if (index >= 0) ids.splice(index, 1);
    else if (ids.length < config.maxReferences) ids.push(id);
  },

  /*--- AI: zawsze propozycja do akceptacji ---*/

  aiLabel(target) {
    if (target === 'situation') return '„Jak rozumiemy Twoją sytuację?”';
    if (target.startsWith('block:')) return `blok ${Number(target.split(':')[1]) + 1} „Jak osiągnąć ten cel?”`;
    return 'tekst strony';
  },

  current(target) {
    if (target === 'situation') return { html: this.state.content.situation };
    if (target.startsWith('block:')) return this.blocks[Number(target.split(':')[1])];
    const key = target.split(':')[1];
    return { text: this.state.document[key] };
  },

  hasText(target) {
    const value = this.current(target);
    if (!value) return false;
    return target.startsWith('text:') ? String(value.text || '').trim() !== '' : !empty(value.html) || !!value.title?.trim();
  },

  async run(action, target, extra = {}) {
    if (this.busy) return;
    this.busy = `${action}:${target}`;
    this.aiError = null;
    try {
      const texts = target.startsWith('text:') ? { [target.split(':')[1]]: this.state.document[target.split(':')[1]] } : {};
      const result = await postJson(this.urls.ai, { action, target, prompt: '', content: this.state.content, texts, ...extra });
      this.proposal = { action, target, result };
    } catch (error) {
      this.aiError = error.message;
    } finally {
      this.busy = null;
    }
  },

  /** „Dopasuj tekst do strony z AI”: docelowa długość z pomiaru przepełnienia. */
  fitWithAi() {
    const ratio = this.report.situation?.ratio || 0.8;
    const length = textLength(this.state.content.situation);
    this.run('fit', 'situation', { target_chars: Math.max(300, Math.floor(length * ratio * 0.9)) });
  },

  accept() {
    const { target, result } = this.proposal;
    if (target === 'situation') this.state.content.situation = result.html;
    else if (target.startsWith('block:')) {
      const index = Number(target.split(':')[1]);
      this.blocks[index] = { title: result.title || this.blocks[index].title, html: result.html };
    } else {
      this.state.document[target.split(':')[1]] = result.text;
    }
    this.proposal = null;
  },

  reject() { this.proposal = null; },
});
