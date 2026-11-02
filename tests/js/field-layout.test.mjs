// Uruchomienie: node --test tests/js
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  alignRect, createItem, emptyHistory, moveRect, nextCopyId, pushHistory, redoHistory, reorder, resizeRect, undoHistory,
} from '../../resources/js/guesstimate/field-layout.js';

const rect = { x: 10, y: 20, width: 40, height: 10 };

test('przesunięcie zostaje na stronie', () => {
  assert.deepEqual(moveRect(rect, -50, -50), { ...rect, x: 0, y: 0 });
  assert.deepEqual(moveRect(rect, 500, 500), { ...rect, x: 60, y: 90 });
});

test('przesunięcie liczy rzeczywistą wysokość pola, nie tylko zapisaną', () => {
  assert.equal(moveRect(rect, 0, 500, { height: 30 }).y, 70);
});

test('siatka przyciąga pozycję', () => {
  const moved = moveRect(rect, 1.3, 0.4, { step: 2 });
  assert.deepEqual([moved.x, moved.y], [12, 20]);
});

test('uchwyty trzymają przeciwną krawędź', () => {
  const east = resizeRect(rect, 'e', 10, 0);
  assert.deepEqual([east.x, east.width], [10, 50]);
  const west = resizeRect(rect, 'w', 5, 0);
  assert.deepEqual([west.x, west.width], [15, 35]);
  const north = resizeRect(rect, 'n', 0, -5);
  assert.deepEqual([north.y, north.height], [15, 15]);
  const corner = resizeRect(rect, 'se', 5, 5);
  assert.deepEqual([corner.width, corner.height], [45, 15]);
});

test('rozmiar nie schodzi poniżej minimum i nie wychodzi poza stronę', () => {
  const tiny = resizeRect(rect, 'e', -100, 0);
  assert.equal(tiny.width, 5);
  const wide = resizeRect(rect, 'e', 500, 0);
  assert.equal(wide.x + wide.width, 100);
  const up = resizeRect(rect, 'nw', -500, -500);
  assert.deepEqual([up.x, up.y, up.width, up.height], [0, 0, 50, 30]);
  const bottom = resizeRect(rect, 's', 0, 500);
  assert.equal(bottom.y + bottom.height, 100);
});

test('wyrównanie do strony', () => {
  assert.equal(alignRect(rect, 'left').x, 0);
  assert.equal(alignRect(rect, 'center').x, 30);
  assert.equal(alignRect(rect, 'right').x, 60);
  assert.equal(alignRect(rect, 'top').y, 0);
  assert.equal(alignRect(rect, 'middle').y, 45);
  assert.equal(alignRect(rect, 'bottom').y, 90);
  assert.equal(alignRect(rect, 'bottom', 30).y, 70);
});

test('warstwy dają kolejne numery', () => {
  const items = [{ id: 'a', z_index: 1 }, { id: 'b', z_index: 5 }, { id: 'c', z_index: 9 }];
  assert.deepEqual(reorder(items, 'a', 'front'), { b: 1, c: 2, a: 3 });
  assert.deepEqual(reorder(items, 'c', 'back'), { c: 1, a: 2, b: 3 });
  assert.deepEqual(reorder(items, 'a', 'forward'), { b: 1, a: 2, c: 3 });
  assert.deepEqual(reorder(items, 'c', 'forward'), { a: 1, b: 2, c: 3 });
  assert.deepEqual(reorder(items, 'a', 'backward'), { a: 1, b: 2, c: 3 });
  assert.deepEqual(reorder(items, 'x', 'front'), {});
});

test('kolejny identyfikator kopii', () => {
  assert.equal(nextCopyId([{ id: 'heading' }]), 'copy_1');
  assert.equal(nextCopyId([{ id: 'copy_1' }, { id: 'copy_7' }]), 'copy_8');
});

test('nowe pole ma pełny schemat', () => {
  const item = createItem('cover', { id: 'heading', x: 1, y: 2, width: 3, height: 4, align: 'right' });
  assert.equal(item.page_id, 'cover');
  assert.equal(item.breakpoint, 'sheet');
  assert.deepEqual(item.style, { align: 'right' });
  assert.equal(item.constraints.locked, false);
});

test('cofanie i ponawianie', () => {
  let history = pushHistory(emptyHistory(), 'A', 1);
  history = pushHistory(history, 'B', 2);
  const undone = undoHistory(history, 3);
  assert.equal(undone.value, 2);
  const again = undoHistory(undone.history, 2);
  assert.equal(again.value, 1);
  const redone = redoHistory(again.history, 1);
  assert.equal(redone.value, 2);
  assert.equal(redoHistory(redone.history, 2).value, 3);
  assert.equal(undoHistory(emptyHistory(), 1), null);
  assert.equal(redoHistory(emptyHistory(), 1), null);
});

test('nowa zmiana kasuje ponawianie', () => {
  const undone = undoHistory(pushHistory(emptyHistory(), 'A', 1), 2);
  assert.deepEqual(pushHistory(undone.history, 'B', 1).future, []);
});

test('historia ma limit', () => {
  let history = emptyHistory();
  for (let i = 0; i < 70; i += 1) history = pushHistory(history, 'x', i);
  assert.equal(history.past.length, 50);
  assert.equal(history.past[0].value, 20);
});

test('nowa pozycja mieści się w stronie także przy tekście dłuższym niż arkusz', () => {
  const item = createItem('situation', { id: 'situation', x: 80, y: 30, width: 40, height: 120 });
  assert.equal(item.width, 20);
  assert.equal(item.height, 70);
});
