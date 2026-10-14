/*--- GUESSTIMATE: UPROSZCZONA MAPA STRONY ---*/
// Płaska lista podstron. Każda podstrona = 1 jednostka kalkulacyjna z domyślnymi godzinami oferty;
// pojedyncze godziny można nadpisać w „Ustawieniach zaawansowanych”. Liczniki to podgląd — wiążące wyliczenia robi serwer.

const FIELDS = ['hours_ui_min', 'hours_ui_max', 'hours_dev_min', 'hours_dev_max'];
const num = (value) => {
  const parsed = parseFloat(String(value ?? '').replace(/\s/g, '').replace(',', '.'));
  return Number.isFinite(parsed) ? parsed : null;
};
const fmt = (value) => (Math.round(value * 100) / 100).toLocaleString('pl-PL', { maximumFractionDigits: 2 });

export default (initial, defaults, typeUnits) => ({
  pages: initial.map((page) => ({ ...page, advanced: FIELDS.some((f) => page[f] !== '' && page[f] !== null) })),
  defaults,
  typeUnits: num(typeUnits) || 0,
  newName: '',
  bulk: '',
  showBulk: false,
  dragIndex: null,
  dirty: false,

  init() {
    this.$watch('pages', () => {
      this.dirty = true;
      window.dispatchEvent(new CustomEvent('gst-recalc'));
    }, { deep: true });
    this.$nextTick(() => { this.dirty = false; });
  },

  blank(name) {
    return { name, hours_ui_min: '', hours_ui_max: '', hours_dev_min: '', hours_dev_max: '', advanced: false };
  },

  add() {
    const name = this.newName.trim();
    if (!name) return;
    this.pages.push(this.blank(name));
    this.newName = '';
  },

  addBulk() {
    this.bulk.split('\n').map((line) => line.replace(/^[\s\-–•*\d.)]+/, '').trim()).filter(Boolean).forEach((name) => this.pages.push(this.blank(name.slice(0, 190))));
    this.bulk = '';
    this.showBulk = false;
  },

  remove(index) {
    this.pages.splice(index, 1);
  },

  duplicate(index) {
    const copy = { ...this.pages[index], name: `${this.pages[index].name} (kopia)`.slice(0, 190) };
    this.pages.splice(index + 1, 0, copy);
  },

  move(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= this.pages.length) return;
    const [page] = this.pages.splice(index, 1);
    this.pages.splice(target, 0, page);
    this.$nextTick(() => this.$root.querySelectorAll('[data-page-name]')[target]?.focus());
  },

  drop(index) {
    if (this.dragIndex === null || this.dragIndex === index) return;
    const [page] = this.pages.splice(this.dragIndex, 1);
    this.pages.splice(index, 0, page);
    this.dragIndex = null;
  },

  hours(page, field) {
    const own = num(page[`hours_${field}`]);
    return own === null ? (num(this.defaults[field]) || 0) : own;
  },

  get stats() {
    const sum = { ui_min: 0, ui_max: 0, dev_min: 0, dev_max: 0 };
    this.pages.forEach((page) => Object.keys(sum).forEach((key) => { sum[key] += this.hours(page, key); }));
    Object.keys(sum).forEach((key) => { sum[key] += this.typeUnits * (num(this.defaults[key]) || 0); });

    const range = (min, max) => (min === max ? `${fmt(min)} h` : `${fmt(min)}–${fmt(max)} h`);

    return {
      pages: this.pages.length,
      units: fmt(this.typeUnits),
      total: fmt(this.pages.length + this.typeUnits),
      ui: range(sum.ui_min, sum.ui_max),
      dev: range(sum.dev_min, sum.dev_max),
    };
  },

  get payload() {
    return JSON.stringify(this.pages.map((page) => {
      const row = { name: page.name };
      FIELDS.forEach((f) => { row[f] = page[f] ?? ''; });
      return row;
    }));
  },
});
