/*--- GUESSTIMATE: WYBÓR GRAFIKI Z BIBLIOTEKI DO ZWYKŁEGO FORMULARZA ---*/
// Wybrany plik trafia do ukrytego pola `attachment_id`; formularz zapisuje się jak dotąd.

import { getJson } from './http.js';

export default ({ url = '', id = null, thumb = null } = {}) => ({
  open: false,
  items: [],
  q: '',
  page: 1,
  more: false,
  loading: false,
  error: null,
  id,
  thumb,

  show() {
    this.open = true;
    if (!this.items.length) this.load(true);
  },

  async load(reset = false) {
    if (this.loading) return;
    this.loading = true;
    this.error = null;

    try {
      const page = reset ? 1 : this.page + 1;
      const data = await getJson(`${url}?q=${encodeURIComponent(this.q)}&page=${page}`);
      this.items = reset ? data.items : this.items.concat(data.items);
      this.page = page;
      this.more = data.more;
    } catch (error) {
      this.error = error.message;
    } finally {
      this.loading = false;
    }
  },

  pick(item) {
    this.id = item.id;
    this.thumb = item.thumb;
    this.open = false;
  },
});
