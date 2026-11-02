// Demo obiegu: oferta → szablon → szkic → płatności → zatwierdzenie → pobranie. Zrzuty i pliki trafiają do OUT.
// NIGDY na bazie innej niż kopia: cgi-prepend.php łączy się z 127.0.0.1:33177. Wymaga tests/ui/demo-prepare.php.
// PW_CORE=… PW_CHROME=… OUT=/tmp/demo DEMO=id node tests/ui/demo.mjs

import { writeFile } from 'node:fs/promises';
import { attach } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const { OUT, DEMO } = process.env;
const BASE = 'http://guesstimate.local/app';
const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROME });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
await attach(context);
const page = await context.newPage();
const shot = async (name) => { await page.waitForTimeout(900); await page.screenshot({ path: `${OUT}/${name}.png` }); };
const open = (target) => page.goto(`${BASE}${target}`, { timeout: 60000 });

await open(`/umowy/${DEMO}`);
const offer = await page.locator('header a').nth(1).getAttribute('href');
await page.goto(offer, { timeout: 60000 });
await page.locator('#gst-contracts').scrollIntoViewIfNeeded();
await shot('01-oferta-z-umowami');
await open('/szablony-umow');
await shot('02-szablony');
await page.goto(`${offer}/umowy/nowa`, { timeout: 60000 });
await shot('03-nowa-umowa-z-oferty');
await open(`/umowy/${DEMO}`);
await shot('04-szkic-podglad');
await open(`/umowy/${DEMO}/platnosci`);
await shot('05-platnosci');
await open(`/umowy/${DEMO}`);
await page.getByRole('button', { name: 'Zatwierdź umowę' }).click();
await shot('06-potwierdzenie');
await Promise.all([page.waitForNavigation({ timeout: 60000 }), page.getByRole('dialog').getByRole('button', { name: 'Zatwierdź', exact: true }).click()]);
await shot('07-zatwierdzona');
await open('/umowy');
await shot('08-lista-umow');

for (const format of ['pdf', 'docx']) {
  const b64 = await page.evaluate(async (url) => {
    const bytes = new Uint8Array(await (await fetch(url)).arrayBuffer());
    let text = ''; bytes.forEach((b) => { text += String.fromCharCode(b); });
    return btoa(text);
  }, `${BASE}/umowy/${DEMO}/eksport/${format}`);
  await writeFile(`${OUT}/umowa-demo.${format}`, Buffer.from(b64, 'base64'));
}

await browser.close();
console.log('DEMO GOTOWE');
