// Test UI biblioteki: prawdziwe przesyłanie plików, duplikaty, role, ponowne użycie w ofertach, archiwizacja.
// NIGDY na bazie innej niż kopia: cgi-prepend.php łączy się z 127.0.0.1:33177. Pliki trafiają do GST_TEST_UPLOADS.
//
// PW_CORE=… PW_CHROME=… SHOTS=/tmp GST_TEST_UPLOADS=/tmp/up OFFER=5 OFFER2=6 EMPLOYEE=2 MANAGER=3 node tests/ui/media-library.mjs

import { readdirSync, existsSync } from 'node:fs';
import path from 'node:path';
import { attach } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const { SHOTS = '.', GST_TEST_UPLOADS: ROOT, OFFER, OFFER2, EMPLOYEE, MANAGER } = process.env;
const BASE = 'http://guesstimate.local/app';
if (!ROOT) throw new Error('Wymagane GST_TEST_UPLOADS');
const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROME });
const problems = [];
const session = async (user) => {
  const context = await browser.newContext({ viewport: { width: 1500, height: 950 }, ...(user ? { extraHTTPHeaders: { 'x-test-user': user } } : {}) });
  await attach(context);
  const page = await context.newPage();
  page.on('pageerror', (e) => problems.push(e.message));
  return page;
};
let ok = 0; let bad = 0;
const t = (name, cond, extra = '') => { if (cond) { ok += 1; console.log('OK  ', name); } else { bad += 1; console.log('FAIL', name, extra); } };
const files = () => (existsSync(ROOT) ? readdirSync(ROOT, { recursive: true, withFileTypes: true }).filter((f) => f.isFile()).map((f) => path.join(f.parentPath, f.name)) : []);
const open = (page, target) => page.goto(`${BASE}${target}`, { timeout: 60000 });
const token = (page) => page.locator('meta[name="gst-nonce"]').getAttribute('content');
const api = async (page, target, method = 'GET', data = null) => {
  const nonce = await token(page);
  return page.evaluate(async ([url, verb, fields, key]) => {
    const r = await fetch(url, { method: verb, headers: { 'X-GST-Nonce': key, Accept: 'application/json' }, body: fields ? new URLSearchParams(fields) : undefined });
    return { status: r.status, body: await r.text() };
  }, [`${BASE}${target}`, method, data, nonce]);
};
const upload = async (page, name, mimeType, buffer) => {
  const [response] = await Promise.all([
    page.waitForResponse((r) => r.url().endsWith('/biblioteka/pliki') && r.request().method() === 'POST', { timeout: 60000 }),
    page.locator('input[type=file]').first().setInputFiles({ name, mimeType, buffer }),
  ]);
  return { status: response.status(), body: await response.json().catch(() => ({})) };
};
const png = (page, color) => page.evaluate((fill) => {
  const c = document.createElement('canvas'); c.width = 900; c.height = 600;
  const x = c.getContext('2d'); x.fillStyle = fill; x.fillRect(0, 0, 900, 600);
  return c.toDataURL('image/png').split(',')[1];
}, color).then((b) => Buffer.from(b, 'base64'));

/*--- PRZESYŁANIE ---*/
const admin = await session();
await open(admin, '/biblioteka');
const before = files().length;
const first = await png(admin, '#1a7a6a');
const created = await upload(admin, 'test-zażółć.png', 'image/png', first);
t('przesłanie PNG', created.status === 200 && created.body.id > 0 && created.body.duplicate === false, JSON.stringify(created));
const image = created.body.id;
t('plik i miniatury są w katalogu testowym', files().length > before);
await open(admin, '/biblioteka');
await admin.locator(`[data-media="${image}"]`).waitFor({ timeout: 30000 });
t('grafika widoczna w bibliotece', await admin.locator(`[data-media="${image}"] img`).evaluate((img) => img.complete && img.naturalWidth > 0));
await admin.locator(`[data-media="${image}"]`).click();
await admin.locator('[data-media-usage]').waitFor();
t('nowa grafika nie jest używana', (await admin.locator('[data-media-usage]').innerText()).includes('nie'));
await admin.screenshot({ path: `${SHOTS}/media-detail.png` });
await open(admin, '/biblioteka');
const count = files().length;
const again = await upload(admin, 'inna-nazwa.png', 'image/png', first);
t('ten sam plik wskazuje istniejący załącznik', again.status === 200 && again.body.id === image && again.body.duplicate === true, JSON.stringify(again));
t('duplikat nie tworzy plików', files().length === count);
t('tekst jako .png odrzucony', (await upload(admin, 'udawany.png', 'image/png', Buffer.from('to nie jest obraz'))).status === 422);
// Przeglądarka odrzuca je sama, więc serwer jest sprawdzany bezpośrednio.
const direct = async (name, type, text) => {
  const nonce = await token(admin);
  return admin.evaluate(async ([url, file, mime, content, key]) => {
    const form = new FormData(); form.append('file', new File([content], file, { type: mime }));
    return (await fetch(url, { method: 'POST', headers: { 'X-GST-Nonce': key }, body: form })).status;
  }, [`${BASE}/biblioteka/pliki`, name, type, text, nonce]);
};
await admin.locator('input[type=file]').first().setInputFiles({ name: 'kod.php', mimeType: 'application/x-php', buffer: Buffer.from('<?php echo 1;') });
t('przeglądarka odrzuca PHP z komunikatem', await admin.getByText('Obsługiwane formaty').count() >= 1);
t('serwer odrzuca PHP', await direct('kod.php', 'application/x-php', '<?php echo 1;') === 422);
t('serwer odrzuca PHP udający PNG', await direct('kod.png', 'image/png', '<?php echo 1;') === 422);
t('serwer odrzuca SVG', await direct('x.svg', 'image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg"/>') === 422);
t('odrzucone pliki nie zostały na dysku', files().length === count);

