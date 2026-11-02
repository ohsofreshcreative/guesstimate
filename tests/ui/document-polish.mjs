// Test UI dopracowania stron (PagePolish): 8 stron w każdym dostępnym układzie oraz każda opcja, w edytorze i u klienta.
// NIGDY na bazie innej niż kopia: cgi-prepend.php loguje administratora i łączy się z 127.0.0.1:33177.
//
// Wymaga tego samego co document-layout.mjs oraz `wp eval-file tests/ui/polish-prepare.php` na kopii.
// PW_CORE=… PW_CHROME=… OFFER=5 IMAGE=11 SHOTS=/tmp node tests/ui/document-polish.mjs
// Test zapisuje zmiany w ofercie na kopii bazy.

import { attach } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const SHOTS = process.env.SHOTS || '.'; const OFFER = process.env.OFFER || 5; const IMAGE = Number(process.env.IMAGE || 11);
const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROME });
const context = await browser.newContext({ viewport: { width: 1600, height: 950 } });
await attach(context);
const page = await context.newPage();
const problems = [];
page.on('pageerror', (e) => problems.push(e.message));
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
const idle = async () => { await page.waitForTimeout(450); await page.waitForFunction(() => !document.querySelector('[x-data]')._x_dataStack[0].previewing, null, { timeout: 30000 }); await page.waitForTimeout(250); };
const go = async (key) => { await call(`select('${key}')`); await page.waitForTimeout(250); };
const PAGE = '[data-doc-page][data-current]:not([data-continued])';
const current = () => frame().locator(PAGE);
const inPage = (fn, arg) => current().evaluate(fn, arg);
const fits = () => inPage((el) => {
  const box = el.getBoundingClientRect();
  const wide = [...el.querySelectorAll('.osf-content *')].filter((n) => n.getBoundingClientRect().width > 0 && n.getBoundingClientRect().right > box.right + 1);
  return { h: Math.round(box.height), w: Math.round(box.width), wide: wide.length, overflow: el.hasAttribute('data-overflow'), a4: Math.abs(box.width * 210 / 297 - box.height) < 3 };
});

await open();

// --- Treść na wszystkich stronach
await call(`state.content.situation = '<p>Klient potrzebuje nowej strony, która wyjaśni ofertę i pomoże zamykać sprzedaż.</p>'`);
await call(`state.content.blocks = [1, 2, 3].map((n) => ({ title: 'Blok ' + n, html: '<p>Opis bloku ' + n + '</p>' }))`);
await call(`state.document.extras = [{ name: 'Opieka', description: 'Aktualizacje', price: '500 zł' }]`);
await call(`state.document.next_steps = [{ title: 'Warsztat', note: '(1–3 dni)' }, { title: 'Umowa', note: '' }]`);
await call(`state.document.reference_ids = [1, 2, 3]`);
for (const key of ['cover', 'situation', 'solution', 'pricing', 'extras', 'references', 'next_steps', 'contact']) await call(`state.document.pages.${key}.polish = null`); // ponowny przebieg na tej samej kopii
for (const key of ['cover', 'situation', 'solution', 'next_steps', 'contact']) await call(`state.document.pages.${key}.image_id = ${IMAGE}`);
await idle();

// --- Każda strona w każdym układzie
const sheets = [];
const seen = new Set();
const keys = ['cover', 'situation', 'solution', 'pricing', 'extras', 'references', 'next_steps', 'contact'];
for (const key of keys) {
  await go(key);
  const layouts = await data('page.layouts');
  for (const layout of layouts) {
    await call(`pageState.layout = '${layout.key}'`);
    await idle();
    seen.add(layout.key);
    const f = await fits();
    const media = await current().locator('.osf-media').count();
    const name = `${key}/${layout.key}`;
    t(`${name}: bez wyjścia poza stronę`, !f.wide && !f.overflow, JSON.stringify(f));
    t(`${name}: proporcje A4`, f.a4 || key === 'references', JSON.stringify(f));
    t(`${name}: zdjęcie zgodne z układem`, media === (layout.media ? 1 : 0), JSON.stringify({ media, l: layout.media }));
    sheets.push({ name, shot: await current().screenshot() });
  }
  await call(`pageState.layout = '${layouts[0].key}'`);
}
t('4 układy sprawdzone', ['text_image', 'image_text', 'full', 'tiles'].every((l) => seen.has(l)), JSON.stringify([...seen]));

