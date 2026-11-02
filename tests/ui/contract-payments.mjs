// Test UI harmonogramu płatności. Prawdziwa aplikacja w headless Chromium, bez nasłuchującego serwera.
// NIGDY na bazie innej niż kopia: cgi-prepend.php loguje użytkownika i łączy się z 127.0.0.1:33177.
//
// Wymaga migracji i tests/ui/contract-prepare.php na kopii oraz tego samego co document-layout.mjs.
// PW_CORE=… PW_CHROME=… SHOTS=/tmp OFFER=id EMPLOYEE=2 node tests/ui/contract-payments.mjs

import { attach } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const SHOTS = process.env.SHOTS || '.';
const BASE = 'http://guesstimate.local/app';
const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROME });
const context = await browser.newContext({ viewport: { width: 1500, height: 1100 } });
await attach(context);
const page = await context.newPage();
const problems = [];
page.on('pageerror', (e) => problems.push(e.message));
page.on('console', (m) => { if (['error', 'warning'].includes(m.type()) && /Alpine|Error/.test(m.text())) problems.push(m.text()); });
let ok = 0; let bad = 0;
const t = (name, cond, extra = '') => { if (cond) { ok += 1; console.log('OK  ', name); } else { bad += 1; console.log('FAIL', name, extra); } };
const open = (url) => page.goto(url, { timeout: 60000 });
const submit = async (locator) => { await Promise.all([page.waitForNavigation({ timeout: 60000 }), locator.click()]); };
const save = () => submit(page.getByRole('button', { name: 'Zapisz harmonogram' }));
const nbsp = (text) => text.replace(/\u00A0/g, ' ');
const blockers = async () => nbsp((await page.locator('[data-payment-blockers]').allInnerTexts()).join(' '));
const warnings = async () => nbsp((await page.locator('[data-payment-warnings]').allInnerTexts()).join(' '));
const row = (i, field) => page.locator(`[name="stages[${i}][${field}]"]`);
const OFFER = process.env.OFFER;

await open(`${BASE}/oferty/${OFFER}/umowy/nowa`);
await submit(page.getByRole('button', { name: 'Wygeneruj szkic umowy' }));
const contract = page.url();
await submit(page.getByRole('link', { name: 'Harmonogram płatności' }));
const url = page.url();

/*--- BEZ NARZUCANIA ---*/
t('brak gotowych etapów', await page.locator('[data-row]').count() === 0);
t('kwota umowy pusta mimo ceny z oferty', await page.locator('#f-agreed').inputValue() === '' && /\d/.test(await page.locator('#f-estimate').innerText()));
t('stawka VAT z ustawień', (await page.locator('#f-vat').inputValue()).length > 0);
t('tryb zadatku bez wartości domyślnej', await page.locator('#f-deposit-mode').inputValue() === '');

/*--- WALIDACJA ---*/
await page.locator('#f-agreed').fill('abc');
await page.getByRole('button', { name: 'Dodaj etap' }).click();
await row(0, 'name').fill('Etap <b>I</b>');
await save();
t('błędne dane pokazują komunikaty', await page.getByText('Podaj kwotę netto, np. 12 000,00.').count() === 1 && await page.getByText('Etap 1: Podaj kwotę netto, np. 1 500,50.').count() === 1);
t('wpisy zostają po błędzie', await row(0, 'name').inputValue() === 'Etap <b>I</b>');

/*--- ROZBIEŻNOŚCI ---*/
await page.locator('#f-agreed').fill('10 000,00');
await row(0, 'net').fill('6 000');
await row(0, 'milestone').fill('Makiety');
await row(0, 'percent').fill('30');
await row(0, 'due').fill('7 dni od odbioru');
await page.getByRole('button', { name: 'Dodaj etap' }).click();
await row(1, 'name').fill('Etap II');
await row(1, 'net').fill('3 000');
await save();
t('zapisano', await page.locator('[data-settlement-total]').count() === 1);
t('niezgodność sumy z kwotą', (await blockers()).includes('różnica 1 000 zł'), await blockers());
t('procent zostaje, z ostrzeżeniem', await row(0, 'percent').inputValue() === '30.00' && (await warnings()).includes('z kwot wynika 60'), await warnings());