/*--- ROLE ---*/
const employee = await session(EMPLOYEE);
await open(employee, '/biblioteka');
const own = await upload(employee, 'pracownik.png', 'image/png', await png(employee, '#c0492f'));
t('pracownik przesyła grafikę', own.status === 200 && own.body.id > 0);
t('pracownik nie zmieni cudzej grafiki', (await api(employee, `/biblioteka/pliki/${image}`, 'POST', { title: 'x' })).status === 403);
t('pracownik nie usunie cudzej grafiki', (await api(employee, `/biblioteka/pliki/${image}/usun`, 'POST', {})).status === 403);
t('pracownik zmienia własną', (await api(employee, `/biblioteka/pliki/${own.body.id}`, 'POST', { title: 'Moja' })).status === 200);
const manager = await session(MANAGER);
await open(manager, '/biblioteka');
t('manager zmienia cudzą', (await api(manager, `/biblioteka/pliki/${own.body.id}`, 'POST', { title: 'Managera' })).status === 200);

/*--- PONOWNE UŻYCIE ---*/
const attachImage = async (offer) => {
  await open(admin, `/oferty/${offer}/dokument`);
  await admin.waitForFunction(() => { const d = document.querySelector('[x-data]')._x_dataStack?.[0]; return d?.ready.frame; }, null, { timeout: 60000 });
  await admin.waitForTimeout(500);
  await admin.evaluate((id) => { document.querySelector('[x-data]')._x_dataStack[0].state.document.pages.cover.image_id = id; }, image);
  await admin.waitForTimeout(600);
  const [response] = await Promise.all([
    admin.waitForResponse((r) => /\/dokument$/.test(r.url()) && r.request().method() === 'POST', { timeout: 60000 }),
    admin.evaluate(() => document.querySelector('[x-data]')._x_dataStack[0].save()),
  ]);
  return response.status();
};
const usedBefore = files().length;
t('grafika w pierwszej ofercie', await attachImage(OFFER) === 200);
t('ta sama grafika w drugiej ofercie', await attachImage(OFFER2) === 200);
t('ponowne użycie nie kopiuje plików', files().length === usedBefore);
await open(admin, '/biblioteka');
const detail = await api(admin, `/biblioteka/pliki/${image}`);
const usage = JSON.parse(detail.body);
t('biblioteka pokazuje dwa użycia', usage.used === true && usage.usage.versions.length === 2, JSON.stringify(usage.usage));

/*--- USUWANIE ---*/
const main = files().find((f) => /test-za.*\.png$/.test(f) && !/-\d+x\d+\./.test(f));
const removed = await api(admin, `/biblioteka/pliki/${image}/usun`, 'POST', {});
t('używana grafika jest archiwizowana', removed.status === 200 && /archiv|Zarchiw/i.test(removed.body), removed.body.slice(0, 200));
t('plik zostaje po archiwizacji', main && existsSync(main));
t('przywrócenie z archiwum', (await api(admin, `/biblioteka/pliki/${image}/przywroc`, 'POST', {})).status === 200);
const size = files().length;
t('nieużywana grafika jest usuwana', (await api(manager, `/biblioteka/pliki/${own.body.id}/usun`, 'POST', {})).status === 200 && files().length < size);

t('brak błędów JS', problems.length === 0, problems.slice(0, 3).join(' | '));
await browser.close();
console.log(`WYNIK: ${ok} ok, ${bad} błędów`);
process.exit(bad ? 1 : 0);
