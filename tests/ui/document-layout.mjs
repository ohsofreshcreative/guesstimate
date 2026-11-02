// Test UI układu pól: prawdziwy edytor w headless Chromium, bez nasłuchującego serwera (bridge.mjs oddaje żądania do php-cgi).
// NIGDY na bazie innej niż kopia: cgi-prepend.php loguje administratora i łączy się z 127.0.0.1:33177.
//
// Wymaga: kopii bazy na porcie 33177 (procedura w STAGE-00.md), `php-cgi`, poza repo `npm i playwright-core`, świeżej oferty.
// PW_CORE=/ścieżka/node_modules/playwright-core/index.mjs PW_CHROME=/ścieżka/chrome-headless-shell OFFER=5 SHOTS=/tmp node tests/ui/document-layout.mjs
// Oferta nie może mieć układu pól; test ustawia jej treść „Sytuacja” i zapisuje zmiany w kopii bazy.

import { attach, stats } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const SHOTS = process.env.SHOTS || '.'; const OFFER = process.env.OFFER || 5;
const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROME });
const context = await browser.newContext({ viewport: { width: 1600, height: 950 } });
await attach(context);
const page = await context.newPage();
const problems = [];
page.on('pageerror', (e) => problems.push(e.message));
let ok = 0, bad = 0;
const t = (name, cond, extra = '') => { if (cond) { ok += 1; console.log('OK  ', name); } else { bad += 1; console.log('FAIL', name, extra); } };
const near = (a, b, d = 1.2) => Math.abs(a - b) <= d;
const open = async () => {
  await page.goto(`http://guesstimate.local/app/oferty/${OFFER}/dokument`, { timeout: 60000 });
  await page.waitForFunction(() => { const d = document.querySelector('[x-data]')._x_dataStack?.[0]; return d?.ready.frame && d?.ready.thumbs; }, null, { timeout: 40000 });
  await page.waitForTimeout(500);
};
const js = (fn, arg) => page.evaluate(fn, arg);
const data = (path) => js((p) => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return JSON.parse(JSON.stringify(p.split('.').reduce((o, k) => o?.[k], d) ?? null)); }, path);
const call = (expr) => js((e) => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return new Function('d', `return d.${e}`)(d); }, expr);
const frame = () => page.frames().find((f) => f.url().includes('/ramka') && !f.url().includes('miniatury'));
const idle = async () => { await page.waitForTimeout(450); await page.waitForFunction(() => !document.querySelector('[x-data]')._x_dataStack[0].previewing, null, { timeout: 30000 }); await page.waitForTimeout(120); };
const go = async (key) => { await call(`select('${key}')`); await page.waitForTimeout(250); };
const field = (id) => frame().locator(`[data-doc-page][data-current]:not([data-continued]) [data-edit="${id}"]`);
const box = (id) => field(id).boundingBox();
const pageBox = () => frame().locator('[data-doc-page][data-current]:not([data-continued])').boundingBox();
const items = async (key) => (await data(`state.document.pages.${key}.fields`))?.items || [];
const item = async (key, id) => (await items(key)).find((i) => i.id === id);
const drag = async (from, dx, dy, steps = 6) => { await page.mouse.move(from.x, from.y); await page.mouse.down(); await page.mouse.move(from.x + dx, from.y + dy, { steps }); await page.mouse.up(); await idle(); };
const center = (b) => ({ x: b.x + b.width / 2, y: b.y + b.height / 2 });
const handle = async (name) => { const b = await frame().locator(`.osf-selection [data-handle="${name}"]`).boundingBox(); return center(b); };

await open();
// --- Start: dokument bez układu
await go('situation');
await js(() => { const d = document.querySelector('[x-data]')._x_dataStack[0]; d.state.content.situation = '<p>' + 'Klient potrzebuje nowej strony, która wyjaśni ofertę i pomoże zamykać sprzedaż. '.repeat(9) + '</p>'; });
await idle();
t('start: strona bez układu', (await data('state.document.pages.situation.fields')) === null);
const headBefore = await box('heading'); const bodyBefore = await box('situation');
await page.mouse.click(center(headBefore).x, center(headBefore).y); await page.waitForTimeout(250);
t('kliknięcie zaznacza pole', (await data('field')) === 'heading' && await frame().locator('.osf-selection').count() === 1);
t('kliknięcie nie przesuwa i nie tworzy układu', (await data('state.document.pages.situation.fields')) === null && (await data('history.past')).length === 0);
t('fokus nie ucieka do panelu', (await js(() => document.activeElement.tagName)) !== 'TEXTAREA' && !(await js(() => document.activeElement.isContentEditable)));
await drag(center(headBefore), 2, 1, 2);
t('ruch poniżej progu nie jest przeciąganiem', (await data('state.document.pages.situation.fields')) === null);

