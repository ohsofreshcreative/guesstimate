/*--- GUESSTIMATE: LISTY POZYCJI (funkcjonalności, prace, koszty) ---*/

export default (initial, blank) => ({
  rows: initial,
  dirty: false,

  init() {
    // Dodanie/usunięcie wiersza zmienia wycenę (prace dodatkowe, koszty) — panel na żywo przelicza się sam.
    this.$watch('rows', () => window.dispatchEvent(new CustomEvent('gst-recalc')), { deep: true });
  },

  add(row = null) {
    this.rows.push({ ...(row || blank) });
    this.dirty = true;
    this.$nextTick(() => {
      const inputs = this.$root.querySelectorAll('[data-row] input[data-first]');
      inputs[inputs.length - 1]?.focus();
    });
  },

  remove(index) {
    this.rows.splice(index, 1);
    this.dirty = true;
  },

  has(featureId) {
    return this.rows.some((row) => String(row.feature_id) === String(featureId));
  },
});
