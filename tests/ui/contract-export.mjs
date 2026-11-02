// Test UI eksportu umowy: pobiera PDF i DOCX z prawdziwej aplikacji, porównuje tekst i renderuje strony.
// NIGDY na bazie innej niż kopia: cgi-prepend.php loguje użytkownika i łączy się z 127.0.0.1:33177.
//
// Wymaga migracji i tests/ui/contract-export-prepare.php na kopii. PDF czyta pdf.js, a DOCX pakiet docx-preview:
// oba poza projektem (`npm i pdfjs-dist docx-preview jszip` w katalogu TOOLS). To nie zastępuje otwarcia pliku w Wordzie.
// PW_CORE=… PW_CHROME=… SHOTS=/tmp TOOLS=/ścieżka READY=1 DRAFT=2 BROKEN=3 EMPLOYEE=2 node tests/ui/contract-export.mjs

import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { attach } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const SHOTS = process.env.SHOTS || '.';
const TOOLS = process.env.TOOLS;
const BASE = 'http://guesstimate.local/app';
const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROME });
const context = await browser.newContext({ viewport: { width: 1500, height: 950 } });
await attach(context);
const page = await context.newPage();
const problems = [];
page.on('pageerror', (e) => problems.push(e.message));
let ok = 0; let bad = 0;
const t = (name, cond, extra = '') => { if (cond) { ok += 1; console.log('OK  ', name); } else { bad += 1; console.log('FAIL', name, extra); } };
const open = (url) => page.goto(url, { timeout: 60000 });
const { READY, DRAFT, BROKEN } = process.env;
const url = (id, format) => `${BASE}/umowy/${id}/eksport/${format}`;
const download = (href) => page.evaluate(async (target) => {
  const r = await fetch(target, { redirect: 'manual' });
  const headers = Object.fromEntries(r.headers.entries());
  const buffer = r.type === 'opaqueredirect' ? new ArrayBuffer(0) : await r.arrayBuffer();
  let binary = ''; new Uint8Array(buffer).forEach((b) => { binary += String.fromCharCode(b); });
  return { status: r.status, type: r.type, headers, b64: btoa(binary) };
}, href);

/*--- ZASADY POBRANIA ---*/
await open(`${BASE}/umowy/${DRAFT}`);
t('szkic bez przycisków pobrania', await page.getByRole('link', { name: 'Pobierz PDF' }).count() === 0);
t('szkic: serwer odmawia', (await download(url(DRAFT, 'pdf'))).type === 'opaqueredirect');
await open(url(DRAFT, 'docx'));
t('szkic: powód widoczny', await page.getByText('tylko dla zatwierdzonej wersji').count() >= 1);
await open(`${BASE}/umowy/${BROKEN}`);
t('podmieniona treść: alarm i brak pobrania', await page.getByRole('alert').filter({ hasText: 'nie zgadza się ze skrótem' }).count() === 1 && await page.getByRole('link', { name: 'Pobierz PDF' }).count() === 0);
t('podmieniona treść: serwer odmawia', (await download(url(BROKEN, 'docx'))).type === 'opaqueredirect');
t('nieznany format', (await download(`${BASE}/umowy/${READY}/eksport/exe`)).status === 404);

/*--- POBRANIE ---*/
await open(`${BASE}/umowy/${READY}`);
t('zatwierdzona ma oba przyciski', await page.getByRole('link', { name: 'Pobierz PDF' }).count() === 1 && await page.getByRole('link', { name: 'Pobierz DOCX' }).count() === 1);
const files = {};