// --- Przeciąganie
await drag(center(await box('heading')), 160, 90);
const headAfter = await box('heading'); const bodyAfter = await box('situation'); const sheet = await pageBox();
t('przeciąganie przesuwa pole', near(headAfter.y - headBefore.y, 90, sheet.height * 0.021), JSON.stringify([headBefore, headAfter]));
t('pozostałe pole zostaje w miejscu', near(bodyAfter.x, bodyBefore.x, 2) && near(bodyAfter.y, bodyBefore.y, 2) && near(bodyAfter.width, bodyBefore.width, 2), JSON.stringify([bodyBefore, bodyAfter]));
t('adopcja zapisuje wszystkie pola strony', (await items('situation')).map((i) => i.id).sort().join() === 'heading,situation');
await drag(await handle('e'), -sheet.width * 0.5, 0);
const narrow = await item('situation', 'heading');
t('zawężenie pola', near(narrow.width, 40.4, 1.3), JSON.stringify(narrow));
const nb = await box('heading');
await drag(center(nb), 160, -40);
const na = await box('heading');
t('ruch w obu osiach', near(na.x - nb.x, 160, sheet.width * 0.021) && near(na.y - nb.y, -40, sheet.height * 0.021), JSON.stringify([nb, na]));
const h1 = await item('situation', 'heading');
t('siatka: pozycja to wielokrotność 2%', Math.abs(h1.x % 2) < 0.01 && Math.abs(h1.y % 2) < 0.01, JSON.stringify(h1));
t('podgląd serwera zgodny z pozycją', await field('heading').getAttribute('data-field') === 'heading' && (await field('heading').getAttribute('style')).includes(`--field-x:${h1.x};`));
t('ramka zaznaczenia po odświeżeniu podglądu', await frame().locator('.osf-selection').count() === 1);

// --- Bez siatki
await page.keyboard.down('Alt'); await drag(center(await box('heading')), 13, 7); await page.keyboard.up('Alt');
const h2 = await item('situation', 'heading');
t('Alt wyłącza siatkę', Math.abs(h2.x % 2) > 0.01 || Math.abs(h2.y % 2) > 0.01, JSON.stringify(h2));

// --- Rozmiar
await call(`select('situation','situation',false)`); await page.waitForTimeout(200);
const b0 = await item('situation', 'situation');
await drag(await handle('e'), -120, 0);
const b1 = await item('situation', 'situation');
t('uchwyt E zmienia szerokość, x bez zmian', b1.width < b0.width && b1.x === b0.x, JSON.stringify([b0, b1]));
await drag(await handle('w'), 60, 0);
const b2 = await item('situation', 'situation');
t('uchwyt W przesuwa lewą krawędź', b2.x > b1.x && near(b2.x + b2.width, b1.x + b1.width, 0.1), JSON.stringify([b1, b2]));
await drag(await handle('se'), -30, 40);
const b3 = await item('situation', 'situation');
t('uchwyt narożny zmienia oba wymiary', b3.width < b2.width && b3.height !== b2.height, JSON.stringify([b2, b3]));

// --- Granice strony
await call(`select('situation','heading',false)`); await page.waitForTimeout(200);
await drag(center(await box('heading')), 3000, 3000);
let hb = await box('heading'); let ps = await pageBox();
t('pole nie wychodzi za prawy dolny róg', hb.x + hb.width <= ps.x + ps.width + 1 && hb.y + hb.height <= ps.y + ps.height + 1, JSON.stringify([hb, ps]));
await drag(center(await box('heading')), -3000, -3000);
hb = await box('heading');
t('pole nie wychodzi za lewy górny róg', hb.x >= ps.x - 1 && hb.y >= ps.y - 1);

