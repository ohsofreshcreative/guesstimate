/*--- GUESSTIMATE: EDYTOR SZABLONÓW UMÓW ---*/
// contenteditable na trzech częściach dokumentu: nagłówek, treść, stopka. Bez bibliotek, jak richText.
// HTML jest zawsze czyszczony po stronie serwera (ContractHtml::clean) — tu dbamy tylko o wygodę.
// Tekst to treść prawna: edytor nigdy nie zmienia go sam, a wklejanie przyjmuje czysty tekst.

import { postJson } from './http.js';
import { renderSheets } from './contract-sheets.js';

const REGIONS = ['header', 'body', 'footer'];
const STYLES = {
  p: { tag: 'p' }, h1: { tag: 'h1' }, h2: { tag: 'h2' }, h3: { tag: 'h3' },
  par: { tag: 'p', cls: 'ct-par' }, small: { tag: 'p', cls: 'ct-small' },
};
const ALIGN = ['ct-center', 'ct-right', 'ct-justify'];
const BLOCKS = 'p,h1,h2,h3,li,td,th';
const HISTORY = 100;
const escape = (text) => text.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));

export default (config) => ({
  ...config,
  mode: 'edit',
  dirty: false,
  busy: false,
  status: '',
  error: '',
  reviewed: false,
  overflow: 0,
  pageCount: 0,
  range: null,
  past: [],
  future: [],

  init() {
    if (!this.editable) return;
    document.execCommand('defaultParagraphSeparator', false, 'p');
    // Starsze szkice mogą mieć pusty akapit bez wysokości, w który nie da się kliknąć.
    this.$refs.body.querySelectorAll('p:empty').forEach((p) => p.append(document.createElement('br')));
    document.addEventListener('selectionchange', () => this.remember());
    this.past = [this.snapshot()];
  },

  region(name) { return this.$refs[name]; },

  regionOf(node) {
    return REGIONS.map((name) => this.region(name)).find((el) => el?.contains(node)) || null;
  },

  remember() {
    const selection = window.getSelection();
    if (selection?.rangeCount && this.regionOf(selection.anchorNode)) this.range = selection.getRangeAt(0).cloneRange();
  },

  // Lista stylów i zmiennych zabiera fokus, więc kursor trzeba przywrócić przed poleceniem.
  restore() {
    const area = this.range ? this.regionOf(this.range.startContainer) : null;
    if (!area) {
      this.$refs.body.focus();
      return;
    }
    area.focus();
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(this.range);
  },

  block() {
    const node = this.range?.startContainer;
    if (!node) return null;
    const element = node.nodeType === 1 ? node : node.parentElement;
    const block = element.closest(BLOCKS);
    return block && this.regionOf(block) ? block : null;
  },

  // Lista działań zabiera fokus, ale zaznaczenie zwykle zostaje; zapamiętany kursor jest tylko rezerwą.
  cell() {
    const live = window.getSelection();
    const node = live?.rangeCount && this.regionOf(live.anchorNode) ? live.anchorNode : this.range?.startContainer;
    const element = node?.nodeType === 1 ? node : node?.parentElement;
    return element?.closest('td,th') || null;
  },

  snapshot() {
    return Object.fromEntries(REGIONS.map((name) => [name, this.region(name).innerHTML]));
  },

  apply(state) {
    REGIONS.forEach((name) => { this.region(name).innerHTML = state[name]; });
    this.range = null;
  },

  // Własna historia: tabele i klasy zmieniamy na DOM, czego natywne cofanie nie widzi.
  record() {
    const state = this.snapshot();
    const last = this.past[this.past.length - 1];
    if (REGIONS.every((name) => last?.[name] === state[name])) return;
    this.past.push(state);
    if (this.past.length > HISTORY) this.past.shift();
    this.future = [];
    this.dirty = true;
    this.status = '';
  },

  undo() {
    if (this.past.length < 2) return;
    this.future.push(this.past.pop());
    this.apply(this.past[this.past.length - 1]);
    this.dirty = true;
  },

  redo() {
    if (!this.future.length) return;
    const state = this.future.pop();
    this.past.push(state);
    this.apply(state);
    this.dirty = true;
  },

  // Fokus w dokumencie to aktualny kursor; przywracanie zapamiętanego kasowałoby włączony format pisania.
  ensureCaret() {
    if (!this.regionOf(document.activeElement)) this.restore();
  },

  // Chrome zostawia listę w akapicie, czego serwer nie zapisze w tym kształcie. Edytor ma pokazywać to, co powstanie.
  liftLists(area) {
    const nested = () => area.querySelector('p > ul, p > ol, p > p');
    const item = this.currentItem();

    for (let list = nested(); list; list = nested()) {
      const parent = list.parentElement;
      parent.after(list);
      if (!parent.textContent.trim() && !parent.children.length) parent.remove();
    }

    if (item?.isConnected) {
      const selection = window.getSelection();
      selection.selectAllChildren(item);
      selection.collapseToEnd();
    }
  },

  currentItem() {
    const node = window.getSelection()?.anchorNode;
    const element = node?.nodeType === 1 ? node : node?.parentElement;
    return element?.closest('li') || null;
  },

  cmd(command, value = null) {
    this.ensureCaret();
    document.execCommand(command, false, value);
    const area = this.regionOf(document.activeElement);
    if (area) this.liftLists(area);
    this.remember();
    this.record();
  },

  setStyle(key) {
    const style = STYLES[key];
    if (!style) return;
    this.cmd('formatBlock', `<${style.tag}>`);
    const block = this.block();
    if (block) {
      block.classList.remove('ct-par', 'ct-small');
      if (style.cls) block.classList.add(style.cls);
      if (!block.classList.length) block.removeAttribute('class');
    }
    this.record();
  },

  align(cls = null) {
    this.ensureCaret();
    this.remember();
    const block = this.block();
    if (!block) return;
    block.classList.remove(...ALIGN);
    if (cls) block.classList.add(cls);
    if (!block.classList.length) block.removeAttribute('class');
    this.record();
  },

  insertBreak() {
    this.ensureCaret();
    this.remember();
    if (this.regionOf(this.range?.startContainer) !== this.$refs.body) {
      this.error = 'Podział strony można wstawić tylko w treści dokumentu.';
      return;
    }
    this.error = '';
    const rule = document.createElement('hr');
    rule.className = 'ct-break';
    this.insertBlock(rule);
  },

  // insertHTML wypycha takie elementy poza akapit i zostawia kursor poza blokiem, dlatego wstawiamy je przez DOM.
  topBlock(area) {
    let node = window.getSelection().getRangeAt(0).startContainer;
    if (node === area) return area.children[window.getSelection().getRangeAt(0).startOffset] || area.lastElementChild;
    while (node.parentNode !== area) node = node.parentNode;
    return node;
  },

  // Blok po bieżącym akapicie; pusty akapit jest zastępowany. Za blokiem zawsze zostaje miejsce na kursor.
  insertBlock(element) {
    const area = this.regionOf(window.getSelection().anchorNode) || this.$refs.body;
    const current = this.topBlock(area);
    const spacer = document.createElement('p');
    spacer.append(document.createElement('br'));

    if (current) {
      current.after(element);
      if (current.nodeType === 1 && current.matches('p') && !current.textContent.trim() && !current.querySelector('[data-var]')) current.remove();
    } else {
      area.append(element);
    }

    element.after(spacer);
    const selection = window.getSelection();
    selection.selectAllChildren(spacer);
    selection.collapseToStart();
    this.remember();
    this.record();
  },

  insertVariable(key) {
    if (!key) return;
    const label = this.labels[key] || key;
    this.ensureCaret();
    const selection = window.getSelection();
    const range = selection.getRangeAt(0);
    const chip = document.createElement('span');
    chip.className = 'ct-var';
    chip.dataset.var = key;
    chip.contentEditable = 'false';
    chip.textContent = label;
    // Za polem musi być znak, inaczej przeglądarka nie ma gdzie postawić kursora.
    const after = document.createTextNode('\u00A0');
    range.deleteContents();
    range.insertNode(after);
    range.insertNode(chip);
    range.setStartAfter(after);
    range.collapse(true);
    selection.removeAllRanges();
    selection.addRange(range);
    this.remember();
    this.record();
  },

  insertTable(rows = 2, columns = 2) {
    this.ensureCaret();
    const table = document.createElement('table');
    table.innerHTML = `<tbody>${`<tr>${'<td><br></td>'.repeat(columns)}</tr>`.repeat(rows)}</tbody>`;
    this.insertBlock(table);
  },

  tableAction(action) {
    const cell = this.cell();
    if (!cell) {
      this.error = 'Ustaw kursor w komórce tabeli.';
      return;
    }
    this.error = '';
    const row = cell.parentElement;
    const table = row.closest('table');
    const column = cell.cellIndex;
    const rows = [...table.rows];

    if (action === 'row') {
      const clone = row.cloneNode(false);
      [...row.cells].forEach(() => clone.insertCell().append(document.createElement('br')));
      row.after(clone);
    } else if (action === 'column') {
      rows.forEach((r) => r.insertCell(Math.min(column + 1, r.cells.length)).append(document.createElement('br')));
    } else if (action === 'deleteRow') {
      if (rows.length > 1) row.remove(); else table.remove();
    } else if (action === 'deleteColumn') {
      if (row.cells.length > 1) rows.forEach((r) => r.cells[Math.min(column, r.cells.length - 1)].remove()); else table.remove();
    } else if (action === 'deleteTable') {
      table.remove();
    }

    this.record();
  },

  paste(event) {
    event.preventDefault();
    const text = event.clipboardData?.getData('text/plain') || '';
    const lines = text.split(/\r?\n/).map((line) => line.trimEnd()).filter((line, i, all) => line || (i > 0 && all[i - 1]));
    const html = lines.filter(Boolean).map((line) => `<p>${escape(line)}</p>`).join('');
    document.execCommand('insertHTML', false, html);
    this.record();
  },

  keydown(event) {
    if (!(event.ctrlKey || event.metaKey)) return;
    const key = event.key.toLowerCase();
    if (key === 'z') {
      event.preventDefault();
      if (event.shiftKey) this.redo(); else this.undo();
    } else if (key === 'y') {
      event.preventDefault();
      this.redo();
    } else if (key === 's') {
      event.preventDefault();
      this.save();
    } else if (['b', 'i', 'u'].includes(key)) {
      event.preventDefault();
      this.cmd({ b: 'bold', i: 'italic', u: 'underline' }[key]);
    }
  },

  async save() {
    if (!this.editable || this.busy) return false;
    this.busy = true;
    this.error = '';

    try {
      const state = this.snapshot();
      const data = await postJson(this.urls.save, { ...state, name: this.name, description: this.description, hash: this.hash });
      this.hash = data.hash;
      // Serwer pokazuje, co faktycznie zapisał; kursora nie ruszamy, gdy tekst jest taki sam.
      REGIONS.forEach((name) => { if (data.regions[name] !== state[name]) this.region(name).innerHTML = data.regions[name]; });
      this.past = [this.snapshot()];
      this.future = [];
      this.dirty = false;
      this.status = 'Zapisano';
      return true;
    } catch (error) {
      this.error = error.message;
      return false;
    } finally {
      this.busy = false;
    }
  },

  async approve() {
    if (this.busy) return;
    if (!window.confirm('Zatwierdzić tę wersję? Po zatwierdzeniu nie można jej zmienić, a poprawka to nowa wersja.')) return;
    if (!(await this.save())) return;
    this.busy = true;

    try {
      const data = await postJson(this.urls.approve, { hash: this.hash, reviewed: this.reviewed });
      this.dirty = false;
      window.location.href = data.redirect;
    } catch (error) {
      this.error = error.message;
      this.busy = false;
    }
  },

  togglePreview() {
    this.mode = this.mode === 'edit' ? 'preview' : 'edit';
    if (this.mode === 'preview') this.$nextTick(() => this.renderPreview());
  },

  renderPreview() {
    const { pages, overflow } = renderSheets(this.$refs.sheets, {
      header: this.region('header').innerHTML,
      footer: this.region('footer').innerHTML,
      nodes: [...this.region('body').children],
    });

    this.pageCount = pages;
    this.overflow = overflow;
  },
});
