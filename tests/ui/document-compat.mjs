// Test UI kompatybilności: przełącznik trybu układu, migracja jednej wersji roboczej, podgląd przed zapisem, walidacja zakresów.
// NIGDY na bazie innej niż kopia: cgi-prepend.php loguje administratora i łączy się z 127.0.0.1:33177.
//
// Wymaga tego samego co document-layout.mjs, na świeżej kopii (oferta bez układu pól).
// PW_CORE=… PW_CHROME=… OFFER=5 IMAGE=11 SHOTS=/tmp node tests/ui/document-compat.mjs

import { attach, stats } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const SHOTS = process.env.SHOTS || '.'; const OFFER = process.env.OFFER || 5; const IMAGE = Number(process.env.IMAGE || 11);
const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROME });
const context = await browser.newContext({ viewport: { width: 1600, height: 950 } });
await attach(context);
const page = await context.newPage();
const problems = [];
page.on('pageerror', (e) => problems.push(e.message));
page.on('console', (m) => { if (['error', 'warning'].includes(m.type()) && /Alpine|Error/.test(m.text())) problems.push(m.text()); });
let ok = 0, bad = 0;
const t = (name, cond, extra = '') => { if (cond) { ok += 1; console.log('OK  ', name); } else { bad += 1; console.log('FAIL', name, extra); } };

const open = async () => {
  await page.goto(`http://guesstimate.local/app/oferty/${OFFER}/dokument`, { timeout: 60000 });
  await page.waitForFunction(() => { const d = document.querySelector('[x-data]')._x_dataStack?.[0]; return d?.ready.frame && d?.ready.thumbs; }, null, { timeout: 40000 });
  await page.waitForTimeout(500);
};
const call = (expr) => page.evaluate((e) => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return new Function('d', `return d.${e}`)(d); }, expr);
const data = (expr) => page.evaluate((p) => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return JSON.parse(JSON.stringify(p.split('.').reduce((o, k) => o?.[k], d) ?? null)); }, expr);
const frame = () => page.frames().find((f) => f.url().includes('/ramka') && !f.url().includes('miniatury'));
const idle = async () => { await page.waitForTimeout(450); await page.waitForFunction(() => !document.querySelector('[x-data]')._x_dataStack[0].previewing, null, { timeout: 30000 }); await page.waitForTimeout(450); };
const go = async (key) => { await call(`select('${key}')`); await idle(); };
const PAGE = '[data-doc-page][data-current]:not([data-continued])';
const KEYS = ['cover', 'situation', 'solution', 'pricing', 'extras', 'references', 'next_steps', 'contact'];
const inPage = (fn, arg) => frame().locator(PAGE).evaluate(fn, arg);
const near = (a, b, d = 0.6) => Math.abs(a - b) <= d;

// Pozycje w % strony: nagłówek i pozostałe bloki. Nagłówek stanowi pole, treść płynie z szablonu.
const positions = () => inPage((el) => {
  const b = el.getBoundingClientRect();
  const rel = (n) => { const r = n.getBoundingClientRect(); return n && r.width ? [(r.left - b.left) / b.width * 100, (r.top - b.top) / b.height * 100, r.width / b.width * 100] : null; };
  const nodes = [...el.querySelectorAll('[data-edit="heading"], [data-edit="situation"], [data-edit="cover_heading"], [data-edit="cover_lead"], [data-flow-body] > *')];
  return nodes.map((n) => [n.dataset.edit || n.tagName, ...(rel(n) || [])]);
});
const allPositions = async () => { const out = {}; for (const key of KEYS) { await go(key); out[key] = await positions(); } return out; };
const compare = (name, a, b) => {
  for (const key of KEYS) {
    const same = a[key].length === b[key].length && a[key].every((row, i) => row.slice(1).every((v, j) => near(v, b[key][i][j + 1])));
    t(`${name}: ${key} bez przesunięcia`, same, JSON.stringify([a[key], b[key]]));
  }
};
const storedPages = () => page.evaluate(async (url) => (await (await fetch(url)).text()).match(/data-free/g)?.length ?? 0, `http://guesstimate.local/app/oferty/${OFFER}/dokument/ramka`);

await open();
await call(`state.content.situation = '<p>Klient potrzebuje nowej strony, która wyjaśni ofertę i pomoże zamykać sprzedaż.</p>'`);
await call(`state.content.blocks = [1, 2, 3].map((n) => ({ title: 'Blok ' + n, html: '<p>Opis bloku ' + n + '</p>' }))`);
await call(`state.document.extras = [{ name: 'Opieka', description: 'Aktualizacje', price: '500 zł' }]`);
await call(`state.document.next_steps = [{ title: 'Warsztat', note: '(1–3 dni)' }]`);
for (const key of ['situation', 'contact']) await call(`state.document.pages.${key}.image_id = ${IMAGE}`);
await idle();
await page.locator('button:has-text("Zapisz")').first().click();
await page.waitForFunction(() => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return !d.dirty && !d.saving; }, null, { timeout: 30000 });

// --- Nic nie dzieje się samo
t('bez akcji żadna strona nie ma układu', await storedPages() === 0);
const template = await allPositions();

// --- Podgląd przed zapisem
const savesBefore = stats.saves;
await page.locator('[data-migration-start]').click();
await idle();
const pending = await data('migration');
t('podgląd: wszystkie strony przeniesione', pending?.moved.length === 8 && pending.skipped.length === 0, JSON.stringify(pending));
t('podgląd: stan zmieniony, publikacja wstrzymana', (await data('dirty')) === true && (await data('canPublish')) === false);
t('podgląd: panel pokazany', await page.locator('[data-migration-preview]').isVisible());
compare('podgląd', template, await allPositions());
await page.waitForTimeout(4000);
t('podgląd: autozapis wstrzymany', stats.saves === savesBefore, String(stats.saves - savesBefore));
t('podgląd: na serwerze bez zmian', await storedPages() === 0);