await row(1, 'net').fill('4 000');
await page.locator('#f-deposit').fill('1 000');
await save();
t('suma się zgadza', !(await blockers()).includes('różnica'));
t('niewybrany zadatek blokuje', (await blockers()).includes('Wybierz, czy zadatek'), await blockers());

await page.locator('#f-deposit-mode').selectOption('separate');
await save();
t('zadatek odrębny ponad kwotę to rozbieżność', (await blockers()).includes('różnica 1 000 zł'), await blockers());

await page.locator('#f-deposit-mode').selectOption('credited');
await page.locator('#f-deposit-stage').fill('1');
await save();
t('zaliczony rozlicza się', (await blockers()) === '', await blockers());
const total = nbsp(await page.locator('[data-settlement-total]').innerText());
t('razem bez podwójnego liczenia', total.includes('10 000 zł') && total.includes('12 300 zł'), total);
t('zaliczenie obniża etap', nbsp(await page.locator('tbody tr').filter({ hasText: 'Etap <b>I</b>' }).innerText()).includes('6 150 zł'));
await page.screenshot({ path: `${SHOTS}/contract-payments.png`, fullPage: true });

/*--- ODCZYT PO ODŚWIEŻENIU ---*/
await open(url);
t('kwoty po odświeżeniu', await page.locator('#f-agreed').inputValue() === '10000', await page.locator('#f-agreed').inputValue());
t('zadatek po odświeżeniu', await page.locator('#f-deposit-mode').inputValue() === 'credited' && await page.locator('#f-deposit-stage').inputValue() === '1');

/*--- DOKUMENT ---*/
await open(contract);
await page.waitForSelector('.ct-sheet table');
const sheet = nbsp((await page.locator('.ct-sheet').allInnerTexts()).join('\n'));
t('kwota umowy z harmonogramu', sheet.includes('Kwota: 10 000 zł'), sheet.slice(0, 300));
t('tabela w dokumencie', await page.locator('.ct-sheet table tbody tr').count() === 3 && sheet.includes('Do zapłaty') && sheet.includes('Makiety'));
t('tekst etapu dosłownie, bez znaczników', sheet.includes('Etap <b>I</b>') && await page.locator('.ct-sheet table b').count() === 0);
t('brak undefined', !/undefined|\bnull\b|NaN/.test(sheet));
t('pole kwoty tylko do odczytu', await page.locator('[name="values[contract.amount]"]').isDisabled());
t('ostrzeżenia w panelu umowy', (await page.locator('aside [data-payment-warnings]').count()) === 1);
await page.screenshot({ path: `${SHOTS}/contract-payments-doc.png` });

/*--- KONFLIKT ---*/
await open(url);
const stale = await context.newPage();
await stale.goto(url, { timeout: 60000 });
await row(1, 'due').fill('Po wdrożeniu');
await save();
await stale.locator('[name="stages[1][due]"]').fill('Stara karta');
await Promise.all([stale.waitForNavigation({ timeout: 60000 }), stale.getByRole('button', { name: 'Zapisz harmonogram' }).click()]);
t('stara karta nie nadpisuje', await stale.locator('[name="stages[1][due]"]').inputValue() === 'Po wdrożeniu' && await stale.getByText('Ktoś inny zmienił ten szkic').count() >= 1);
await stale.close();

/*--- ROLE ---*/
const other = await browser.newContext({ extraHTTPHeaders: { 'x-test-user': process.env.EMPLOYEE } });
await attach(other);
const employee = await other.newPage();
t('pracownik nie widzi cudzego harmonogramu', (await employee.goto(url, { timeout: 60000 })).status() === 403);
await other.close();

t('brak błędów JS i Alpine', problems.length === 0, problems.slice(0, 3).join(' | '));
await browser.close();
console.log(`WYNIK: ${ok} ok, ${bad} błędów`);
process.exit(bad ? 1 : 0);
