/*--- GUESSTIMATE: UKŁAD PÓL — CZYSTA LOGIKA ---*/
// Pozycje w procentach strony A4 (x, width: szerokość; y, height: wysokość). Bez DOM, więc działa tak samo w każdym
// powiększeniu i ma testy w node (tests/js). Ograniczenia muszą zgadzać się z App\Guesstimate\Document\FieldLayout.

export const SCHEMA = 1;
export const GRID = 2;
export const MIN_WIDTH = 5;
export const MIN_HEIGHT = 2;
const HISTORY_LIMIT = 50;

const round = (value) => Math.round(value * 100) / 100;
const clamp = (value, min, max) => Math.min(Math.max(value, min), Math.max(min, max));
const snapTo = (value, step) => (step ? Math.round(value / step) * step : value);

/**
 * Przesunięcie o (dx, dy). `height` to rzeczywista wysokość pola: treść bywa wyższa od zapisanej wysokości minimalnej.
 */
export function moveRect(rect, dx, dy, { step = 0, height = rect.height } = {}) {
  const bottom = Math.max(height, rect.height);
  return {
    ...rect,
    x: round(clamp(snapTo(rect.x + dx, step), 0, 100 - rect.width)),
    y: round(clamp(snapTo(rect.y + dy, step), 0, 100 - bottom)),
  };
}

/**
 * Zmiana rozmiaru uchwytem n, s, e, w lub narożnym: przeciwna krawędź zostaje w miejscu.
 */
export function resizeRect(rect, handle, dx, dy, { step = 0, minWidth = MIN_WIDTH, minHeight = MIN_HEIGHT } = {}) {
  let left = rect.x;
  let top = rect.y;
  let right = rect.x + rect.width;
  let bottom = rect.y + rect.height;

  if (handle.includes('w')) left = clamp(snapTo(left + dx, step), 0, right - minWidth);
  if (handle.includes('e')) right = clamp(snapTo(right + dx, step), left + minWidth, 100);
  if (handle.includes('n')) top = clamp(snapTo(top + dy, step), 0, bottom - minHeight);
  if (handle.includes('s')) bottom = clamp(snapTo(bottom + dy, step), top + minHeight, 100);

  return { ...rect, x: round(left), y: round(top), width: round(right - left), height: round(bottom - top) };
}

/**
 * Wyrównanie do krawędzi lub środka strony.
 */
export function alignRect(rect, where, height = rect.height) {
  const bottom = Math.max(height, rect.height);
  const target = {
    left: { x: 0 },
    center: { x: (100 - rect.width) / 2 },
    right: { x: 100 - rect.width },
    top: { y: 0 },
    middle: { y: (100 - bottom) / 2 },
    bottom: { y: 100 - bottom },
  }[where];

  return moveRect({ ...rect, ...target }, 0, 0, { height: bottom });
}

/**
 * Kolejność warstw. Zwraca nowe z_index: kolejne liczby od 1, więc liczba warstw nie rośnie przy każdym ruchu.
 */
export function reorder(items, id, action) {
  const order = [...items].sort((a, b) => a.z_index - b.z_index).map((item) => item.id);
  const from = order.indexOf(id);
  if (from < 0) return {};

  const to = { front: order.length - 1, back: 0, forward: from + 1, backward: from - 1 }[action];
  order.splice(from, 1);
  order.splice(clamp(to, 0, order.length), 0, id);

  return Object.fromEntries(order.map((key, index) => [key, index + 1]));
}

export function nextCopyId(items) {
  const numbers = items.map((item) => /^copy_(\d+)$/.exec(item.id)?.[1]).filter(Boolean).map(Number);
  return `copy_${Math.max(0, ...numbers) + 1}`;
}

export function createItem(page, rect, extra = {}) {
  return {
    id: rect.id,
    page_id: page,
    breakpoint: 'sheet',
    x: rect.x,
    y: rect.y,
    // Tekst dłuższy niż strona mierzy więcej niż arkusz; zapisana wysokość jest minimalna i musi mieścić się w zakresie.
    width: Math.min(rect.width, 100 - rect.x),
    height: Math.max(MIN_HEIGHT, Math.min(rect.height, 100 - rect.y)),
    z_index: rect.z_index ?? 1,
    style: { align: rect.align || 'left' },
    constraints: { locked: false, min_width: MIN_WIDTH, min_height: MIN_HEIGHT },
    ...extra,
  };
}

/*--- Historia: stany poprzednie i cofnięte; `present` nie jest jej częścią ---*/

export const emptyHistory = () => ({ past: [], future: [] });

export function pushHistory(history, label, before) {
  return { past: [...history.past, { label, value: before }].slice(-HISTORY_LIMIT), future: [] };
}

export function undoHistory(history, present) {
  const entry = history.past.at(-1);
  if (!entry) return null;
  return {
    history: { past: history.past.slice(0, -1), future: [{ label: entry.label, value: present }, ...history.future] },
    value: entry.value,
  };
}

export function redoHistory(history, present) {
  const entry = history.future[0];
  if (!entry) return null;
  return {
    history: { past: [...history.past, { label: entry.label, value: present }], future: history.future.slice(1) },
    value: entry.value,
  };
}
