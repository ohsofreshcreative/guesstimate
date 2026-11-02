/*--- GUESSTIMATE: UKŁAD PÓL — WSKAŹNIK I KLAWIATURA W RAMCE EDYTORA ---*/
// Stan układu należy do edytora (document-editor.js). Ramka tylko pokazuje ruch na żywo i po puszczeniu wysyła wynik.
// Pomiar względem getBoundingClientRect strony, więc przesunięcie jest takie samo w każdym powiększeniu.
//
// Zaznaczanie tekstu a przeciąganie: kliknięcie nigdy nie przesuwa (próg DRAG_START), a dwuklik włącza tryb tekstu,
// w którym wskaźnik zaznacza tekst zamiast przesuwać pole. Esc wychodzi z trybu.

import { moveRect, resizeRect, GRID } from './field-layout.js';

const DRAG_START = 4; // px
const sheetHeight = (box) => (box.width * 210) / 297; // strona może być wydłużona, a pozycje liczymy od wysokości A4
const HANDLES = { nw: [0, 0], n: [50, 0], ne: [100, 0], e: [100, 50], se: [100, 100], s: [50, 100], sw: [0, 100], w: [0, 50] };
const KEYS = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Delete', 'Backspace', 'Escape', '[', ']'];

export function createFieldStage({ main, post, settled }) {
  const root = document.documentElement;
  let options = { fields: {}, editable: false, snap: true, grid: false };
  let selection = { key: null, field: null };
  let drag = null;
  let skipClick = false;
  let pressed = null;

  const pageOf = (key) => main.querySelector(`[data-doc-page][data-page-key="${CSS.escape(key)}"]:not([data-continued])`);
  const fieldOf = (page, id) => page?.querySelector(`[data-edit="${CSS.escape(id)}"]`);
  const idsOf = (key) => options.fields[key] || [];

  const movable = (el) => {
    const page = el?.closest('[data-doc-page]');
    if (!options.editable || !page || page.dataset.continued || el === page) return false;
    return idsOf(page.dataset.pageKey).includes(el.dataset.edit) || el.hasAttribute('data-field-copy');
  };

  /**
   * Pozycja pola w procentach strony, także gdy pole jeszcze płynie w układzie szablonu.
   * `height` to wysokość zapisana (minimalna), `actual` to wysokość z treścią: tylko ta druga decyduje o dolnej krawędzi.
   */
  function rectOf(el, page) {
    const box = page.getBoundingClientRect();
    const rect = el.getBoundingClientRect();
    const style = el.getAttribute('style') || '';
    const round = (value) => Math.round(value * 100) / 100;
    return {
      id: el.dataset.edit,
      x: round(((rect.left - box.left) / box.width) * 100),
      y: round(((rect.top - box.top) / sheetHeight(box)) * 100),
      width: round((rect.width / box.width) * 100),
      height: Number(/--field-h:([\d.]+)/.exec(style)?.[1]) || round((rect.height / sheetHeight(box)) * 100),
      actual: round((rect.height / sheetHeight(box)) * 100),
      z_index: Number(/--field-z:(\d+)/.exec(style)?.[1] || 1),
      align: /--field-align:(\w+)/.exec(style)?.[1] || (['center', 'right'].includes(getComputedStyle(el).textAlign) ? getComputedStyle(el).textAlign : 'left'),
    };
  }

  function apply(el, page, rect) {
    page.setAttribute('data-free', '');
    el.dataset.field = rect.id;
    el.style.setProperty('--field-x', String(rect.x));
    el.style.setProperty('--field-y', String(rect.y));
    el.style.setProperty('--field-w', String(rect.width));
    el.style.setProperty('--field-h', String(rect.height));
    el.style.setProperty('--field-z', String(rect.z_index));
    el.style.setProperty('--field-align', rect.align);
  }

  /** Pola strony jako pozycje. Wszystkie naraz: po wyjęciu jednego z przepływu pozostałe by się przesunęły. */
  function measure(key) {
    const page = pageOf(key);
    if (!page) return [];
    return idsOf(key).concat(Array.from(page.querySelectorAll('[data-field-copy]'), (el) => el.dataset.edit))
      .map((id) => fieldOf(page, id))
      .filter(Boolean)
      .map((el) => rectOf(el, page));
  }

  /** Wszystkie strony: do migracji dokumentu. Pomiar wymaga widocznych stron (gstDoc.measureAll). */
  function measureAll() {
    return Object.fromEntries(Object.keys(options.fields).map((key) => [key, measure(key)]));
  }

  /** To samo co serwer w partials/heading: nagłówek wyjęty z przepływu nie może przesunąć treści pod nim. */
  function holdSpace(page, el) {
    if (idsOf(page.dataset.pageKey).length !== 1 || el.nextElementSibling?.classList.contains('osf-field-spacer')) return;
    const spacer = document.createElement('div');
    spacer.className = `${el.className} osf-field-spacer`;
    spacer.setAttribute('aria-hidden', 'true');
    spacer.textContent = el.textContent;
    el.after(spacer);
  }

  function adopt(page) {
    const rects = measure(page.dataset.pageKey);
    rects.forEach((rect) => {
      const el = fieldOf(page, rect.id);
      holdSpace(page, el);
      apply(el, page, rect);
    });
    return rects;
  }

  function paint(key = selection.key, field = selection.field) {
    selection = { key, field };
    main.querySelectorAll('.osf-selection').forEach((el) => el.remove());
    const page = key && field ? pageOf(key) : null;
    const el = fieldOf(page, field || '');
    if (!el || !movable(el)) return;

    const rect = rectOf(el, page);
    const box = document.createElement('div');
    box.className = 'osf-selection';
    box.dataset.selectionFor = field;
    const scale = sheetHeight(page.getBoundingClientRect()) / page.getBoundingClientRect().height;
    box.style.cssText = `left:${rect.x}%;top:${rect.y * scale}%;width:${rect.width}%;height:${rect.actual * scale}%`;

    if (el.hasAttribute('data-locked')) {
      box.dataset.locked = '';
    } else {
      Object.entries(HANDLES).forEach(([name, [x, y]]) => {
        const handle = document.createElement('span');
        handle.dataset.handle = name;
        handle.style.cssText = `--handle-x:${x}%;--handle-y:${y}%`;
        box.append(handle);
      });
    }

    page.append(box);
  }

  function leaveTextMode() {
    root.removeAttribute('data-text-mode');
  }

  function finish() {
    const current = drag;
    drag = null;
    window.removeEventListener('pointermove', onMove);
    window.removeEventListener('pointerup', onUp);
    window.removeEventListener('pointercancel', onCancel);
    if (current.active) current.page.releasePointerCapture?.(current.pointer);
    return current;
  }

  function onMove(event) {
    const moved = Math.hypot(event.clientX - drag.startX, event.clientY - drag.startY);
    if (!drag.active) {
      if (drag.handle === null && moved < DRAG_START) return;
      drag.active = true;
      // Dopiero tutaj: przechwycenie przy naciśnięciu przekierowałoby zwykłe kliknięcie na całą stronę.
      drag.page.setPointerCapture?.(drag.pointer);
      drag.adopted = adopt(drag.page);
      drag.start = drag.adopted.find((rect) => rect.id === drag.id);
      document.getSelection()?.removeAllRanges();
    }

    const box = drag.page.getBoundingClientRect();
    const dx = ((event.clientX - drag.startX) / box.width) * 100;
    const dy = ((event.clientY - drag.startY) / sheetHeight(box)) * 100;
    const step = options.snap && !event.altKey ? GRID : 0;

    drag.rect = drag.handle
      ? resizeRect(drag.start, drag.handle, dx, dy, { step })
      : moveRect(drag.start, dx, dy, { step, height: drag.start.actual });
    apply(drag.el, drag.page, drag.rect);
    paint();
  }

  function onUp() {
    const current = finish();
    if (current.active) {
      skipClick = true;
      setTimeout(() => { skipClick = false; }, 0);
      post({
        type: 'fields',
        page: current.page.dataset.pageKey,
        field: current.id,
        label: current.handle ? 'Zmiana rozmiaru' : 'Przesunięcie',
        items: current.adopted.map((rect) => (rect.id === current.id ? { ...current.rect, id: rect.id } : rect)),
      });
    }
    settled();
  }

  function onCancel() {
    const current = finish();
    if (current.active) current.adopted.forEach((rect) => apply(fieldOf(current.page, rect.id), current.page, rect));
    paint();
    settled();
  }

  /** Zaznaczone pole ma pierwszeństwo: inaczej pola przykrytego innym nie dałoby się chwycić. */
  function underPointer(event) {
    const selected = selection.field ? fieldOf(pageOf(selection.key), selection.field) : null;
    const rect = selected?.getBoundingClientRect();
    const inside = rect && event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom;
    return inside ? selected : event.target.closest('[data-edit]');
  }

  function onDown(event) {
    pressed = { x: event.clientX, y: event.clientY };
    if (event.button !== 0 || !options.editable) return;
    const handle = event.target.closest('[data-handle]');
    const el = handle ? fieldOf(event.target.closest('[data-doc-page]'), event.target.closest('.osf-selection').dataset.selectionFor) : underPointer(event);
    if (!movable(el) || el.hasAttribute('data-locked')) return;
    if (root.hasAttribute('data-text-mode')) {
      if (el.dataset.edit === selection.field) return;
      leaveTextMode();
    }

    const page = el.closest('[data-doc-page]');
    window.focus();
    drag = { el, page, id: el.dataset.edit, handle: handle ? handle.dataset.handle : null, startX: event.clientX, startY: event.clientY, active: false, pointer: event.pointerId };
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
    window.addEventListener('pointercancel', onCancel);
  }

  function onDouble(event) {
    const el = event.target.closest('[data-edit]');
    if (!movable(el)) return;
    root.setAttribute('data-text-mode', '');
    post({ type: 'edit', page: el.closest('[data-doc-page]').dataset.pageKey, field: el.dataset.edit, focus: true });
  }

  function onKey(event) {
    const control = event.ctrlKey || event.metaKey;
    const letter = control && ['z', 'y', 'd'].includes(event.key.toLowerCase());
    if (!letter && !KEYS.includes(event.key)) return;

    if (event.key === 'Escape') {
      if (drag) onCancel();
      if (root.hasAttribute('data-text-mode')) {
        leaveTextMode();
        return;
      }
    }

    if (selection.field || letter) event.preventDefault();
    post({ type: 'key', key: event.key, shift: event.shiftKey, control });
  }

  main.addEventListener('pointerdown', onDown);
  main.addEventListener('dblclick', onDouble);
  document.addEventListener('keydown', onKey);

  return {
    paint,
    measure,
    measureAll,
    movable: (el) => movable(el),
    busy: () => !!drag,
    configure(next) {
      options = { ...options, ...next };
      root.classList.toggle('osf-show-grid', !!options.grid);
      paint();
    },
    text: (key, field) => fieldOf(pageOf(key), field)?.innerText?.trim() || '',
    /** Czy kliknięcie to koniec przeciągania (także zablokowanego pola), a nie wybór elementu. */
    consumeClick(event) {
      const moved = pressed && Math.hypot(event.clientX - pressed.x, event.clientY - pressed.y) >= DRAG_START;
      const skip = skipClick || moved;
      skipClick = false;
      return skip;
    },
  };
}