// --- Zoom
for (const zoom of ['125', '75', 'fit']) {
  await page.selectOption('#f-zoom', zoom); await page.waitForTimeout(500);
  const before = await item('situation', 'heading'); const ps2 = await pageBox();
  await drag(center(await box('heading')), ps2.width * 0.2, ps2.height * 0.1);
  const after = await item('situation', 'heading');
  t(`zoom ${zoom}: ruch proporcjonalny do strony`, near(after.x - before.x, 20, 2.1) && near(after.y - before.y, 10, 2.1), JSON.stringify([before, after, ps2.width]));
  t(`zoom ${zoom}: pozycja zgodna z obrazem`, near(((await box('heading')).x - ps2.x) / ps2.width * 100, after.x, 0.3));
  t(`zoom ${zoom}: ramka zaznaczenia`, await frame().locator('.osf-selection').count() === 1);
  await call('undo()'); await idle();
}

// --- Cofanie
const past = (await data('history.past')).length; const hNow = await item('situation', 'heading');
await drag(center(await box('heading')), 100, 40);
await frame().locator('body').click({ position: { x: 5, y: 5 }, force: true }).catch(() => {});
await call(`select('situation','heading',false)`); await page.waitForTimeout(150);
await field('heading').evaluate(() => window.focus());
await page.keyboard.press('Control+z'); await idle();
t('Ctrl+Z cofa', JSON.stringify((await item('situation', 'heading')).x) === JSON.stringify(hNow.x), JSON.stringify(await data('history.past')));
await page.keyboard.press('Control+Shift+z'); await idle();
t('Ctrl+Shift+Z ponawia', (await item('situation', 'heading')).x !== hNow.x);
await page.click('[data-layout-undo]'); await idle();
t('przycisk Cofnij', (await item('situation', 'heading')).x === hNow.x && (await data('history.past')).length === past);
await page.click('[data-layout-redo]'); await idle(); await page.click('[data-layout-undo]'); await idle();
t('przycisk Ponów i ponowne cofnięcie', (await item('situation', 'heading')).x === hNow.x);

// --- Klawiatura
await field('heading').evaluate(() => window.focus());
await call(`select('situation','heading',false)`); await page.waitForTimeout(100);
const k0 = await item('situation', 'heading');
await page.keyboard.press('ArrowRight'); await page.keyboard.press('ArrowDown'); await page.waitForTimeout(120);
const k1 = await item('situation', 'heading');
t('strzałki przesuwają o krok siatki', k1.x === k0.x + 2 && k1.y === k0.y + 2, JSON.stringify([k0, k1]));
await page.keyboard.press('Shift+ArrowRight'); await page.waitForTimeout(120);
t('Shift+strzałka przesuwa o 5 kroków', (await item('situation', 'heading')).x === k1.x + 10);
await page.keyboard.press(']'); await page.waitForTimeout(120);
const z0 = await items('situation');
t('] podnosi warstwę', z0.find((i) => i.id === 'heading').z_index > z0.find((i) => i.id === 'situation').z_index, JSON.stringify(z0.map((i) => [i.id, i.z_index])));
await page.keyboard.press('['); await page.waitForTimeout(120);
const z1 = await items('situation');
t('[ obniża warstwę', z1.find((i) => i.id === 'heading').z_index < z1.find((i) => i.id === 'situation').z_index);
await page.click('[data-layer="front"]'); await idle();
const fz = await field('heading').evaluate((el) => getComputedStyle(el).zIndex);
t('warstwa widoczna w renderze', Number(fz) === 2, fz);

// --- Wyrównanie
await page.click('[aria-label="Wyrównanie do strony"] >> text=Środek'); await idle();
const c = await item('situation', 'heading');
t('wyrównanie do środka', near(c.x + c.width / 2, 50, 0.1));
await page.click('[aria-label="Wyrównanie do strony"] >> text=Dół'); await idle();
hb = await box('heading'); ps = await pageBox();
t('wyrównanie do dołu trzyma dolną krawędź na stronie', near(hb.y + hb.height, ps.y + ps.height, 2), JSON.stringify([hb, ps]));
await page.click('[data-text-align="right"]'); await idle();
t('wyrównanie tekstu', (await field('heading').evaluate((el) => getComputedStyle(el).textAlign)) === 'right');

// --- Blokada
await page.click('[data-layout-lock]'); await idle();
const l0 = await item('situation', 'heading');
t('blokada zapisana i widoczna', l0.constraints.locked === true && await field('heading').getAttribute('data-locked') !== null);
t('zablokowane pole bez uchwytów', await frame().locator('.osf-selection [data-handle]').count() === 0);
await drag(center(await box('heading')), -200, -200);
await page.keyboard.press('ArrowLeft'); await page.waitForTimeout(150);
t('zablokowane pole się nie rusza', (await item('situation', 'heading')).x === l0.x && (await item('situation', 'heading')).y === l0.y);
await page.click('[data-layout-lock]'); await idle();
t('odblokowanie', (await item('situation', 'heading')).constraints.locked === false);