for (const [format, type] of [['pdf', 'application/pdf'], ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']]) {
  const r = await download(url(READY, format));
  files[format] = Buffer.from(r.b64, 'base64');
  t(`${format}: nagłówki`, r.status === 200 && r.headers['content-type'] === type && r.headers['content-disposition'] === `attachment; filename="umowa-${READY}-v1.${format}"` && r.headers['x-content-type-options'] === 'nosniff' && /no-store/.test(r.headers['cache-control'] || ''), JSON.stringify(r.headers));
  await writeFile(`${SHOTS}/contract.${format}`, files[format]);
}

t('pdf: sygnatura', files.pdf.subarray(0, 5).toString() === '%PDF-');
t('docx: sygnatura zip', files.docx.subarray(0, 2).toString() === 'PK');

const other = await browser.newContext({ extraHTTPHeaders: { 'x-test-user': process.env.EMPLOYEE } });
await attach(other);
const employee = await other.newPage();
t('pracownik nie pobierze cudzej umowy', (await employee.goto(url(READY, 'pdf'), { timeout: 60000 })).status() === 403);
await other.close();

/*--- TEKST I STRONY ---*/
const viewer = await browser.newPage({ viewport: { width: 1400, height: 900 } });
viewer.on('pageerror', (e) => problems.push(`viewer: ${e.message}`));
await viewer.route('http://v.local/**', async (route) => {
  const u = new URL(route.request().url());
  if (u.pathname === '/') return route.fulfill({ contentType: 'text/html', body: '<!doctype html><meta charset=utf-8><body style="margin:0;background:#ddd"><div id=o style="display:flex;flex-wrap:wrap;gap:8px"></div>' });
  return route.fulfill({ contentType: u.pathname.endsWith('js') ? 'text/javascript' : 'application/octet-stream', body: await readFile(path.join(TOOLS, u.pathname)) });
});
await viewer.goto('http://v.local/');
const pdf = await viewer.evaluate(async (data) => {
  const lib = await import('/node_modules/pdfjs-dist/build/pdf.min.mjs');
  lib.GlobalWorkerOptions.workerSrc = '/node_modules/pdfjs-dist/build/pdf.worker.min.mjs';
  const doc = await lib.getDocument({ data: Uint8Array.from(atob(data), (c) => c.charCodeAt(0)) }).promise;
  const pages = [];
  for (let i = 1; i <= doc.numPages; i += 1) {
    const p = await doc.getPage(i); const vp = p.getViewport({ scale: 0.62 });
    const c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
    document.getElementById('o').append(c);
    await p.render({ canvasContext: c.getContext('2d'), viewport: vp, canvas: c }).promise;
    const items = (await p.getTextContent()).items.filter((it) => it.str.trim() !== '');
    pages.push({ text: items.map((it) => it.str).join(' '), lowest: Math.min(...items.map((it) => it.transform[5])), right: Math.max(...items.map((it) => it.transform[4] + it.width)) });
  }
  return { pages, size: (await doc.getPage(1)).view.slice(2) };
}, files.pdf.toString('base64'));
await viewer.screenshot({ path: `${SHOTS}/contract-export-pdf.png`, fullPage: true });

await viewer.goto('http://v.local/');
await viewer.addScriptTag({ url: '/node_modules/jszip/dist/jszip.min.js' });
await viewer.addScriptTag({ url: '/node_modules/docx-preview/dist/docx-preview.min.js' });
await viewer.evaluate(async (data) => {
  const host = document.getElementById('o');
  await window.docx.renderAsync(new Blob([Uint8Array.from(atob(data), (c) => c.charCodeAt(0))]), host, null, { inWrapper: false });
  host.style.zoom = 0.5;
}, files.docx.toString('base64'));
await viewer.screenshot({ path: `${SHOTS}/contract-export-docx.png`, fullPage: true });

const count = pdf.pages.length;
const flat = pdf.pages.map((p) => p.text).join(' ');
t('pdf: format A4', Math.round(pdf.size[0]) === 595 && Math.round(pdf.size[1]) === 842, String(pdf.size));
t('pdf: wiele stron', count >= 3, String(count));
t('pdf: numeracja na każdej stronie', pdf.pages.every((p, i) => p.text.includes(`Strona ${i + 1} z ${count}`)), pdf.pages.map((p) => p.text.slice(-20)).join(' | '));
t('pdf: nagłówek na każdej stronie', pdf.pages.every((p) => p.text.includes('Firma: Wykonawca Testowy')));
t('pdf: polskie znaki', flat.includes('Zażółć gęślą jaźń ĄĆĘŁŃÓŚŹŻ') && flat.includes('ul. Żółwia 1, 00-001 Łódź'));
t('pdf: paragrafy policzone', flat.includes('§ 1. Przedmiot umowy') && flat.includes('§ 2. Wynagrodzenie') && flat.includes('§ 3. Zakres'));
t('pdf: wszystkie wiersze tabeli', Array.from({ length: 60 }, (_, i) => flat.includes(`Pozycja ${i + 1} `)).every(Boolean));
t('pdf: nagłówek tabeli powtórzony', (flat.match(/Lp\. Opis Wartość/g) || []).length >= 2);
t('pdf: harmonogram', flat.includes('Do zapłaty') && flat.includes('Makiety') && flat.includes('Zadatek'));
t('pdf: treść w marginesach strony', pdf.pages.every((p) => p.lowest > 30 && p.right < 595 - 30), JSON.stringify(pdf.pages.map((p) => [p.lowest, p.right])));
t('pdf: brak pustych tokenów', !/undefined|\bnull\b|NaN|\[brak|Nazwa firmy|Numer strony/.test(flat));

const extract = path.join(SHOTS, 'contract-docx-text.txt');
execFileSync('php', ['-r', `$z = new ZipArchive(); $z->open($argv[1]); $o = []; foreach (['word/document.xml', 'word/header1.xml', 'word/footer1.xml'] as $n) { $x = $z->getFromName($n); $x = preg_replace('~<w:tab/>|</w:p>|<w:br[^>]*>~', ' ', $x); $o[] = html_entity_decode(strip_tags($x), ENT_QUOTES | ENT_XML1, 'UTF-8'); } file_put_contents($argv[2], implode("\\f", $o));`, `${SHOTS}/contract.docx`, extract]);
const [docxBody, docxHeader] = (await readFile(extract, 'utf8')).split('\f');
const squeeze = (text) => text.replace(/\s+/g, '');
const clean = pdf.pages.map((p) => p.text.replace(`Strona ${pdf.pages.indexOf(p) + 1} z ${count}`, '').replace('Firma: Wykonawca Testowy', '')).join(' ');
const firstHead = clean.indexOf('Lp. Opis Wartość') + 1;
const pdfText = squeeze(clean.slice(0, firstHead) + clean.slice(firstHead).replace(/Lp\. Opis Wartość/g, ''));
// Znaczniki list są w obu formatach numeracją układu, a nie tekstem; pdf.js zwraca tylko numery z listy numerowanej.
const withoutMarkers = pdfText.replace(/(?<=punktdrugi)1\.numerjeden2\.numerdwa/, 'numerjedennumerdwa');
t('docx: nagłówek', docxHeader.includes('Firma: Wykonawca Testowy'));
t('pdf i docx: ten sam tekst', withoutMarkers === squeeze(docxBody), diff(withoutMarkers, squeeze(docxBody)));
t('docx: wszystkie wiersze', Array.from({ length: 60 }, (_, i) => docxBody.includes(`Pozycja ${i + 1} `)).every(Boolean));

function diff(a, b) {
  let i = 0; while (i < a.length && a[i] === b[i]) i += 1;
  return `od ${i}: [${a.slice(i - 30, i + 60)}] vs [${b.slice(i - 30, i + 60)}]`;
}

t('brak błędów JS', problems.length === 0, problems.slice(0, 3).join(' | '));
await browser.close();
console.log(`WYNIK: ${ok} ok, ${bad} błędów`);
process.exit(bad ? 1 : 0);