// --- Anulowanie
await page.locator('[data-migration-cancel]').click();
await idle();
t('anulowanie: układ wraca', (await data('state.document.pages.pricing.fields')) === null && (await data('dirty')) === false && (await data('migration')) === null);
compare('anulowanie', template, await allPositions());
await page.waitForTimeout(3500);
t('anulowanie: bez zapisu', stats.saves === savesBefore);

// --- Strona z nadmiarem treści zostaje przy szablonie
const text = await data('state.content.situation');
await call(`state.content.situation = '<p>${'Bardzo długi tekst strony sytuacji, który nie zmieści się na arkuszu. '.repeat(90)}</p>'`);
await idle();
await go('situation');
await page.locator('[data-migration-start]').click();
await idle();
const crowded = await data('migration');
t('nadmiar: strona pominięta i nazwana', crowded?.skipped.includes('Sytuacja klienta') && (await data('state.document.pages.situation.fields')) === null, JSON.stringify(crowded));
t('nadmiar: komunikat widoczny', await page.locator('[data-migration-skipped]').isVisible());
await page.locator('[data-migration-cancel]').click();
await call(`state.content.situation = ${JSON.stringify(JSON.stringify(text))}.slice(1, -1)`);
await idle();
await page.waitForFunction(() => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return !d.dirty && !d.saving; }, null, { timeout: 15000 });

// --- Akceptacja
await page.locator('[data-migration-start]').click();
await idle();
await page.locator('[data-migration-apply]').click();
await page.waitForFunction(() => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return !d.dirty && !d.saving && !d.migration; }, null, { timeout: 30000 });
t('zapis: wszystkie strony na serwerze', await storedPages() === 8, String(await storedPages()));
await open();
compare('po odświeżeniu', template, await allPositions());
t('historia: migrację można cofnąć', (await data('undoLabel')) === 'Migracja do nowego układu', String(await data('undoLabel')));

// --- Przeciągnięcie nagłówka nie rusza treści pod nim
await go('pricing');
const bodyTop = () => inPage((el) => el.querySelector('[data-flow-body]').getBoundingClientRect().top);
const top0 = await bodyTop();
await call(`state.document.pages.pricing.fields.items[0].x = 30`);
await idle();
t('nagłówek przesunięty, treść stoi', near(await bodyTop(), top0, 1), JSON.stringify([top0, await bodyTop()]));

// --- Przełącznik trybu
await call(`state.document.pages.pricing.fields.items[0].x = ${template.pricing[0][1].toFixed(2)}`);
await page.locator('[data-layout-mode-old]').check();
await idle();
t('stary: pola bez pozycji', await frame().locator('[data-free]').count() === 0);
t('stary: panel pól ukryty', await page.locator('[data-layout-panel]').count() === 0);
await go('situation');
t('stary: przeciąganie wyłączone', await frame().evaluate(() => document.querySelector('[data-doc-page][data-current]').querySelectorAll('[data-field]').length) === 0);
compare('stary tryb', template, await allPositions());
await page.waitForFunction(() => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return !d.dirty && !d.saving; }, null, { timeout: 15000 });
await open();
t('stary: zapisany, pozycje zachowane', (await data('state.document.layout_mode')) === 'template' && (await data('state.document.pages.cover.fields'))?.items.length === 2);
t('stary: odświeżony dokument w szablonie', await frame().locator('[data-free]').count() === 0);
await page.locator('[data-layout-mode-new]').check();
await idle();
t('nowy: pozycje wracają', await frame().locator('[data-free]').count() === 8);
await page.waitForFunction(() => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return !d.dirty && !d.saving; }, null, { timeout: 15000 });

// --- Walidacja zakresów na serwerze (żądania z pominięciem edytora)
const post = async (change) => page.evaluate(async ([url, nonce, code]) => {
  const d = document.querySelector('[x-data]')._x_dataStack[0];
  const body = JSON.parse(JSON.stringify({ content: d.state.content, document: d.state.document, fit: {} }));
  new Function('b', code)(body);
  const r = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-GST-Nonce': nonce }, body: JSON.stringify(body) });
  return { status: r.status, errors: (await r.json().catch(() => ({}))).errors };
}, [await data('urls.save'), await page.evaluate(() => document.querySelector('meta[name="gst-nonce"]')?.content || ''), change]);
const bad1 = await post(`b.document.pages.cover.fields.items[0].x = 95`);
t('zakres: pole poza stroną odrzucone', bad1.status === 422 && !!bad1.errors?.['document.pages.cover.fields'], JSON.stringify(bad1));
const bad2 = await post(`b.document.pages.cover.polish = { heading_lift: 500 }`);
t('zakres: podniesienie odrzucone', bad2.status === 422 && !!bad2.errors?.['document.pages.cover.polish'], JSON.stringify(bad2));
const bad3 = await post(`b.document.layout_mode = 'cokolwiek'`);
t('zakres: nieznany tryb odrzucony', bad3.status === 422 && !!bad3.errors?.['document.layout_mode'], JSON.stringify(bad3));
await open();
const coverItems = (await data('state.document.pages.cover.fields')).items;
t('zakres: dane bez zmian', coverItems.every((item) => item.x < 90), JSON.stringify(coverItems));
t('brak błędów JS', problems.length === 0, JSON.stringify(problems.slice(0, 3)));
await page.screenshot({ path: `${SHOTS}/compat.png` });

console.log(`\nWYNIK ok=${ok} fail=${bad} saves=${stats.saves}`);
await browser.close();
process.exit(bad ? 1 : 0);
