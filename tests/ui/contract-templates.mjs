// Test UI edytora szablonów umów. Prawdziwa aplikacja w headless Chromium, bez nasłuchującego serwera.
// NIGDY na bazie innej niż kopia: cgi-prepend.php loguje użytkownika i łączy się z 127.0.0.1:33177.
//
// Wymaga tego samego co document-layout.mjs. Treści testowe są sztuczne, to nie jest wzór umowy.
// Tryb PRE=1: na kopii BEZ migracji umów sprawdza instrukcję zamiast błędu.
// Zwykły tryb: po migracji i przygotowaniu (użytkownicy EMPLOYEE, MANAGER; pliki DOCX i EVIL).
// PW_CORE=… PW_CHROME=… SHOTS=/tmp EMPLOYEE=2 MANAGER=3 DOCX=a.docx EVIL=b.docx node tests/ui/contract-templates.mjs

import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { attach } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const SHOTS = process.env.SHOTS || '.';
const BASE = 'http://guesstimate.local/app';
const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROME });
const context = await browser.newContext({ viewport: { width: 1500, height: 950 } });
await attach(context);
const page = await context.newPage();
const problems = [];
page.on('dialog', (d) => d.accept());
page.on('pageerror', (e) => problems.push(e.message));
page.on('console', (m) => { if (['error', 'warning'].includes(m.type()) && /Alpine|Error/.test(m.text())) problems.push(m.text()); });
let ok = 0; let bad = 0;
const t = (name, cond, extra = '') => { if (cond) { ok += 1; console.log('OK  ', name); } else { bad += 1; console.log('FAIL', name, extra); } };
const finish = async () => { t('brak błędów JS i Alpine', problems.length === 0, problems.slice(0, 3).join(' | ')); await browser.close(); console.log(`WYNIK: ${ok} ok, ${bad} błędów`); process.exit(bad ? 1 : 0); };

if (process.env.PRE) {
  const r = await page.goto(`${BASE}/szablony-umow`, { timeout: 60000 });
  t('moduł bez migracji: instrukcja (503), nie błąd bazy', r.status() === 503 && /wp acorn migrate|migrac/i.test(await page.content()), String(r.status()));
  const r2 = await page.goto(`${BASE}/oferty`, { timeout: 60000 });
  t('reszta aplikacji działa bez migracji', r2.status() === 200, String(r2.status()));
  await finish();
}

const editor = () => page.evaluate(() => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return { dirty: d.dirty, status: d.status, error: d.error, hash: d.hash, pageCount: d.pageCount, overflow: d.overflow, past: d.past.length, editable: d.editable }; });
const call = (expr) => page.evaluate((e) => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return new Function('d', `return (${e})`)(d); }, expr);
const html = (ref) => page.evaluate((r) => document.querySelector('[x-data]')._x_dataStack[0].$refs[r].innerHTML, ref);
const open = async (url) => { await page.goto(url, { timeout: 60000 }); await page.waitForFunction(() => document.querySelector('[x-data]')?._x_dataStack?.[0]?.past !== undefined); };
const body = page.locator('[aria-label="Treść dokumentu"]');
const save = async () => { await page.getByRole('button', { name: 'Zapisz', exact: true }).click(); await page.waitForFunction(() => { const d = document.querySelector('[x-data]')._x_dataStack[0]; return !d.busy && (d.status === 'Zapisano' || d.error); }); };
const pick = (label, value) => page.getByLabel(label).selectOption(value);
const caretEnd = () => page.evaluate(() => { const el = document.querySelector('[aria-label="Treść dokumentu"]'); el.focus(); const s = getSelection(); s.selectAllChildren(el.lastElementChild); s.collapseToEnd(); });
// Kończy się na stronie docelowej: po przekierowaniu to GET, po błędzie walidacji 422.
const submit = async (locator) => {
  const landed = page.waitForResponse((r) => r.request().isNavigationRequest() && (r.request().method() === 'GET' || r.status() === 422), { timeout: 60000 });
  await locator.click();
  await landed;
  await page.waitForLoadState('load');
};
// Playwright nie udostępnia pliku z wysyłki multipart, więc treść żądania składamy sami; PHP dostaje zwykłe $_FILES.
const upload = async (name, file) => page.evaluate(async ({ name: title, b64, filename }) => {
  const nonce = document.querySelector('form[action$="import"] input[type=hidden]');
  const bytes = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
  const boundary = '----gst8';
  const enc = new TextEncoder();
  const head = enc.encode(`--${boundary}\r\nContent-Disposition: form-data; name="${nonce.name}"\r\n\r\n${nonce.value}\r\n--${boundary}\r\nContent-Disposition: form-data; name="name"\r\n\r\n${title}\r\n--${boundary}\r\nContent-Disposition: form-data; name="docx"; filename="${filename}"\r\nContent-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document\r\n\r\n`);
  const tail = enc.encode(`\r\n--${boundary}--\r\n`);
  const data = new Uint8Array(head.length + bytes.length + tail.length);
  data.set(head); data.set(bytes, head.length); data.set(tail, head.length + bytes.length);
  const response = await fetch(document.querySelector('form[action$="import"]').action, { method: 'POST', headers: { 'Content-Type': `multipart/form-data; boundary=${boundary}` }, body: data, redirect: 'manual' });
  return { status: response.status, type: response.type, text: await response.text() };
}, { name, b64: (await readFile(file)).toString('base64'), filename: path.basename(file) });
const nl = async (text) => { await page.keyboard.press('Enter'); await page.keyboard.type(text); };

