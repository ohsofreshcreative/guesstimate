/*--- GUESSTIMATE: EDYTOR WIZUALNY DOKUMENTU ---*/
// Stan edytora to wyłącznie istniejące pola wersji: `content` (treści z kroku 5) i `document` (teksty i wygląd stron).
// Każda zmiana odświeża podgląd LIVE (serwer renderuje te same komponenty stron co oferta publiczna) i po chwili
// bez zmian zapisuje wersję roboczą (autozapis, można wyłączyć). Publikacja zawsze pozostaje świadomą operacją.
// Kalkulacji edytor nie zmienia — cena w dokumencie pochodzi z kalkulatora.

import {
  alignRect, createItem, emptyHistory, GRID, moveRect, nextCopyId, pushHistory, redoHistory, reorder, SCHEMA, undoHistory,
} from './field-layout.js';
import { nonce, postJson } from './http.js';

const DESIGN_WIDTH = 1123; // A4 poziomo przy 96 dpi — „100%”
const ZOOMS = { fit: null, 75: 0.75, 100: 1, 125: 1.25 };
const clone = (value) => JSON.parse(JSON.stringify(value));
const empty = (html) => !String(html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
const AUTOSAVE_DELAY = 2500;
const NUDGE_SMALL = 0.5; // % przy wyłączonej siatce
const textLength = (html) => String(html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length;

export default (config) => ({
  editable: config.editable,
  state: clone(config.state),
  saved: JSON.stringify(config.state),
  pages: config.pages,
  library: config.library,
  folders: config.folders || [],
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
  fieldReport: config.fieldReport || [],
  layout: config.layout,
  snap: true,
  grid: false,
  history: emptyHistory(),
  autosave: true,
  autosaveError: null,
  autosaved: false,
  outdated: config.outdated,
  blockers: config.blockers,
  picker: { open: false, items: [], q: '', folder: '', page: 1, more: false, loading: false, uploading: false, error: null },
  proposal: null,
  busy: null,
  aiError: null,
  migration: null, // podgląd migracji: stan przed nią, do czasu zapisu nic nie trafia na serwer

  init() {
    // Powrót przyciskiem „Wstecz” (bfcache) po publikacji: przycisk znów aktywny.
    window.addEventListener('pageshow', (event) => { if (event.persisted) this.publishing = false; });
    // Ramka mogła się wczytać, zanim Alpine zaczął nasłuchiwać komunikatów „ready”.
    this.$nextTick(() => ['frame', 'thumbs'].forEach((key) => {
      const win = this.$refs[key]?.contentWindow;
      if (win?.gstDoc && !this.ready[key]) this.receive({ origin: location.origin, source: win, data: { gst: true, type: 'ready', ...win.gstDoc.summary() } });
    }));
    this.restoreHistory();
    this.$watch('state', () => this.schedule(), { deep: true });
    this.$watch('state.document.layout_mode', () => this.configureFrame());
    this.$watch('snap', () => this.configureFrame());
    this.$watch('grid', () => this.configureFrame());
    this.$watch('zoom', () => this.applyZoom());
    this.$watch('showThumbs', () => this.$nextTick(() => this.applyZoom()));
    this.$watch('showPanel', () => this.$nextTick(() => this.applyZoom()));
  },

  /*--- Stan ---*/

  get dirty() { return JSON.stringify(this.state) !== this.saved; },
  get templateMode() { return this.state.document.layout_mode === 'template'; },
  get page() { return this.pages.find((p) => p.key === this.selected); },
  get pageState() { return this.state.document.pages[this.selected]; },
  get mediaLayout() { return !!this.page?.layouts.find((l) => l.key === this.pageState.layout)?.media; },
  get overflow() { return !!this.report.situation?.overflow; },
  get fieldOverflow() { return this.report.layout?.pages || []; },
  get canPublish() { return !this.migration && !this.dirty && !this.overflow && !this.fieldOverflow.length && !this.blockers.length && !this.saving && !this.publishing; },
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
      if (fromFrame) {
        this.applyZoom();
        this.configureFrame();
      }
    }

    if (fromFrame && (data.type === 'ready' || data.type === 'rendered')) {
      this.report = data.fit || {};
      this.summary = data.pages || [];
      this.reportFit();
    }

    if (data.type === 'select') this.select(data.page);
    if (data.type === 'edit') this.select(data.page, data.field, data.focus !== false);
    if (data.type === 'fields') this.fieldsMoved(data);
    if (data.type === 'key') this.onKey(data);
  },

  configureFrame() {
    this.frame()?.gstDoc?.configure({ fields: this.layout.fields, editable: this.editable && !this.templateMode, snap: this.snap, grid: this.grid });
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
    this.scheduleAutosave();
  },

  scheduleAutosave() {
    clearTimeout(this.autosaveTimer);
    // Podmiana stanu po zapisie też tu trafia, a wtedy status „Zapisano automatycznie” ma zostać.
    if (!this.dirty) return;
    this.autosaved = false;
    if (!this.editable || !this.autosave || this.migration) return;
    this.autosaveTimer = setTimeout(() => this.autoSave(), AUTOSAVE_DELAY);
  },

  /** Zapis w tle. Niepoprawny stan (np. pusty nagłówek okładki) czeka na poprawkę, a nie zgłasza błędu przy każdym znaku. */
  async autoSave() {
    if (!this.dirty || this.migration) return;
    if (this.saving || this.publishing || this.previewing) {
      this.scheduleAutosave();
      return;
    }
    if (Object.keys(this.errors).length) {
      this.autosaveError = 'Autozapis wstrzymany: popraw zaznaczone pola.';
      return;
    }
    await this.save({ auto: true });
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

  select(key, field = null, focus = true) {
    if (!this.pages.find((p) => p.key === key)) return;
    this.selected = key;
    this.field = field;
    this.tab = 'page';
    this.syncSelection();
    if (field && focus) this.$nextTick(() => this.focusField(field));
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

  async save({ auto = false } = {}) {
    if (!this.editable || this.saving) return;
    if (this.migration) this.commitMigration(); // zapis podczas podglądu = akceptacja migracji
    clearTimeout(this.autosaveTimer);
    this.saving = true;
    this.autosaveError = null;
    if (!auto) {
      this.error = null;
      this.message = null;
    }
    try {
      // Zapisujemy stan, który podgląd właśnie zmierzył (dopasowanie strony „Sytuacja”).
      clearTimeout(this.timer);
      await this.refresh();
      const sent = JSON.stringify(this.state);
      const response = await fetch(this.urls.save, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-GST-Nonce': nonce() },
        body: JSON.stringify({ content: JSON.parse(sent).content, document: JSON.parse(sent).document, fit: this.report }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        this.errors = data?.errors || {};
        throw new Error(data?.error || `Błąd serwera (${response.status}).`);
      }
      this.errors = {};
      // Zmiany wpisane w trakcie zapisu zostają w edytorze: trafią do kolejnego autozapisu.
      if (JSON.stringify(this.state) === sent) this.state = data.state;
      else this.$nextTick(() => this.scheduleAutosave());
      this.saved = JSON.stringify(data.state);
      this.fitReport = data.fitReport;
      this.fieldReport = data.fieldReport || [];
      this.storeHistory();
      this.outdated = data.outdated;
      this.blockers = data.blockers;
      if (auto) this.autosaved = true;
      else this.message = data.message;
    } catch (error) {
      if (auto) this.autosaveError = `Autozapis nie powiódł się: ${error.message}`;
      else this.error = error.message;
    } finally {
      this.saving = false;
    }
  },

  /** Pomiar zapisanego stanu (bez niezapisanych zmian) — serwer blokuje publikację przepełnionej strony. */
  async reportFit() {
    const fit = this.report.situation;
    if (!this.editable || this.dirty || !fit) return;
    const known = this.fitReport;
    const sameFields = JSON.stringify(this.fieldOverflow) === JSON.stringify(this.fieldReport);
    if (known && !!known.overflow === !!fit.overflow && known.level === fit.level && sameFields) return;
    try {
      const data = await postJson(this.urls.fit, { fit: this.report });
      this.fitReport = data.fitReport;
      this.fieldReport = data.fieldReport || [];
      this.blockers = data.blockers;
      this.outdated = data.outdated;
    } catch (error) {
      // pomiar jest pomocniczy — błąd nie przerywa pracy
    }
  },

  /*--- Układ pól: pozycje na stronie (treść zostaje w swoich polach) ---*/

  get fieldItems() { return this.pageState.fields?.items || []; },
  get fieldItem() { return this.fieldItems.find((item) => item.id === this.field) || null; },
  get movableIds() { return [...(this.layout.fields[this.selected] || []), ...this.fieldItems.filter((item) => item.text !== undefined).map((item) => item.id)]; },
  get fieldActive() { return this.movableIds.includes(this.field); },
  get canDuplicate() { return this.fieldActive && this.fieldItems.filter((item) => item.text !== undefined).length < this.layout.maxCopies; },
  get undoLabel() { return this.history.past.at(-1)?.label || null; },
  get redoLabel() { return this.history.future[0]?.label || null; },

  fieldLabel(id) {
    const item = this.fieldItems.find((row) => row.id === id);
    if (item?.text !== undefined) return `Kopia: ${item.text.slice(0, 24)}`;
    return this.layout.labels[id] || id;
  },

  /** Układ wszystkich stron: jedna wartość historii, więc cofanie działa też po przejściu na inną stronę. */
  layoutState() {
    return JSON.stringify(Object.fromEntries(Object.entries(this.state.document.pages).map(([key, page]) => [key, page.fields ?? null])));
  },

  historyKey() { return `gst-doc-layout-${config.versionId}`; },

  /** Historia zostaje po odświeżeniu, o ile dotyczy układu zapisanego na serwerze. */
  storeHistory() {
    try {
      sessionStorage.setItem(this.historyKey(), JSON.stringify({ present: this.layoutState(), history: this.history }));
    } catch (error) {
      // historia jest wygodą: pełny sessionStorage nie przerywa pracy
    }
  },

  restoreHistory() {
    try {
      const stored = JSON.parse(sessionStorage.getItem(this.historyKey()) || 'null');
      if (stored?.present === this.layoutState()) this.history = stored.history;
    } catch (error) {
      sessionStorage.removeItem(this.historyKey());
    }
  },

  /** Jedna zmiana układu = jeden krok historii. Strona bez układu najpierw dostaje pozycje wszystkich swoich pól. */
  changeFields(label, change, adopted = null) {
    const before = this.layoutState();
    const key = this.selected;
    const page = this.pageState;
    const items = JSON.parse(JSON.stringify(page.fields?.items || []));
    const measured = adopted || (items.length ? [] : this.frame()?.gstDoc?.measure(key) || []);

    measured.forEach((rect) => {
      if (!items.find((item) => item.id === rect.id)) items.push(createItem(key, rect));
    });

    change(items);
    page.fields = items.length ? { schema: SCHEMA, items } : null;
    if (this.layoutState() === before) return;
    this.history = pushHistory(this.history, label, before);
    this.storeHistory();
  },

  fieldsMoved({ page, field, label, items }) {
    if (page !== this.selected) this.select(page, field, false);
    this.field = field;
    this.changeFields(label, (rows) => items.forEach((rect) => {
      const item = rows.find((row) => row.id === rect.id);
      if (item) Object.assign(item, { x: rect.x, y: rect.y, width: rect.width, height: rect.height });
    }), items);
  },

  /** Zmiana wybranego pola. Zablokowane pole przyjmuje tylko odblokowanie. */
  changeField(label, change) {
    if (!this.editable || !this.fieldActive) return;
    if (this.fieldItem?.constraints.locked) return;
    this.changeFields(label, (rows) => {
      const item = rows.find((row) => row.id === this.field);
      if (item) change(item, rows);
    });
  },

  /** Wysokość z treścią: pole z długim tekstem nie może zejść poza stronę. */
  actualHeight(id) {
    return this.frame()?.gstDoc?.measure(this.selected).find((rect) => rect.id === id)?.actual ?? 0;
  },

  nudge(dx, dy) {
    this.changeField('Przesunięcie', (item) => Object.assign(item, moveRect(item, dx, dy, { height: this.actualHeight(item.id) })));
  },

  alignField(where) {
    this.changeField('Wyrównanie do strony', (item) => Object.assign(item, alignRect(item, where, this.actualHeight(item.id))));
  },

  alignText(align) {
    this.changeField('Wyrównanie tekstu', (item) => { item.style.align = align; });
  },

  layer(action) {
    this.changeField('Kolejność warstw', (item, rows) => {
      const order = reorder(rows, item.id, action);
      rows.forEach((row) => { row.z_index = order[row.id]; });
    });
  },

  toggleLock() {
    if (!this.editable || !this.fieldActive) return;
    const locked = !!this.fieldItem?.constraints.locked;
    this.changeFields(locked ? 'Odblokowanie pola' : 'Blokada pola', (rows) => {
      const item = rows.find((row) => row.id === this.field);
      if (item) item.constraints.locked = !locked;
    });
  },

  duplicateField() {
    if (!this.editable || !this.canDuplicate) return;
    const source = this.field;
    const text = this.frame()?.gstDoc?.fieldText(this.selected, source) || '';
    if (!text) return;
    let copyId = null;
    this.changeFields('Duplikat pola', (rows) => {
      const item = rows.find((row) => row.id === source);
      copyId = nextCopyId(rows);
      const role = item.style.role || (['heading', 'cover_heading'].includes(source) ? 'heading' : 'text');
      rows.push({
        ...JSON.parse(JSON.stringify(item)),
        ...moveRect(item, 3, 3, { height: this.actualHeight(source) }),
        id: copyId,
        text: text.slice(0, 600),
        z_index: Math.min(99, Math.max(...rows.map((row) => row.z_index)) + 1),
        style: { align: item.style.align, role },
        constraints: { ...item.constraints, locked: false },
      });
    });
    this.field = copyId;
    this.syncSelection();
  },

  removeCopy() {
    if (!this.editable || this.fieldItem?.text === undefined) return;
    const id = this.field;
    this.changeFields('Usunięcie kopii', (rows) => rows.splice(rows.findIndex((row) => row.id === id), 1));
    this.field = null;
  },

  resetLayout() {
    if (!this.editable || !this.pageState.fields) return;
    const before = this.layoutState();
    this.pageState.fields = null;
    this.field = null;
    this.history = pushHistory(this.history, 'Przywrócenie układu strony', before);
    this.storeHistory();
  },

  /*--- Tryb układu i migracja jednej wersji roboczej. Nic nie dzieje się samo: tylko z przycisku w tej wersji. ---*/

  setLayoutMode(template) {
    this.state.document.layout_mode = template ? 'template' : null;
    this.field = null;
  },

  /** Przypina pola do miejsc z szablonu. To tylko stan edytora: zapis dopiero po obejrzeniu wyniku. */
  startMigration() {
    if (!this.editable || this.migration) return;
    const before = this.layoutState();
    const mode = this.state.document.layout_mode;
    const moved = [];
    const skipped = [];
    Object.entries(this.frame()?.gstDoc?.measureAll() || {}).forEach(([key, rects]) => {
      const page = this.state.document.pages[key];
      if (page.fields || !rects.length) return;
      const label = this.pages.find((item) => item.key === key).label;
      // Strona wydłużona nadmiarem treści nie ma pozycji w obrębie A4: zostaje przy szablonie.
      if (rects.some((rect) => rect.x + rect.width > 100 || rect.y + rect.actual > 100)) {
        skipped.push(label);
        return;
      }
      page.fields = { schema: SCHEMA, items: rects.map((rect) => createItem(key, rect)) };
      moved.push(label);
    });
    this.state.document.layout_mode = null;
    this.migration = { before, mode, moved, skipped };
    clearTimeout(this.autosaveTimer);
  },

  cancelMigration() {
    if (!this.migration) return;
    const { before, mode } = this.migration;
    this.migration = null;
    this.state.document.layout_mode = mode;
    this.restoreLayout(before);
  },

  commitMigration() {
    if (!this.migration) return;
    this.history = pushHistory(this.history, 'Migracja do nowego układu', this.migration.before);
    this.migration = null;
    this.storeHistory();
  },

  restoreLayout(value) {
    Object.entries(JSON.parse(value)).forEach(([key, fields]) => { this.state.document.pages[key].fields = fields; });
    if (this.field && !this.movableIds.includes(this.field)) this.field = null;
    this.storeHistory();
  },

  undo() {
    if (this.migration) return;
    const step = undoHistory(this.history, this.layoutState());
    if (!step || !this.editable) return;
    this.history = step.history;
    this.restoreLayout(step.value);
  },

  redo() {
    if (this.migration) return;
    const step = redoHistory(this.history, this.layoutState());
    if (!step || !this.editable) return;
    this.history = step.history;
    this.restoreLayout(step.value);
  },

  /** Skrót z ramki lub z okna edytora. */
  onKey({ key, shift, control }) {
    if (!this.editable) return;
    const letter = key.toLowerCase();
    if (control) {
      if (letter === 'z') { if (shift) this.redo(); else this.undo(); }
      if (letter === 'y') this.redo();
      if (letter === 'd') this.duplicateField();
      return;
    }

    const step = (this.snap ? GRID : NUDGE_SMALL) * (shift ? 5 : 1);
    const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    if (moves[key]) this.nudge(...moves[key]);
    if (key === '[') this.layer('backward');
    if (key === ']') this.layer('forward');
    if ((key === 'Delete' || key === 'Backspace') && this.fieldItem?.text !== undefined) this.removeCopy();
    if (key === 'Escape') {
      this.field = null;
      this.syncSelection();
    }
  },

  windowKey(event) {
    if (event.target.closest?.('input, textarea, select, [contenteditable]')) return;
    const control = event.ctrlKey || event.metaKey;
    const shortcut = control ? ['z', 'y', 'd'].includes(event.key.toLowerCase()) : !!this.field && ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Delete', 'Backspace', 'Escape', '[', ']'].includes(event.key);
    if (!shortcut) return;
    event.preventDefault();
    this.onKey({ key: event.key, shift: event.shiftKey, control });
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
      const response = await fetch(`${this.urls.media}?q=${encodeURIComponent(picker.q)}&folder=${encodeURIComponent(picker.folder)}&page=${page}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
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
    if (this.pageState.image_id !== item.id) this.pageState.image_crop = null;
    this.pageState.image_id = item.id;
    this.picker.open = false;
  },

  removeImage() {
    this.pageState.image_id = null;
    this.pageState.image_crop = null;
  },

  /*--- Kadr zdjęcia: tylko wartości w dokumencie, plik pozostaje bez zmian ---*/

  cropValue(key) { return this.pageState.image_crop?.[key] ?? (key === 'zoom' ? 1 : 50); },
  setCrop(key, value) {
    this.pageState.image_crop = { x: this.cropValue('x'), y: this.cropValue('y'), zoom: this.cropValue('zoom'), [key]: Number(value) };
  },

  /*--- Dopracowanie strony (PagePolish): wartości domyślne nie są zapisywane ---*/

  polishValue(name, fallback = '') { return this.pageState.polish?.[name] ?? fallback; },
  setPolish(name, value) {
    const polish = { ...(this.pageState.polish || {}) };
    if (value === '' || value === 0 || value === false || value === null) delete polish[name];
    else polish[name] = value;
    this.pageState.polish = Object.keys(polish).length ? polish : null;
  },
  caseStudy(id) { return config.caseStudies.find((item) => item.id === id); },
  moveReference(index, delta) {
    const ids = this.state.document.reference_ids;
    const target = index + delta;
    if (target < 0 || target >= ids.length) return;
    ids.splice(target, 0, ids.splice(index, 1)[0]);
  },
  referenceCrop(id, key) { return this.pageState.polish?.image_crops?.[id]?.[key] ?? (key === 'zoom' ? 1 : 50); },
  setReferenceCrop(id, key, value) {
    const crop = { x: this.referenceCrop(id, 'x'), y: this.referenceCrop(id, 'y'), zoom: this.referenceCrop(id, 'zoom'), [key]: Number(value) };
    this.setPolish('image_crops', { ...(this.pageState.polish?.image_crops || {}), [id]: crop });
  },
  resetReferenceCrop(id) {
    const crops = { ...(this.pageState.polish?.image_crops || {}) };
    delete crops[id];
    this.setPolish('image_crops', Object.keys(crops).length ? crops : null);
  },

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