// --- Duplikat
await field('heading').evaluate(() => window.focus());
await page.keyboard.press('Control+d'); await idle();
const copy = (await items('situation')).find((i) => i.id.startsWith('copy_'));
t('Ctrl+D tworzy kopię z tekstem', !!copy && copy.text.length > 0 && copy.style.role === 'heading', JSON.stringify(copy));
t('kopia zaznaczona i wyrenderowana', (await data('field')) === copy?.id && await field(copy.id).count() === 1);
await page.fill('[data-layout-panel] textarea', 'Nowy tekst kopii'); await idle();
t('edycja tekstu kopii', (await field(copy.id).innerText()) === 'Nowy tekst kopii');
const orig = await field('heading').innerText();
t('oryginał bez zmian', orig !== 'Nowy tekst kopii' && orig.length > 0);
await drag(center(await box(copy.id)), -300, -120);
t('kopię można przesuwać', (await item('situation', copy.id)).y !== copy.y);

// --- Inna strona i cofanie między stronami
await go('cover');
await call(`select('cover','cover_heading',false)`); await page.waitForTimeout(150);
const cv0 = await box('cover_heading');
await drag(center(cv0), 80, -60);
t('druga strona: przeciąganie', !!(await item('cover', 'cover_heading')) && (await items('cover')).length >= 1);
await go('pricing');
await call(`select('pricing','heading',false)`); await page.waitForTimeout(150);
await drag(center(await box('heading')), 40, 30);
t('trzecia strona: przeciąganie', !!(await item('pricing', 'heading')));
await call('undo()'); await idle(); await call('undo()'); await idle();
t('cofanie wraca przez strony', (await data('state.document.pages.cover.fields')) === null && (await data('state.document.pages.pricing.fields')) === null);
await call('redo()'); await idle(); await call('redo()'); await idle();
t('ponawianie wraca przez strony', !!(await item('cover', 'cover_heading')) && !!(await item('pricing', 'heading')));

// --- Autozapis i odświeżenie
await page.waitForFunction(() => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return !d.dirty && d.autosaved; }, null, { timeout: 20000 }).catch(async () => console.log('AUTOSAVE?', JSON.stringify({ dirty: await data('dirty'), autosaved: await data('autosaved'), err: await data('autosaveError'), error: await data('error'), errors: await data('errors'), saves: stats.saves, previewing: await data('previewing'), saving: await data('saving') })));
t('autozapis bez kliknięcia', stats.saves >= 1 && !(await data('dirty')));
t('status autozapisu', (await page.locator('[data-editor-status]').innerText()).includes('automatycznie'));
const savedCover = await item('cover', 'cover_heading'); const savedCopy = await item('situation', copy.id); const pastLen = (await data('history.past')).length;
await open();
await go('cover');
t('po odświeżeniu: układ zachowany', JSON.stringify(await item('cover', 'cover_heading')) === JSON.stringify(savedCover));
await go('situation');
t('po odświeżeniu: kopia zachowana', (await item('situation', copy.id))?.text === savedCopy.text && await field(copy.id).count() === 1);
t('po odświeżeniu: historia zachowana', (await data('history.past')).length === pastLen, String((await data('history.past')).length));
await call('undo()'); await idle();
t('po odświeżeniu: cofanie działa', (await data('state.document.pages.pricing.fields')) === null);
await call('redo()'); await idle();

// --- Tryb tekstu
await call(`select('situation','situation',false)`); await page.waitForTimeout(200);
const tb = await box('situation'); const s0 = await item('situation', 'situation');
await page.mouse.dblclick(tb.x + 30, tb.y + 12); await page.waitForTimeout(300);
t('dwuklik włącza tryb tekstu', await frame().locator('html[data-text-mode]').count() === 1);
await page.mouse.move(tb.x + 30, tb.y + 12); await page.mouse.down(); await page.mouse.move(tb.x + 160, tb.y + 14, { steps: 6 }); await page.mouse.up(); await page.waitForTimeout(250);
const sel = await frame().evaluate(() => document.getSelection().toString());
t('w trybie tekstu wskaźnik zaznacza tekst', sel.length > 0, JSON.stringify(sel));
t('zaznaczanie tekstu nie przesuwa pola', JSON.stringify(await item('situation', 'situation')) === JSON.stringify(s0));
await frame().locator('body').evaluate(() => window.focus());
await page.keyboard.press('Escape'); await page.waitForTimeout(150);
t('Esc kończy tryb tekstu', await frame().locator('html[data-text-mode]').count() === 0);