/*--- LISTA I TWORZENIE ---*/
await page.goto(`${BASE}/szablony-umow`, { timeout: 60000 });
t('moduł w menu', await page.getByRole('link', { name: 'Szablony umów' }).count() > 0);
t('pusta lista', await page.getByText('Nie ma jeszcze szablonów').count() === 1);
await page.getByRole('button', { name: 'Nowy szablon' }).first().click();
await page.evaluate(() => { document.querySelector('#template-name').required = false; });
await page.locator('#template-name').fill('   ');
await submit(page.locator('form[action$="szablony-umow"] button[type=submit]'));
t('walidacja nazwy z komunikatem', await page.getByText('Podaj nazwę szablonu.').count() === 1);
await page.locator('#template-name').fill('Testowy szablon "A"');
await submit(page.locator('form[action$="szablony-umow"] button[type=submit]'));
await page.waitForFunction(() => document.querySelector('[x-data]')?._x_dataStack?.[0]?.past !== undefined);
const editUrl = page.url();
t('komunikat po utworzeniu widoczny w edytorze', await page.getByText('Utworzono szablon.').count() === 1);
t('nowy szablon = szkic v1', (await page.textContent('header')).includes('v1 · Szkic'));

/*--- PISANIE I STYLE ---*/
await caretEnd();
await page.keyboard.type('Tytuł dokumentu');
await pick('Styl akapitu', 'h1');
await page.waitForTimeout(100);
await page.getByRole('button', { name: 'Środek' }).click();
t('nagłówek 1 z wyrównaniem', /<h1 class="ct-center">Tytuł dokumentu/.test(await html('body')), await html('body'));
await caretEnd(); await nl('Pierwszy przedmiot');
await pick('Styl akapitu', 'par');
await page.waitForTimeout(100);
t('paragraf § zamiast dziedziczenia nagłówka', /<p class="ct-par">Pierwszy przedmiot/.test(await html('body')), await html('body'));
await caretEnd(); await nl('Drugi przedmiot');
await caretEnd(); await nl('Tekst zwykły ');
await pick('Styl akapitu', 'p');
await page.waitForTimeout(50);
await caretEnd();
await page.keyboard.press('Control+b'); await page.keyboard.type('gruby'); await page.keyboard.press('Control+b');
await page.keyboard.press('Control+i'); await page.keyboard.type(' pochyły'); await page.keyboard.press('Control+i');
t('pogrubienie i kursywa', /<b>gruby<\/b>|<strong>gruby<\/strong>/.test(await html('body')) && /<(i|em)>(&nbsp;| )pochyły<\/(i|em)>/.test(await html('body')), await html('body'));
await caretEnd(); await nl('punkt listy');
await page.getByRole('button', { name: 'Lista', exact: true }).click();
t('lista punktowana', /<ul[^>]*>\s*<li>(<[a-z]+>)*punkt listy/.test(await html('body')), await html('body'));
await caretEnd(); await page.keyboard.press('Enter'); await page.keyboard.press('Enter');
await page.keyboard.type('numer');
await page.getByRole('button', { name: 'Numeracja' }).click();
t('lista numerowana', /<ol[^>]*>\s*<li>(<[a-z]+>)*numer/.test(await html('body')), await html('body'));
await caretEnd(); await page.keyboard.press('Enter'); await page.keyboard.press('Enter');