// --- Okładka: podniesienie nagłówka
await go('cover'); await call(`pageState.layout = 'full'`); await idle();
const cover = () => inPage((el) => {
  const b = el.getBoundingClientRect();
  const rel = (n) => { const r = n.getBoundingClientRect(); return { top: (r.top - b.top) / b.height * 100, bottom: (r.bottom - b.top) / b.height * 100, left: (r.left - b.left) / b.width * 100 }; };
  return { heading: rel(el.querySelector('[data-edit="cover_heading"]')), logo: rel(el.querySelector('.osf-logo')), client: rel([...el.querySelectorAll('p')].find((p) => p.textContent.includes('oferta dla'))), h: b.height };
});
const c0 = await cover();
t('okładka: bez opcji bez znacznika', await current().locator('[data-lift]').count() === 0);
await call(`setPolish('heading_lift', 50)`); await idle();
const c50 = await cover();
t('okładka: 50 podnosi nagłówek', c50.heading.top < c0.heading.top - 3, JSON.stringify([c0.heading, c50.heading]));
t('okładka: lewa krawędź bez zmian', Math.abs(c50.heading.left - c0.heading.left) < 0.2, JSON.stringify([c0.heading, c50.heading]));
await call(`setPolish('heading_lift', 100)`); await idle();
const c100 = await cover();
t('okładka: 100 wyżej niż 50', c100.heading.top < c50.heading.top - 3, JSON.stringify([c50.heading, c100.heading]));
const lead = await inPage((el) => { const b = el.getBoundingClientRect(); return (el.querySelector('[data-edit="cover_lead"]').getBoundingClientRect().top - b.top) / b.height * 100; });
t('okładka: blok nie wchodzi na logo', lead >= c100.logo.bottom, JSON.stringify({ lead, logo: c100.logo }));
t('okładka: dół i wysokość strony bez zmian', Math.abs(c100.client.top - c0.client.top) < 0.2 && Math.abs(c100.h - c0.h) < 1, JSON.stringify([c0, c100]));
sheets.push({ name: 'cover lift=100', shot: await current().screenshot() });
await call(`pageState.layout = 'text_image'`); await idle();
const cm = await cover();
t('okładka ze zdjęciem: podniesienie działa', cm.heading.top < 35, JSON.stringify(cm.heading));
await call(`pageState.layout = 'full'`); await call(`setPolish('heading_lift', 0)`); await idle();
t('okładka: 0 wraca do szablonu', (await data('pageState.polish')) === null && Math.abs((await cover()).heading.top - c0.heading.top) < 0.2);

// --- Boczny napis
for (const key of ['pricing', 'references']) {
  await go(key); await idle();
  t(`${key}: napis domyślnie jest`, await current().locator('.osf-edge-label').count() === 1);
  const before = await inPage((el) => el.querySelector('.osf-pad').getBoundingClientRect().right - el.querySelector('[data-flow-title]').getBoundingClientRect().left);
  await call(`setPolish('hide_edge_label', true)`); await idle();
  t(`${key}: napis usunięty z dokumentu`, await current().locator('.osf-edge-label').count() === 0);
  t(`${key}: pozostała treść na miejscu`, Math.abs(before - await inPage((el) => el.querySelector('.osf-pad').getBoundingClientRect().right - el.querySelector('[data-flow-title]').getBoundingClientRect().left)) < 1);
}

// --- Realizacje: tytuły, proporcje, dopasowanie, kadr, kolejność
await go('references'); await idle();
const titles = () => inPage((el) => [...el.querySelectorAll('article h3')].map((h) => h.textContent.trim()));
const titleSize = () => inPage((el) => parseFloat(getComputedStyle(el.querySelector('article h3')).fontSize));
const s0 = await titleSize();
await call(`setPolish('title_size', 'large')`); await idle();
const s1 = await titleSize();
await call(`setPolish('title_size', 'xlarge')`); await idle();
const s2 = await titleSize();
t('tytuły: rosną stopniowo', s1 > s0 && s2 > s1, JSON.stringify([s0, s1, s2]));
const frameRatio = () => inPage((el) => { const n = el.querySelector('article img'); const r = (n.parentElement.classList.contains('overflow-hidden') ? n.parentElement : n).getBoundingClientRect(); return r.width / r.height; });
t('grafiki: domyślnie 16:9', Math.abs(await frameRatio() - 16 / 9) < 0.03);
for (const [ratio, value] of [['standard', 4 / 3], ['square', 1]]) {
  await call(`setPolish('image_ratio', '${ratio}')`); await idle();
  t(`grafiki: proporcje ${ratio}`, Math.abs(await frameRatio() - value) < 0.03, String(await frameRatio()));
  t(`grafiki: ${ratio} mieści się na stronie`, await frame().locator('[data-page-key="references"][data-continued]').count() === 0);
}
t('grafiki: brak grafiki zachowuje proporcje', await inPage((el) => { const r = el.querySelectorAll('article')[2].querySelector('div').getBoundingClientRect(); return Math.abs(r.width / r.height - 1) < 0.03; }));
await call(`setPolish('image_ratio', 'standard')`);
await call(`setPolish('image_fit', 'contain')`); await idle();
t('grafiki: pokaż całą', await inPage((el) => getComputedStyle(el.querySelector('article img')).objectFit) === 'contain');
await call(`setPolish('image_fit', '')`);
await call(`setReferenceCrop(1, 'zoom', 2)`); await call(`setReferenceCrop(1, 'x', 20)`); await idle();
const crop = await inPage((el) => {
  const n = el.querySelector('article img'); const w = n.parentElement; const a = w.getBoundingClientRect();
  return { pos: getComputedStyle(n).objectPosition, zoom: n.style.transform, clip: getComputedStyle(w).overflow, ratio: a.width / a.height, other: el.querySelectorAll('article')[1].querySelector('img').style.transform };
});
t('kadr: punkt skupienia i powiększenie', crop.pos.startsWith('20%') && crop.zoom === 'scale(2)' && crop.clip === 'hidden', JSON.stringify(crop));
t('kadr: ramka nie rośnie, inna realizacja bez zmian', Math.abs(crop.ratio - 4 / 3) < 0.03 && crop.other === '', JSON.stringify(crop));
const before = await titles();
await call(`moveReference(0, 1)`); await idle();
const after = await titles();
t('kolejność: zamiana grafik', before[0] === after[1] && before[1] === after[0], JSON.stringify([before, after]));
t('kolejność: kadr idzie za grafiką', await inPage((el) => el.querySelectorAll('article')[1].querySelector('img').style.transform === 'scale(2)' && el.querySelectorAll('article')[0].querySelector('img').style.transform === ''));
await call(`resetReferenceCrop(2)`);
sheets.push({ name: 'references polished', shot: await current().screenshot() });

