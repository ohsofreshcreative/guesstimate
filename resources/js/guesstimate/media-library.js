/*--- GUESSTIMATE: BIBLIOTEKA GRAFIK ---*/
// Pliki wysyłane są kolejno, po jednym na żądanie: błąd pojedynczego pliku nie przerywa pozostałych,
// a limit post_max_size serwera dotyczy jednego pliku, nie całej paczki.

import { getJson, nonce, postJson } from './http.js';

const EXTENSIONS = { 'image/jpeg': 'JPG', 'image/png': 'PNG', 'image/webp': 'WebP' };

export const formatBytes = (bytes) => {
  if (!bytes) return '—';
  return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB` : `${Math.ceil(bytes / 1024)} KB`;
};

export default (config) => ({
  canUpload: config.canUpload,
  canManage: config.canManage,
  folders: config.folders,
  tags: config.tags,
  licenses: config.licenses,
  urls: config.urls,
  view: 'grid',
  q: '',
  folder: null,
  activeTags: [],
  archived: false,
  items: [],
  page: 1,
  more: false,
  loading: false,
  error: null,
  message: null,
  queue: [],
  dragging: false,
  detail: null,
  form: null,
  errors: {},
  saving: false,
  confirming: false,
  folderName: '',

  init() {
    this.load(true);
  },

  get formats() { return config.mimes.map((mime) => EXTENSIONS[mime] || mime).join(', '); },
  get limit() { return formatBytes(config.maxBytes); },
  get uploading() { return this.queue.some((row) => row.status === 'pending' || row.status === 'sending'); },
  get currentFolder() { return this.folders.find((folder) => folder.id === this.folder) || null; },

  /*--- Lista ---*/

  query(page) {
    const params = new URLSearchParams({ q: this.q, page });
    if (this.folder) params.set('folder', this.folder);
    this.activeTags.forEach((id) => params.append('tag[]', id));
    if (this.archived) params.set('archived', '1');
    return `${this.urls.items}?${params}`;
  },

  async load(reset = false) {
    if (this.loading) return;
    this.loading = true;
    this.error = null;
    try {
      const page = reset ? 1 : this.page + 1;
      const data = await getJson(this.query(page));
      this.items = reset ? data.items : this.items.concat(data.items);
      this.page = page;
      this.more = data.more;
    } catch (error) {
      this.error = error.message;
    } finally {
      this.loading = false;
    }
  },

  setFolder(id) {
    this.folder = id;
    this.archived = false;
    this.load(true);
  },

  toggleTag(id) {
    this.activeTags = this.activeTags.includes(id) ? this.activeTags.filter((tag) => tag !== id) : [...this.activeTags, id];
    this.load(true);
  },

  setArchived(value) {
    this.archived = value;
    this.load(true);
  },

  /*--- Przesyłanie ---*/

  addFiles(files) {
    if (!this.canUpload) return;
    Array.from(files).forEach((file) => {
      const error = !config.mimes.includes(file.type)
        ? `Obsługiwane formaty: ${this.formats}.`
        : (file.size > config.maxBytes ? `Plik może mieć najwyżej ${this.limit}.` : null);
      this.queue.push({ key: `${file.name}-${file.lastModified}-${this.queue.length}`, file, name: file.name, status: error ? 'error' : 'pending', error, note: null });
    });
    this.processQueue();
  },

  async processQueue() {
    if (this.queue.some((row) => row.status === 'sending')) return;
    const row = this.queue.find((entry) => entry.status === 'pending');
    if (!row) {
      await this.load(true);
      return;
    }

    row.status = 'sending';
    try {
      const body = new FormData();
      body.append('file', row.file);
      if (this.folder) body.append('folder', this.folder);
      const response = await fetch(this.urls.items, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-GST-Nonce': nonce() }, body });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(data?.error || `Błąd serwera (${response.status}).`);
      row.status = 'done';
      row.note = data.duplicate ? 'Ten plik jest już w bibliotece — użyto istniejącego.' : null;
    } catch (error) {
      row.status = 'error';
      row.error = error.message;
    }
    this.processQueue();
  },

  clearQueue() { this.queue = this.queue.filter((row) => row.status === 'pending' || row.status === 'sending'); },

  drop(event) {
    this.dragging = false;
    this.addFiles(event.dataTransfer.files);
  },

  /*--- Podgląd i opis ---*/

  async open(item) {
    this.confirming = false;
    this.errors = {};
    this.message = null;
    try {
      this.show(await getJson(`${this.urls.items}/${item.id}`));
    } catch (error) {
      this.error = error.message;
    }
  },

  show(detail) {
    this.detail = detail;
    this.form = {
      title: detail.title,
      alt: detail.alt,
      author: detail.author,
      license: detail.license,
      source_url: detail.source_url,
      folder: detail.folder || '',
      tags: detail.tags.join(', '),
    };
  },

  close() {
    this.detail = null;
    this.form = null;
  },

  bytes(value) { return formatBytes(value); },

  async save() {
    this.saving = true;
    this.errors = {};
    try {
      const data = await postJson(this.detail.urls.update, this.form);
      this.tags = data.tags_all;
      this.folders = data.folders;
      this.show(data);
      this.message = 'Zapisano opis grafiki.';
      this.load(true);
    } catch (error) {
      this.errors = error.data?.errors || { title: error.message };
    } finally {
      this.saving = false;
    }
  },

  async remove() {
    if (!this.confirming) {
      this.confirming = true;
      return;
    }
    this.saving = true;
    try {
      const data = await postJson(this.detail.urls.destroy, {});
      this.message = data.message;
      this.close();
      this.load(true);
    } catch (error) {
      this.errors = { title: error.message };
    } finally {
      this.saving = false;
      this.confirming = false;
    }
  },

  async restore() {
    this.saving = true;
    try {
      this.show(await postJson(this.detail.urls.restore, {}));
      this.message = 'Grafika wróciła do biblioteki.';
      this.load(true);
    } catch (error) {
      this.errors = { title: error.message };
    } finally {
      this.saving = false;
    }
  },

  /*--- Foldery (manager, administrator) ---*/

  async addFolder() {
    const name = this.folderName.trim();
    if (!name) return;
    this.error = null;
    try {
      const data = await postJson(this.urls.folders, { name, parent: this.folder || 0 });
      this.folders = data.folders;
      this.folderName = '';
    } catch (error) {
      this.error = error.message;
    }
  },

  async renameFolder() {
    const folder = this.currentFolder;
    const name = folder && window.prompt('Nowa nazwa folderu', folder.name);
    if (!name || name.trim() === folder.name) return;
    this.error = null;
    try {
      this.folders = (await postJson(`${this.urls.folders}/${folder.id}`, { name: name.trim() })).folders;
    } catch (error) {
      this.error = error.message;
    }
  },

  async deleteFolder() {
    const folder = this.currentFolder;
    if (!folder || !window.confirm(`Usunąć folder „${folder.name}”? Grafiki zostaną w bibliotece, bez folderu.`)) return;
    this.error = null;
    try {
      this.folders = (await postJson(`${this.urls.folders}/${folder.id}/usun`, {})).folders;
      this.setFolder(null);
    } catch (error) {
      this.error = error.message;
    }
  },
});