/*--- ZMIENNE ---*/
await page.keyboard.type('NIP: ');
await pick('Wstaw zmienną', 'client.nip');
t('zmienna wewnątrz akapitu', /<p>[^]*<span class="ct-var" data-var="client.nip" contenteditable="false">NIP<\/span>[^]*<\/p>$/.test(await html('body')), await html('body'));
await page.keyboard.type('po zmiennej');
t('pisanie za zmienną', /<\/span>(&nbsp;| )*po zmiennej/.test(await html('body')), await html('body'));

/*--- TABELA ---*/
await caretEnd(); await nl('');
await page.getByRole('button', { name: 'Tabela', exact: true }).click();
t('tabela 2×2', await page.locator('[aria-label="Treść dokumentu"] table td').count() === 4, await html('body'));
await page.locator('[aria-label="Treść dokumentu"] table td').first().click();
await page.keyboard.type('komórka');
await pick('Działanie na tabeli', 'row');
await page.locator('[aria-label="Treść dokumentu"] table td').first().click();
await pick('Działanie na tabeli', 'column');
t('dodane wiersz i kolumna', await page.locator('[aria-label="Treść dokumentu"] table tr').count() === 3 && await page.locator('[aria-label="Treść dokumentu"] table tr:first-child td').count() === 3, `${(await html('body')).slice(-300)} | ${(await editor()).error}`);
await page.locator('[aria-label="Treść dokumentu"] table td').nth(4).click();
await pick('Działanie na tabeli', 'deleteRow');
t('usunięty wiersz', await page.locator('[aria-label="Treść dokumentu"] table tr').count() === 2);

/*--- PODZIAŁ, NAGŁÓWEK, STOPKA ---*/
await caretEnd();
await page.getByRole('button', { name: 'Podział strony' }).click();
t('podział strony w treści', /<hr class="ct-break">/.test(await html('body')), await html('body'));
await caretEnd(); await page.keyboard.type('Po podziale');
await page.locator('[aria-label="Nagłówek dokumentu"]').click();
await page.keyboard.type('Nagłówek firmy');
await page.getByRole('button', { name: 'Podział strony' }).click();
t('podział strony odrzucony w nagłówku', (await editor()).error.includes('tylko w treści') && !(await html('header')).includes('ct-break'));
await page.locator('[aria-label="Stopka dokumentu"]').click();
await page.keyboard.type('Strona ');
await pick('Wstaw zmienną', 'page.number');
await page.keyboard.type('z ');
await pick('Wstaw zmienną', 'page.count');

/*--- COFANIE ---*/
await page.waitForTimeout(500);
await caretEnd(); await page.keyboard.type(' DOPISEK');
await page.waitForTimeout(500);
const before = await html('body');
await page.keyboard.press('Control+z');
t('Ctrl+Z cofa', !(await html('body')).includes('DOPISEK'), await html('body'));
await page.keyboard.press('Control+Shift+z');
t('Ctrl+Shift+Z ponawia', (await html('body')) === before);
await page.locator('[aria-label="Treść dokumentu"] table td').first().click();
await pick('Działanie na tabeli', 'deleteTable');
t('tabela usunięta', await page.locator('[aria-label="Treść dokumentu"] table').count() === 0);
await page.getByRole('button', { name: 'Cofnij' }).click();
t('cofanie obejmuje tabelę', await page.locator('[aria-label="Treść dokumentu"] table').count() === 1);

/*--- WKLEJANIE ---*/
await caretEnd();
await page.evaluate(() => {
  const dt = new DataTransfer();
  dt.setData('text/plain', 'Wklejone A\n\nWklejone <b>B</b>');
  dt.setData('text/html', '<p style="color:red" onclick="x()">Wklejone A</p><script>window.pwned=1<\/script>');
  document.querySelector('[aria-label="Treść dokumentu"]').dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
});
const pasted = await html('body');
t('wklejanie to czysty tekst', pasted.includes('Wklejone A') && pasted.includes('Wklejone &lt;b&gt;B&lt;/b&gt;') && !/color:red|onclick/.test(pasted), pasted);

