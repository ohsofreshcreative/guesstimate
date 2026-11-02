import test from 'node:test';
import assert from 'node:assert/strict';
import { paginate, parOffsets } from '../../resources/js/guesstimate/contract-pages.js';

const b = (height, extra = {}) => ({ height, ...extra });

test('bloki mieszczące się zostają na jednej stronie', () => {
  assert.deepEqual(paginate([b(40), b(40)], 100).pages, [[{ index: 0 }, { index: 1 }]]);
});

test('blok, który się nie mieści, przechodzi w całości na następną stronę', () => {
  assert.deepEqual(paginate([b(60), b(60)], 100).pages, [[{ index: 0 }], [{ index: 1 }]]);
});

test('podział strony wymusza nową stronę, także gdy miejsce zostało', () => {
  const { pages } = paginate([b(10), b(0, { pageBreak: true }), b(10)], 100);
  assert.deepEqual(pages, [[{ index: 0 }], [{ index: 2 }]]);
});

test('podział strony na początku nie tworzy pustej strony', () => {
  assert.deepEqual(paginate([b(0, { pageBreak: true }), b(10)], 100).pages, [[{ index: 1 }]]);
});

test('nagłówek nie zostaje sam na końcu strony', () => {
  assert.deepEqual(paginate([b(80), b(15, { keepNext: true }), b(30)], 100).pages, [[{ index: 0 }], [{ index: 1 }, { index: 2 }]]);
});

test('tabela dzieli się po wierszach i powtarza nagłówek', () => {
  const { pages, overflow } = paginate([b(30), b(140, { parts: [40, 40, 40], head: 20 })], 100);
  assert.deepEqual(pages, [[{ index: 0 }, { index: 1, from: 0, to: 1 }], [{ index: 1, from: 1, to: 3 }]]);
  assert.deepEqual(overflow, []);
});

test('tabela, której pierwszy wiersz się nie mieści, przechodzi w całości', () => {
  const { pages } = paginate([b(90), b(80, { parts: [40, 40] })], 100);
  assert.deepEqual(pages, [[{ index: 0 }], [{ index: 1, from: 0, to: 2 }]]);
});

test('tabela od góry strony nie zostawia pustej strony przed sobą', () => {
  const { pages } = paginate([b(150, { parts: [50, 50, 50] })], 100);
  assert.deepEqual(pages, [[{ index: 0, from: 0, to: 2 }], [{ index: 0, from: 2, to: 3 }]]);
});

test('blok wyższy od strony jest zgłaszany, a nie ucinany', () => {
  const { pages, overflow } = paginate([b(150)], 100);
  assert.equal(pages.length, 1);
  assert.deepEqual(overflow, [0]);
});

test('numeracja § ciągnie się przez strony', () => {
  assert.deepEqual(parOffsets([[1, 2], [3], [4]], (n) => n), [0, 3, 6]);
});
