// Test UI generatora umów. Prawdziwa aplikacja w headless Chromium, bez nasłuchującego serwera.
// NIGDY na bazie innej niż kopia: cgi-prepend.php loguje użytkownika i łączy się z 127.0.0.1:33177.
//
// Wymaga migracji i tests/ui/contract-prepare.php na kopii oraz tego samego co document-layout.mjs.
// PW_CORE=… PW_CHROME=… SHOTS=/tmp OFFER=id EMPLOYEE=2 node tests/ui/contract-generator.mjs

import { attach } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const SHOTS = process.env.SHOTS || '.';
const BASE = 'http://guesstimate.local/app';
const browser = await chromium.launch({ headless: true, executablePath: process.env.PW_CHROME });
const context = await browser.newContext({ viewport: { width: 1500, height: 950 } });
await attach(context);
const page = await context.newPage();
const problems = [];
page.on('pageerror', (e) => problems.push(e.message));
page.on('console', (m) => { if (['error', 'warning'].includes(m.type()) && /Alpine|Error/.test(m.text())) problems.push(m.text()); });
let ok = 0; let bad = 0;
const t = (name, cond, extra = '') => { if (cond) { ok += 1; console.log('OK  ', name); } else { bad += 1; console.log('FAIL', name, extra); } };
const open = (url) => page.goto(url, { timeout: 60000 });
const submit = async (locator) => { await Promise.all([page.waitForNavigation({ timeout: 60000 }), locator.click()]); };
const sheets = () => page.locator('.ct-sheet').allInnerTexts().then((list) => list.join('\n'));
const value = (key) => page.locator(`[name="values[${key}]"]`);
const OFFER = process.env.OFFER;

/*--- DANE WYKONAWCY ---*/
await open(`${BASE}/ustawienia/wykonawca`);
await page.locator('#f-contractor-nip').fill('123');
await submit(page.getByRole('button', { name: 'Zapisz' }));
t('błędny NIP wykonawcy odrzucony', await page.getByText('Podaj poprawny NIP.').count() === 1);
await page.locator('#f-contractor-nip').fill('');
await page.locator('#f-contractor-company_name').fill('Wykonawca Pierwszy');
await submit(page.getByRole('button', { name: 'Zapisz' }));

/*--- PRZED GENEROWANIEM ---*/
await open(`${BASE}/oferty/${OFFER}`);
await submit(page.getByRole('link', { name: 'Przygotuj umowę' }));
t('jedyny szablon wybrany od razu', await page.locator('#f-template').inputValue() !== '');
const notice = await page.locator('[role=alert], .alert, main').first().innerText();
t('brakujące pola przed generowaniem', /Brakujące pola:.*Adres klienta/s.test(notice) && /Kwota umowy/.test(notice), notice.slice(0, 300));
t('kwota umowy bez podpowiedzi, poza formularzem wartości', await value('contract.amount').inputValue() === '' && await value('contract.amount').isDisabled());
t('dane oferty podstawione w formularzu', (await value('offer.number').inputValue()).length > 0);
t('brak undefined w formularzu', !/undefined|\bnull\b|NaN/.test(await page.locator('main').innerText()));
await value('client.address').fill('ul. Testowa 1');
await submit(page.getByRole('button', { name: 'Wygeneruj szkic umowy' }));
t('szkic wygenerowany', /\/umowy\/\d+$/.test(page.url()), page.url());
const contract = page.url();

/*--- SNAPSHOT I PODGLĄD ---*/
await page.waitForSelector('.ct-sheet');
let text = await sheets();
t('podgląd ma dane', text.includes('Wykonawca Pierwszy') && text.includes('ul. Testowa 1'), text.slice(0, 300));
t('braki widoczne w dokumencie', text.includes('[brak: Kwota umowy netto]') && text.includes('[brak: Miejsce zawarcia umowy]'));
t('brak undefined w podglądzie', !/undefined|\bnull\b|NaN/.test(text));
t('numery stron policzone', /Strona 1 z 1/.test(text), text.slice(-80));
t('tokeny nie zostały w dokumencie', await page.locator('.ct-sheet [data-var]:not([data-var^="page."])').count() === 0);
t('panel pokazuje braki', await page.getByText('Brakujące pola').count() === 1);

