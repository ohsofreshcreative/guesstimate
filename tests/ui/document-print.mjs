// Test wydruku: prawdziwe PDF-y publicznych adresów oferty (arkusz i telefon) oraz blokada edycji wersji zamrożonej.
// NIGDY na bazie innej niż kopia: cgi-prepend.php loguje administratora i łączy się z 127.0.0.1:33177.
//
// URLS: plik JSON [{ "name": "…", "url": "…" } lub { "name": "…", "offer": 5 }] z adresami stron do wydruku. Tokeny w adresach nie trafiają do wyników testu.
// PW_CORE=… PW_CHROME=… URLS=/tmp/urls.json LOCKED=4,1 SHOTS=/tmp node tests/ui/document-print.mjs

import { readFile } from 'node:fs/promises';
import { attach } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const SHOTS = process.env.SHOTS || '.'; const LOCKED = process.env.LOCKED;
const urls = JSON.parse(await readFile(process.env.URLS, 'utf8'));
const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROME });
const context = await browser.newContext();
await attach(context);
const problems = [];
let ok = 0, bad = 0;
const t = (name, cond, extra = '') => { if (cond) { ok += 1; console.log('OK  ', name); } else { bad += 1; console.log('FAIL', name, extra); } };

const inspect = (pdf) => {
  const text = Buffer.from(pdf).toString('latin1');
  const boxes = [...text.matchAll(/\/MediaBox\s*\[\s*0 0 ([\d.]+) ([\d.]+)\s*\]/g)].map((m) => [Number(m[1]), Number(m[2])]);
  return { pages: [...text.matchAll(/\/Type\s*\/Page\b(?!s)/g)].length, a4: boxes.length > 0 && boxes.every(([w, h]) => Math.abs(w - 841.9) < 2 && Math.abs(h - 595.3) < 2), size: pdf.length };
};

for (const { name, url, offer } of urls) {
  const page = await context.newPage();
  page.on('pageerror', (e) => problems.push(`${name}: ${e.message}`));
  await page.setViewportSize({ width: 1400, height: 900 });
  let target = url;
  if (offer) { // wersja robocza: adres podglądu tylko z linku w edytorze
    await page.goto(`http://guesstimate.local/app/oferty/${offer}/dokument`, { timeout: 60000 });
    target = await page.locator('a:has-text("Podgląd klienta")').getAttribute('href');
  }
  const response = await page.goto(target, { timeout: 60000, waitUntil: 'load' });
  t(`${name}: adres działa`, response.status() === 200, String(response.status()));
  await page.waitForFunction(() => document.querySelector('[data-doc-page][aria-label*=" z "]'), null, { timeout: 20000 });
  await page.evaluate(() => document.querySelectorAll('img[loading="lazy"]').forEach((img) => { img.loading = 'eager'; }));
  await page.waitForLoadState('networkidle').catch(() => {});
  const expected = await page.locator('[data-doc-page]').count();
  const desk = inspect(await page.pdf({ preferCSSPageSize: true, printBackground: true, path: `${SHOTS}/${name}-desktop.pdf` }));
  t(`${name}: arkusz, strona dokumentu = kartka`, desk.pages === expected, JSON.stringify([desk, expected]));
  t(`${name}: arkusz, A4 poziomo`, desk.a4, JSON.stringify(desk));

  await page.setViewportSize({ width: 390, height: 800 });
  await page.waitForTimeout(700);
  t(`${name}: telefon na ekranie bez arkusza`, !(await page.evaluate(() => document.documentElement.classList.contains('osf-sheet'))));
  const phone = inspect(await page.pdf({ preferCSSPageSize: true, printBackground: true, path: `${SHOTS}/${name}-phone.pdf` }));
  t(`${name}: telefon, wydruk jak z arkusza`, phone.pages === expected && phone.a4, JSON.stringify([phone, expected]));
  await page.waitForTimeout(500);
  t(`${name}: telefon, po wydruku wraca układ płynny`, !(await page.evaluate(() => document.documentElement.classList.contains('osf-sheet'))));

  // Symulacja mediów nie wywołuje beforeprint: pomiar na szerokości arkusza, jak po przejściu na strony A4.
  await page.setViewportSize({ width: 1400, height: 900 });
  await page.waitForTimeout(700);
  await page.emulateMedia({ media: 'print' });
  const print = await page.evaluate(() => {
    const nav = document.querySelector('[data-doc-viewer] > nav');
    const first = document.querySelector('[data-doc-page]').getBoundingClientRect();
    return { nav: nav ? getComputedStyle(nav).display : 'none', ratio: first.width / first.height, left: first.left };
  });
  t(`${name}: nawigacja niedrukowana, strona od krawędzi`, print.nav === 'none' && Math.abs(print.ratio - 297 / 210) < 0.02 && print.left < 1, JSON.stringify(print));
  await page.emulateMedia({ media: 'screen' });
  await page.close();
}

for (const id of (LOCKED || '').split(',').filter(Boolean)) {
  const page = await context.newPage();
  await page.setViewportSize({ width: 1600, height: 950 });
  const opened = await page.goto(`http://guesstimate.local/app/oferty/${id}/dokument`, { timeout: 60000 });
  if (opened.status() !== 200) { // stary model nie ma edytora dokumentu
    t(`zablokowana ${id}: bez edytora`, opened.status() === 409, String(opened.status()));
    await page.close();
    continue;
  }
  await page.waitForFunction(() => document.querySelector('[x-data]')?._x_dataStack?.[0]?.ready?.frame, null, { timeout: 40000 });
  await page.waitForTimeout(600);
  t(`zablokowana ${id}: edycja wyłączona`, (await page.evaluate(() => document.querySelector('[x-data]')._x_dataStack[0].editable)) === false);
  t(`zablokowana ${id}: migracja wyłączona`, await page.locator('[data-migration-start]').isDisabled());
  await page.evaluate(() => document.querySelector('[x-data]')._x_dataStack[0].startMigration());
  t(`zablokowana ${id}: bez podglądu migracji`, (await page.evaluate(() => document.querySelector('[x-data]')._x_dataStack[0].migration)) === null);
  const save = await page.evaluate(async () => {
    const d = document.querySelector('[x-data]')._x_dataStack[0];
    const r = await fetch(location.pathname, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-GST-Nonce': document.querySelector('meta[name="gst-nonce"]').content }, body: JSON.stringify({ content: d.state.content, document: { ...d.state.document, layout_mode: 'template' }, fit: {} }) });
    return r.status;
  });
  t(`zablokowana ${id}: serwer odrzuca zapis`, save >= 400, String(save));
  await page.close();
}

t('brak błędów JS', problems.length === 0, JSON.stringify(problems.slice(0, 3)));
console.log(`\nWYNIK ok=${ok} fail=${bad}`);
await browser.close();
process.exit(bad ? 1 : 0);
