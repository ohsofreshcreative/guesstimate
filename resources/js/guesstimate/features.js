/*--- GUESSTIMATE: FUNKCJONALNOŚCI OFERTY + ESTYMATOR AI ---*/
// AI zwraca wyłącznie propozycję; godziny trafiają do formularza dopiero po „Zastosuj estymację”,
// a do oferty — dopiero po zapisaniu kroku przez użytkownika.

import { postJson } from './http.js';

export default (initial, blank, aiUrl) => ({
  rows: initial,
  dirty: false,
  quick: { name: '', description: '', hours_min: '', hours_max: '' },
  ai: { open: false, target: null, name: '', description: '', messages: [], input: '', loading: false, error: null, result: null },

  init() {
    this.$watch('rows', () => {
      this.dirty = true;
      window.dispatchEvent(new CustomEvent('gst-recalc'));
    }, { deep: true });
    this.$nextTick(() => { this.dirty = false; });
  },

  has(featureId) {
    return this.rows.some((row) => String(row.feature_id) === String(featureId));
  },

  toggle(item) {
    const index = this.rows.findIndex((row) => String(row.feature_id) === String(item.feature_id));
    if (index >= 0) this.rows.splice(index, 1);
    else this.rows.push({ ...item });
  },

  remove(index) {
    this.rows.splice(index, 1);
  },

  addQuick() {
    if (!this.quick.name.trim()) return;
    this.rows.push({ ...blank, ...this.quick, name: this.quick.name.trim() });
    this.quick = { name: '', description: '', hours_min: '', hours_max: '' };
  },

  /*--- estymator AI ---*/
  openAi(index = null) {
    const source = index === null ? this.quick : this.rows[index];
    this.ai = { open: true, target: index, name: source.name || '', description: source.description || '', messages: [], input: '', loading: false, error: null, result: null };
    this.$dispatch('open-modal', 'ai-estimate');
  },

  async askAi() {
    if (this.ai.loading) return;
    const text = this.ai.input.trim();
    if (text) this.ai.messages.push({ role: 'user', content: text });
    this.ai.input = '';
    this.ai.loading = true;
    this.ai.error = null;

    try {
      const result = await postJson(aiUrl, { name: this.ai.name, description: this.ai.description, messages: this.ai.messages });
      this.ai.result = result;
      this.ai.messages.push({ role: 'assistant', content: this.summary(result) });
    } catch (error) {
      this.ai.error = error.message;
      if (text) this.ai.messages.pop();
      this.ai.input = text;
    } finally {
      this.ai.loading = false;
    }
  },

  summary(result) {
    const parts = [result.reply];
    if (result.hours_min !== null && result.hours_max !== null) parts.push(`Szacunek: ${result.hours_min}–${result.hours_max} h.`);
    if (result.questions?.length) parts.push(`Pytania: ${result.questions.join(' ')}`);
    return parts.filter(Boolean).join('\n');
  },

  applyAi() {
    const r = this.ai.result;
    if (!r || r.hours_min === null || r.hours_max === null) return;
    const hours = { hours_min: String(r.hours_min).replace('.', ','), hours_max: String(r.hours_max).replace('.', ',') };
    const description = this.ai.description || (r.scope?.length ? r.scope.join('; ') : '');
    const notes = [
      r.assumptions?.length ? `Założenia: ${r.assumptions.join('; ')}` : '',
      r.risks?.length ? `Ryzyka: ${r.risks.join('; ')}` : '',
    ].filter(Boolean).join('\n');

    if (this.ai.target === null) {
      this.rows.push({ ...blank, name: this.ai.name || r.name || 'Funkcjonalność indywidualna', description, notes, ...hours });
      this.quick = { name: '', description: '', hours_min: '', hours_max: '' };
    } else {
      Object.assign(this.rows[this.ai.target], hours, { notes: [this.rows[this.ai.target].notes, notes].filter(Boolean).join('\n') });
    }

    this.$dispatch('close-modal', 'ai-estimate');
  },
});