// --- Zdjęcie strony
await go('situation'); await call(`setPolish('image_fit', 'contain')`); await idle();
await frame().waitForFunction((sel) => document.querySelector(`${sel} .osf-media[data-image-fit]`), PAGE, { timeout: 15000 }).catch(() => {});
t('zdjęcie strony: pokaż całe', await inPage((el) => getComputedStyle(el.querySelector('.osf-media img')).objectFit) === 'contain');
sheets.push({ name: 'situation contain', shot: await current().screenshot() });

// --- Trwałość i widok klienta
await call(`setPolish('heading_lift', 0)`);
await go('cover'); await call(`setPolish('heading_lift', 60)`); await idle();
await page.locator('button:has-text("Zapisz")').first().click();
await page.waitForFunction(() => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return !d.dirty && !d.saving; }, null, { timeout: 30000 });
await open();
t('po odświeżeniu: okładka', (await data('state.document.pages.cover.polish'))?.heading_lift === 60);
const refs = await data('state.document.pages.references.polish');
t('po odświeżeniu: realizacje', refs?.hide_edge_label === true && refs?.title_size === 'xlarge' && refs?.image_ratio === 'standard' && refs?.image_crops?.['1']?.zoom === 2, JSON.stringify(refs));
t('po odświeżeniu: kolejność', JSON.stringify(await data('state.document.reference_ids')) === '[2,1,3]');

const preview = await page.locator('a:has-text("Podgląd klienta")').getAttribute('href');
const client = await context.newPage();
client.on('pageerror', (e) => problems.push(e.message));
await client.setViewportSize({ width: 1400, height: 900 });
await client.goto(preview, { timeout: 60000 });
await client.waitForTimeout(800);
const view = () => client.evaluate(() => {
  const refs = document.querySelector('[data-page-key="references"]');
  return { lift: document.querySelectorAll('[data-lift]').length, edge: refs.querySelectorAll('.osf-edge-label').length, names: [...refs.querySelectorAll('article h3')].map((h) => h.textContent.trim()), zoom: refs.querySelectorAll('article img')[1]?.style.transform, wide: document.documentElement.scrollWidth > innerWidth };
});
const desk = await view();
t('klient (arkusz): opcje zastosowane', desk.lift === 1 && desk.edge === 0 && desk.zoom === 'scale(2)' && !desk.wide, JSON.stringify(desk));
await client.setViewportSize({ width: 390, height: 800 });
await client.waitForTimeout(800);
const mob = await view();
t('klient (telefon): bez poziomego paska', !mob.wide && mob.names[0] === desk.names[0], JSON.stringify(mob));
t('klient (telefon): podniesienie nie zmienia układu płynnego', await client.evaluate(() => getComputedStyle(document.querySelector('[data-lift]')).flexDirection) === 'row');
await client.close();
t('brak błędów JS', problems.length === 0, JSON.stringify(problems.slice(0, 3)));

// Arkusz kontrolny: wszystkie widoki na jednym obrazie.
const contact = sheets.map((s) => `<figure><img src="data:image/png;base64,${Buffer.from(s.shot).toString('base64')}"><figcaption>${s.name}</figcaption></figure>`).join('');
const sheet = await context.newPage();
await sheet.setViewportSize({ width: 1800, height: 1200 });
await sheet.setContent(`<style>body{margin:8px;display:grid;grid-template-columns:repeat(4,1fr);gap:8px;font:13px sans-serif}figure{margin:0}img{width:100%;display:block}</style>${contact}`);
await sheet.waitForTimeout(600);
await sheet.screenshot({ path: `${SHOTS}/polish-sheet.png`, fullPage: true });

console.log(`\nWYNIK ok=${ok} fail=${bad}`);
await browser.close();
process.exit(bad ? 1 : 0);