// --- Przepełnienie
await call(`select('situation','situation',false)`); await page.waitForTimeout(200);
await call(`changeField('Test', (i) => { i.width = 5; i.y = 60; })`); await idle(); await page.waitForTimeout(400);
t('przepełnienie wykryte', (await data('fieldOverflow')).includes('situation') || (await data('overflow')) === true, JSON.stringify(await data('report')));
t('publikacja zablokowana', (await data('canPublish')) === false);
const grown = await frame().evaluate(() => { const p = document.querySelector('[data-doc-page][data-page-key="situation"]'); document.documentElement.classList.add('osf-measuring'); const r = p.getBoundingClientRect(); return { h: r.height, w: r.width, f: Math.max(...Array.from(p.querySelectorAll('[data-field]'), (e) => e.getBoundingClientRect().bottom)) - r.top }; });
t('nic nie jest ucinane: strona rośnie', grown.h >= grown.f - 1, JSON.stringify(grown));
await page.screenshot({ path: `${SHOTS}/overflow.png` });
await call('undo()'); await idle(); await page.waitForTimeout(400);
t('cofnięcie usuwa przepełnienie', (await data('fieldOverflow')).length === 0 && (await data('overflow')) === false, JSON.stringify(await data('report')));
const ph = await frame().evaluate(() => { const p = document.querySelector('[data-doc-page][data-page-key="situation"]'); return [p.offsetHeight, p.clientWidth * 210 / 297]; });
t('strona wraca do A4', near(ph[0], ph[1], 2), JSON.stringify(ph));
await page.waitForFunction(() => !document.querySelector('[x-data]')._x_dataStack[0].dirty, null, { timeout: 30000 });
await page.screenshot({ path: `${SHOTS}/editor.png` });

// --- Oferta klienta: arkusz i telefon
const preview = await page.locator('a:has-text("Podgląd klienta")').getAttribute('href');
const client = await context.newPage();
client.on('pageerror', (e) => problems.push('client: ' + e.message));
await client.setViewportSize({ width: 1400, height: 900 });
await client.goto(preview, { timeout: 60000 }); await client.waitForTimeout(800);
const desk = await client.evaluate(() => { const p = document.querySelector('[data-page-key="situation"]'); const e = p.querySelector('[data-field="heading"]'); const r = e.getBoundingClientRect(), b = p.getBoundingClientRect(); return { pos: getComputedStyle(e).position, x: (r.left - b.left) / b.width * 100, stage: performance.getEntriesByType('resource').some((x) => x.name.includes('field-stage')), selection: document.querySelectorAll('.osf-selection').length }; });
const hs = await item('situation', 'heading');
t('klient (arkusz): pozycja jak w edytorze', desk.pos === 'absolute' && near(desk.x, hs.x, 0.3), JSON.stringify([desk, hs.x]));
t('klient: bez modułu edycji', desk.stage === false && desk.selection === 0);
await client.setViewportSize({ width: 390, height: 800 });
await client.reload(); await client.waitForTimeout(800);
const mob = await client.evaluate(() => { const p = document.querySelector('[data-page-key="situation"]'); return { pos: getComputedStyle(p.querySelector('[data-field="heading"]')).position, wide: document.documentElement.scrollWidth <= window.innerWidth + 1, copy: p.querySelector('[data-field-copy]')?.innerText, text: p.innerText.length }; });
t('klient (telefon): tekst płynie, bez poziomego paska', mob.pos === 'static' && mob.wide, JSON.stringify(mob));
t('klient (telefon): kopia widoczna', mob.copy === 'Nowy tekst kopii', JSON.stringify(mob));
await client.close();

// --- Reset
await call(`select('situation')`); await page.waitForTimeout(200);
await page.click('[data-layout-reset]'); await idle();
t('przywrócenie układu strony', (await data('state.document.pages.situation.fields')) === null && await frame().locator('[data-page-key="situation"][data-free]').count() === 0);
await call('undo()'); await idle();
t('przywrócenie można cofnąć', !!(await item('situation', 'heading')));

t('brak błędów JS', problems.length === 0, problems.join(' | '));
console.log(`\nWYNIK ok=${ok} fail=${bad} php=${stats.php} saves=${stats.saves}`);
await browser.close();
process.exit(bad ? 1 : 0);