/*--- RĘCZNA EDYCJA, ESCAPOWANIE ---*/
const attack = '<img src=x onerror="window.pwn=1"> & "cudzysłów" C:\\plik';
await value('terms.place').fill(attack);
await submit(page.getByRole('button', { name: 'Zapisz wartości' }));
await page.waitForSelector('.ct-sheet');
text = await sheets();
t('wartość wyświetlona dosłownie', text.includes(attack), text.slice(0, 400));
t('kod nie został wykonany', await page.evaluate(() => window.pwn === undefined && !document.querySelector('.ct-sheet img')));
const gaps = await page.locator('aside [role=status]').allInnerTexts();
t('uzupełnione pole znika z listy braków', !gaps.join().includes('Miejsce') && gaps.join().includes('Kwota umowy netto'), gaps.join());
const original = await value('offer.number').inputValue();
await value('offer.number').fill('RĘCZNY-NUMER');
await submit(page.getByRole('button', { name: 'Zapisz wartości' }));
await page.waitForSelector('.ct-sheet');
t('ręczna zmiana danych oferty', (await sheets()).includes('RĘCZNY-NUMER'));

/*--- ZMIANA ŹRÓDŁA: BEZ ŻYWEGO POWIĄZANIA ---*/
await open(`${BASE}/ustawienia/wykonawca`);
await page.locator('#f-contractor-company_name').fill('Wykonawca Drugi');
await submit(page.getByRole('button', { name: 'Zapisz' }));
await open(contract);
await page.waitForSelector('.ct-sheet');
text = await sheets();
t('umowa zostaje przy kopii danych', text.includes('Wykonawca Pierwszy') && !text.includes('Wykonawca Drugi'));
t('zmiana pokazana przed odświeżeniem', await page.getByText('Dane w ofercie się zmieniły').count() === 1 && await page.locator('aside').getByText('Wykonawca Drugi').count() === 1);

/*--- KONFLIKT DWÓCH KART ---*/
const stale = await context.newPage();
await stale.goto(contract, { timeout: 60000 });
await value('terms.place').fill('Gdańsk');
await submit(page.getByRole('button', { name: 'Zapisz wartości' }));
await Promise.all([stale.waitForNavigation({ timeout: 60000 }), stale.getByRole('button', { name: 'Odśwież dane z oferty' }).click()]);
t('stara karta nie odświeża', await stale.getByText('Ktoś inny zmienił ten szkic').count() >= 1 && (await stale.locator('.ct-sheet').allInnerTexts()).join().includes('Wykonawca Pierwszy'));
await stale.close();

/*--- ŚWIADOME ODŚWIEŻENIE ---*/
await open(contract);
await submit(page.getByRole('button', { name: 'Odśwież dane z oferty' }));
await page.waitForSelector('.ct-sheet');
text = await sheets();
t('odświeżenie bierze nowe dane', text.includes('Wykonawca Drugi'));
t('wartości ręczne zostają', text.includes('RĘCZNY-NUMER') && text.includes('Gdańsk'), text.slice(0, 300));
t('po odświeżeniu brak różnic', await page.getByText('Dane w ofercie się zmieniły').count() === 0);
await value('offer.number').fill('');
await submit(page.getByRole('button', { name: 'Zapisz wartości' }));
await page.waitForSelector('.ct-sheet');
t('wyczyszczenie wraca do danych oferty', (await sheets()).includes(original) && !(await sheets()).includes('RĘCZNY-NUMER'), `[${original}] ${(await sheets()).slice(0, 260)} | pole: ${await value('offer.number').inputValue()} | ${await page.locator('aside').innerText().then((x) => x.slice(0, 200))}`);
await page.screenshot({ path: `${SHOTS}/contract-generated.png` });

await open(`${BASE}/oferty/${OFFER}`);
t('umowa na karcie oferty', await page.locator('section[aria-labelledby=gst-contracts] a[href*="/app/umowy/"]').count() === 1);

/*--- ROLE ---*/
const other = await browser.newContext({ extraHTTPHeaders: { 'x-test-user': process.env.EMPLOYEE } });
await attach(other);
const employee = await other.newPage();
const refused = await employee.goto(contract, { timeout: 60000 });
t('pracownik nie widzi cudzej umowy', refused.status() === 403, String(refused.status()));
await other.close();

t('brak błędów JS i Alpine', problems.length === 0, problems.slice(0, 3).join(' | '));
await browser.close();
console.log(`WYNIK: ${ok} ok, ${bad} błędów`);
process.exit(bad ? 1 : 0);
