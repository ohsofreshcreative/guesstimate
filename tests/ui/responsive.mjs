// Zrzuty desktop i telefon oraz kontrola przewijania w poziomie. Bez zmian w danych.
// NIGDY na bazie innej niż kopia: cgi-prepend.php łączy się z 127.0.0.1:33177. Tokeny publiczne nie trafiają do wyników.
//
// PW_CORE=… PW_CHROME=… SHOTS=/tmp URLS=/tmp/urls.json OFFER=5 CONTRACT=1 APPROVED=3 EMPLOYEE=2 node tests/ui/responsive.mjs

import { readFile } from 'node:fs/promises';
import { attach } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const { SHOTS = '.', URLS, OFFER, CONTRACT, APPROVED } = process.env;
const BASE = 'http://guesstimate.local/app';
const SIZES = { desktop: { width: 1440, height: 900 }, tablet: { width: 820, height: 1100 }, mobile: { width: 390, height: 844 } };
const PAGES = [
  ['pulpit', '/'], ['oferty', '/oferty'], ['oferta', `/oferty/${OFFER}`], ['biblioteka', '/biblioteka'], ['klienci', '/klienci'],
  ['umowy', '/umowy'], ['umowa-szkic', `/umowy/${CONTRACT}`], ['umowa-zatwierdzona', `/umowy/${APPROVED}`], ['platnosci', `/umowy/${CONTRACT}/platnosci`],
  ['szablony', '/szablony-umow'], ['ustawienia', '/ustawienia'], ['edytor-oferty', `/oferty/${OFFER}/dokument`],
];
const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROME });
const publicUrls = URLS ? JSON.parse(await readFile(URLS, 'utf8')).slice(0, 2).map((u, i) => [`oferta-publiczna-${i + 1}`, u.url]) : [];
let ok = 0; let bad = 0;
const problems = [];
const t = (name, cond, extra = '') => { if (cond) { ok += 1; console.log('OK  ', name); } else { bad += 1; console.log('FAIL', name, extra); } };

for (const [size, viewport] of Object.entries(SIZES)) {
  const context = await browser.newContext({ viewport, isMobile: size === 'mobile', hasTouch: size === 'mobile' });
  await attach(context);
  const page = await context.newPage();
  page.on('pageerror', (e) => problems.push(`${size}: ${e.message}`));

  for (const [name, target] of [...PAGES, ...publicUrls]) {
    const response = await page.goto(target.startsWith('http') ? target : `${BASE}${target}`, { timeout: 60000 });
    await page.waitForTimeout(700);
    const box = await page.evaluate(() => {
      const width = document.documentElement.clientWidth;
      const wide = [...document.querySelectorAll('body *')].filter((n) => {
        const r = n.getBoundingClientRect();
        return r.width > 0 && r.right > width + 1 && !n.closest('.overflow-x-auto, .overflow-auto, .sr-only, [hidden], iframe') && getComputedStyle(n).position !== 'fixed';
      });
      return { scroll: document.documentElement.scrollWidth, width, wide: wide.slice(0, 3).map((n) => `${n.tagName}.${String(n.className).slice(0, 40)}`) };
    });
    t(`${size} ${name}: status`, response.status() === 200, String(response.status()));
    t(`${size} ${name}: bez przewijania strony w poziomie`, box.scroll <= box.width + 1, JSON.stringify(box));
    if (name.startsWith('umowa-')) {
      const sheet = await page.locator('.ct-sheet').first().boundingBox();
      t(`${size} ${name}: arkusz widoczny`, sheet && sheet.height > 400, JSON.stringify(sheet));
      t(`${size} ${name}: lewa krawędź osiągalna`, sheet && sheet.x >= 0, JSON.stringify(sheet));
      if (size === 'desktop') t(`${size} ${name}: arkusz wyśrodkowany`, sheet && sheet.x > 100, JSON.stringify(sheet));
    }
    if (size !== 'tablet') await page.screenshot({ path: `${SHOTS}/r-${size}-${name}.png` });
  }

  await context.close();
}

t('brak błędów JS', problems.length === 0, problems.slice(0, 3).join(' | '));
await browser.close();
console.log(`WYNIK: ${ok} ok, ${bad} błędów`);
process.exit(bad ? 1 : 0);