/*--- ZAPIS I TRWAŁOŚĆ ---*/
const special = 'Zażółć „gęślą” jaźń 😀 C:\\sciezka \'a\' "b" 100% & 2 < 3  dwie';
await page.evaluate((text) => {
  const el = document.querySelector('[aria-label="Treść dokumentu"]');
  const p = document.createElement('p'); p.textContent = text; p.id = 'do-usuniecia'; el.append(p);
  el.insertAdjacentHTML('beforeend', '<p onclick="window.pwned=2" style="color:red">Atak</p><img src="x" onerror="window.pwned=3"><script>window.pwned=4<\/script>');
  el.dispatchEvent(new InputEvent('input', { bubbles: true }));
}, special);
await save();
const saved = await editor();
t('zapisano', saved.status === 'Zapisano' && !saved.dirty, saved.error);
await open(editUrl);
const stored = await html('body');
t('tekst specjalny po odświeżeniu', (await body.textContent()).includes(special), stored.slice(-300));
t('atak usunięty, tekst został', stored.includes('Atak') && !/onclick|onerror|<img|<script|id="do-usuniecia"/.test(stored), stored.slice(-300));
t('nic z ataku się nie wykonało', await page.evaluate(() => window.pwned === undefined));
t('układ zachowany', ['<h1 class="ct-center">', 'class="ct-par"', '<ul>', '<ol>', '<table>', 'class="ct-break"', 'data-var="client.nip"'].every((s) => stored.includes(s)), stored);
t('nagłówek i stopka zachowane', (await html('header')).includes('Nagłówek firmy') && (await html('footer')).includes('data-var="page.count"'), await html('footer'));
const hashA = (await editor()).hash;
await save();
t('zapis bez zmian nie zmienia bajtów', (await editor()).hash === hashA);

/*--- PODGLĄD A4 ---*/
await page.evaluate(() => {
  const el = document.querySelector('[aria-label="Treść dokumentu"]');
  for (let i = 0; i < 12; i += 1) el.insertAdjacentHTML('beforeend', `<p class="ct-par">Paragraf ${i}</p><p>${'Tekst próbny do wypełnienia strony. '.repeat(14)}</p>`);
  let rows = ''; for (let i = 0; i < 60; i += 1) rows += `<tr><td>Wiersz ${i}</td><td>dane</td></tr>`;
  el.insertAdjacentHTML('beforeend', `<table><tbody>${rows}</tbody></table><p>Koniec</p>`);
  el.dispatchEvent(new InputEvent('input', { bubbles: true }));
});
await page.getByRole('button', { name: 'Podgląd A4' }).click();
await page.waitForFunction(() => document.querySelectorAll('.ct-sheet').length > 0);
const preview = await page.evaluate(() => {
  const sheets = [...document.querySelectorAll('.ct-sheet')];
  const inside = sheets.every((s) => { const b = s.querySelector('.ct-sheet-body').getBoundingClientRect(); return [...s.querySelector('.ct-sheet-body').children].every((c) => c.getBoundingClientRect().bottom <= b.bottom + 1); });
  const rows = sheets.flatMap((s) => [...s.querySelectorAll('tr td:first-child')].map((c) => c.textContent)).filter((x) => /^Wiersz/.test(x));
  const first = sheets[0].getBoundingClientRect();
  const numbers = sheets.map((s) => s.querySelector('[data-var="page.number"]')?.textContent);
  const pars = sheets.map((s) => { const el = s.querySelector('.ct-sheet-body'); return { reset: el.style.counterReset, count: el.querySelectorAll('.ct-par').length }; });
  return { pages: sheets.length, w: first.width, h: first.height, inside, rows, numbers, total: sheets[0].querySelector('[data-var="page.count"]')?.textContent, headers: sheets.every((s) => s.querySelector('.ct-sheet-header').textContent.includes('Nagłówek firmy')), pars, hr: document.querySelectorAll('.ct-sheet hr').length };
});
t('wiele stron', preview.pages >= 4, String(preview.pages));
t('A4 pionowo', Math.abs(preview.h / preview.w - 297 / 210) < 0.01, `${preview.w}×${preview.h}`);
t('nic nie wychodzi poza stronę', preview.inside && (await editor()).overflow === 0);
t('tabela podzielona bez gubienia wierszy', preview.rows.length === 60 && preview.rows.every((r, i) => r === `Wiersz ${i}`), String(preview.rows.length));
t('nagłówek na każdej stronie', preview.headers);
t('numery stron', preview.numbers.every((n, i) => n === String(i + 1)) && preview.total === String(preview.pages), preview.numbers.join(','));
let running = 0;
t('numeracja § przez strony', preview.pars.every((p) => { const good = p.reset === `ct-par ${running}`; running += p.count; return good; }) && running === 14, JSON.stringify(preview.pars));
t('podział strony nie jest widoczny w podglądzie', preview.hr === 0);
await page.screenshot({ path: `${SHOTS}/contract-preview.png` });
await page.getByRole('button', { name: 'Wróć do edycji' }).click();
await page.waitForTimeout(200);
await page.screenshot({ path: `${SHOTS}/contract-editor.png` });
await open(editUrl);

/*--- KONFLIKT ---*/
const other = await context.newPage();
await other.goto(editUrl, { timeout: 60000 });
await other.waitForFunction(() => document.querySelector('[x-data]')?._x_dataStack?.[0]?.past !== undefined);
await other.evaluate(() => { const d = document.querySelector('[x-data]')._x_dataStack[0]; d.$refs.body.insertAdjacentHTML('beforeend', '<p>Zmiana z drugiej karty</p>'); return d.save(); });
await caretEnd(); await page.keyboard.type(' moja zmiana');
await save();
t('konflikt wykryty, nic nie nadpisano', (await editor()).error.includes('Ktoś inny'), (await editor()).error);
await other.close();
await open(editUrl);
t('zmiana z drugiej karty została', (await html('body')).includes('Zmiana z drugiej karty') && !(await html('body')).includes('moja zmiana'));

/*--- ZATWIERDZANIE ---*/
await page.evaluate(() => { document.querySelector('[x-data]')._x_dataStack[0].$refs.body.insertAdjacentHTML('beforeend', '<p><span class="ct-var" data-var="offer.nieznana">x</span></p>'); });
await page.getByRole('button', { name: 'Zatwierdź wersję' }).click();
await page.waitForFunction(() => document.querySelector('[x-data]')._x_dataStack[0].error);
t('nieznana zmienna blokuje zatwierdzenie', (await editor()).error.includes('offer.nieznana'), (await editor()).error);
await page.evaluate(() => { document.querySelector('[x-data]')._x_dataStack[0].$refs.body.querySelector('[data-var="offer.nieznana"]').closest('p').remove(); });
await page.getByRole('button', { name: 'Zatwierdź wersję' }).click();
await page.waitForFunction(() => /v1 · Zatwierdzona/.test(document.querySelector('header')?.textContent || ''));
t('zatwierdzona', true);
t('tylko do odczytu', await page.locator('[contenteditable="true"]').count() === 0 && await page.getByRole('toolbar').count() === 0 && await page.getByRole('button', { name: 'Zapisz', exact: true }).count() === 0);
const approvedHtml = await html('body');
const blocked = await page.evaluate(async () => { const n = document.querySelector('meta[name="gst-nonce"]').content; const r = await fetch(location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-GST-Nonce': n, Accept: 'application/json' }, body: JSON.stringify({ name: 'x', body: '<p>podmiana</p>' }) }); return { status: r.status, data: await r.json() }; });
t('serwer odrzuca zapis zatwierdzonej', blocked.status === 422 && /Zatwierdzonej/.test(blocked.data.error), JSON.stringify(blocked));

/*--- NOWA WERSJA I HISTORIA ---*/
await page.getByRole('button', { name: 'Utwórz nową wersję' }).click();
await page.waitForFunction(() => /v2 · Szkic/.test(document.querySelector('header')?.textContent || ''));
await page.waitForFunction(() => document.querySelector('[x-data]')?._x_dataStack?.[0]?.past !== undefined);
t('v2 = kopia v1', (await html('body')) === approvedHtml);
await caretEnd(); await page.keyboard.type(' tylko w v2');
await save();
await open(`${editUrl}?wersja=1`);
t('v1 bez zmian i tylko do odczytu', (await html('body')) === approvedHtml && await page.locator('[contenteditable="true"]').count() === 0);
t('oznaczenie starszej wersji', await page.getByText('To starsza wersja').count() === 1);

/*--- KOPIA ---*/
await page.getByRole('button', { name: 'Kopiuj jako nowy szablon' }).click();
await page.waitForFunction(() => /Kopia: Testowy szablon/.test(document.querySelector('header')?.textContent || ''));
await page.waitForFunction(() => document.querySelector('[x-data]')?._x_dataStack?.[0]?.past !== undefined);
const copyUrl = page.url();
t('kopia to nowy szablon, szkic v1, z wybranej wersji', copyUrl !== editUrl && (await page.textContent('header')).includes('v1 · Szkic') && (await html('body')) === approvedHtml);
await caretEnd(); await page.keyboard.type(' w kopii');
await save();
await open(`${editUrl}?wersja=1`);
t('oryginał niezależny od kopii', (await html('body')) === approvedHtml);

/*--- ARCHIWUM ---*/
await page.goto(`${BASE}/szablony-umow`, { timeout: 60000 });
await submit(page.locator('tr', { hasText: 'Kopia: Testowy' }).getByRole('button', { name: 'Archiwizuj' }));
t('archiwum na liście', (await page.locator('tr', { hasText: 'Kopia: Testowy' }).textContent()).includes('Archiwum'));
await page.goto(copyUrl, { timeout: 60000 });
t('zarchiwizowany tylko do odczytu', await page.locator('[contenteditable="true"]').count() === 0 && await page.getByText('Szablon jest w archiwum').count() === 1);

/*--- IMPORT DOCX ---*/
await page.goto(`${BASE}/szablony-umow`, { timeout: 60000 });
await page.getByRole('button', { name: 'Importuj DOCX' }).click();
t('import nie obiecuje wiernej kopii', await page.getByText('a nie wierna kopia').count() === 1);
const refused = await upload('Zły plik', process.env.EVIL);
t('zły plik odrzucony z komunikatem', refused.status === 422 && refused.text.includes('niedozwolone deklaracje'), String(refused.status));
t('zły plik nie tworzy szablonu', await page.evaluate(() => !document.body.innerText.includes('Zły plik')));
const accepted = await upload('Import testowy', process.env.DOCX);
t('plik przyjęty, przekierowanie', accepted.type === 'opaqueredirect', accepted.type);
await page.goto(`${BASE}/szablony-umow`, { timeout: 60000 });
t('komunikat po imporcie widoczny', await page.getByText('Porównaj go z oryginałem').count() === 1);
await submit(page.getByRole('link', { name: 'Import testowy' }));
await page.waitForFunction(() => document.querySelector('[x-data]')?._x_dataStack?.[0]?.past !== undefined);
const imported = await html('body');
t('treść z pliku w szkicu', /<h1[^>]*>UMOWA TESTOWA/.test(imported) && imported.includes('<table>') && imported.includes('Punkt pierwszy'), imported);
t('ostrzeżenia z importu widoczne', await page.getByText('Czego import nie przeniósł').count() === 1 && await page.getByText('numeracją automatyczną').count() === 1);
const approveBtn = page.getByRole('button', { name: 'Zatwierdź wersję' });
t('zatwierdzenie zablokowane do potwierdzenia', await approveBtn.isDisabled());
const direct = await page.evaluate(async () => { const d = document.querySelector('[x-data]')._x_dataStack[0]; const n = document.querySelector('meta[name="gst-nonce"]').content; const r = await fetch(d.urls.approve, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-GST-Nonce': n, Accept: 'application/json' }, body: JSON.stringify({ hash: d.hash }) }); return r.status; });
t('serwer też wymaga potwierdzenia', direct === 422, String(direct));
await page.getByLabel('Szablon porównany z oryginałem').check();
t('po potwierdzeniu można zatwierdzić', await approveBtn.isEnabled());
await approveBtn.click();
await page.waitForFunction(() => /v1 · Zatwierdzona/.test(document.querySelector('header')?.textContent || ''));
t('import zatwierdzony', true);

/*--- ROLE ---*/
const roleContext = await browser.newContext({ extraHTTPHeaders: { 'x-test-user': process.env.EMPLOYEE } });
await attach(roleContext);
const employee = await roleContext.newPage();
const denied = await employee.goto(editUrl, { timeout: 60000 });
t('pracownik: 403 na edytorze', denied.status() === 403, String(denied.status()));
t('pracownik: 403 na liście', (await employee.goto(`${BASE}/szablony-umow`, { timeout: 60000 })).status() === 403);
const home = await employee.goto(`${BASE}/`, { timeout: 60000 });
t('pracownik: brak w menu', home.status() === 200 && await employee.getByRole('link', { name: 'Szablony umów' }).count() === 0);
await roleContext.close();
const managerContext = await browser.newContext({ extraHTTPHeaders: { 'x-test-user': process.env.MANAGER } });
await attach(managerContext);
const manager = await managerContext.newPage();
t('manager: ma dostęp', (await manager.goto(editUrl, { timeout: 60000 })).status() === 200);
await managerContext.close();

await finish();
