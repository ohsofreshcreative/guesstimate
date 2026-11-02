// Test UI obiegu umowy i dostępu po ID. Prawdziwa aplikacja w headless Chromium, trzy role.
// NIGDY na bazie innej niż kopia: cgi-prepend.php loguje użytkownika i łączy się z 127.0.0.1:33177.
//
// Wymaga migracji i tests/ui/contract-workflow-prepare.php na kopii.
// PW_CORE=… PW_CHROME=… SHOTS=/tmp EMPLOYEE=2 MANAGER=3 ADMIN_OFFER=… EMPLOYEE_OFFER=… DRAFT=… INCOMPLETE=… APPROVED=… ON_MINE=… REMOVED=… OFFER_NUMBER=… CLIENT=… node tests/ui/contract-workflow.mjs

import { attach } from './bridge.mjs';

const { chromium } = await import(process.env.PW_CORE || 'playwright-core');
const SHOTS = process.env.SHOTS || '.';
const BASE = 'http://guesstimate.local/app';
const { EMPLOYEE, MANAGER, ADMIN_OFFER, EMPLOYEE_OFFER, DRAFT, INCOMPLETE, APPROVED, ON_MINE, REMOVED, OFFER_NUMBER } = process.env;
const CLIENT = (process.env.CLIENT || '').replaceAll('_', ' ');
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
const open = (page, path) => page.goto(`${BASE}${path}`, { timeout: 60000 });
const submit = async (page, locator) => { await Promise.all([page.waitForNavigation({ timeout: 60000 }), locator.click()]); };
const titles = (page) => page.locator('tbody tr td:first-child').allInnerTexts();
const nonce = (page) => page.locator('input[name="_gst_nonce"]').first().inputValue();
const post = (page, path, token, fields = {}) => page.evaluate(async ([url, data]) => {
  const r = await fetch(url, { method: 'POST', body: new URLSearchParams(data), redirect: 'manual' });
  return r.type === 'opaqueredirect' ? 302 : r.status;
}, [`${BASE}${path}`, { _gst_nonce: token, expected: 'x', ...fields }]);
const get = (page, path) => page.evaluate(async (url) => {
  const r = await fetch(url, { redirect: 'manual' });
  return r.type === 'opaqueredirect' ? 302 : r.status;
}, `${BASE}${path}`);

/*--- LISTA ---*/
const admin = await session();
await open(admin, '/umowy');
t('menu: Umowy i Szablony umów', await admin.getByRole('link', { name: 'Umowy', exact: true }).count() >= 1 && await admin.getByRole('link', { name: 'Szablony umów' }).count() >= 1);
t('lista stronicowana', (await titles(admin)).length === 20 && (await admin.getByText('25 wyników').count()) === 1, String((await titles(admin)).length));
await admin.getByRole('link', { name: 'Następna' }).click();
await admin.waitForURL(/page=2/);
t('druga strona', (await titles(admin)).length === 5);
await admin.screenshot({ path: `${SHOTS}/contract-list.png` });
await open(admin, '/umowy?q=alfa');
t('szukanie po nazwie', (await titles(admin)).join() === 'Umowa alfa');
await open(admin, `/umowy?q=${encodeURIComponent(OFFER_NUMBER)}`);
t('szukanie po numerze oferty', (await titles(admin)).length === 20);
await open(admin, `/umowy?q=${encodeURIComponent(CLIENT)}`);
t('szukanie po kliencie', (await titles(admin)).length === 20);
await open(admin, '/umowy?q=%25');
t('znak % nie jest wzorcem', await admin.getByText('Żadna umowa nie pasuje').count() === 1);
await open(admin, '/umowy?status=approved');
t('filtr statusu', (await titles(admin)).join() === 'Umowa beta');
await open(admin, `/umowy?author=${EMPLOYEE}`);
t('filtr autora', await admin.getByText('Żadna umowa nie pasuje').count() === 1);
await open(admin, '/umowy?q=usuni');
t('usunięta umowa znika z listy', await admin.getByText('Żadna umowa nie pasuje').count() === 1);
t('usunięta umowa: 404 po ID', (await open(admin, `/umowy/${REMOVED}`)).status() === 404);
await open(admin, `/oferty/${ADMIN_OFFER}`);
t('oferta prowadzi do umów', await admin.getByRole('link', { name: 'Umowa alfa' }).count() === 1 && await admin.getByRole('link', { name: 'Przygotuj umowę' }).count() === 1);

/*--- ZATWIERDZANIE ---*/
await open(admin, `/umowy/${INCOMPLETE}`);
t('braki blokują przycisk', await admin.getByRole('button', { name: 'Zatwierdź umowę' }).isDisabled());
await open(admin, `/umowy/${DRAFT}`);
const adminToken = await nonce(admin);
t('szkic bez pobierania', await admin.getByRole('link', { name: 'Pobierz PDF' }).count() === 0);
t('nieaktualny skrót odrzucony', await post(admin, `/umowy/${DRAFT}/zatwierdz`, adminToken) === 302);
await open(admin, `/umowy/${DRAFT}`);
t('po odrzuceniu nadal szkic', await admin.getByRole('button', { name: 'Zatwierdź umowę' }).count() === 1);

/*--- PRACOWNIK: DOSTĘP PO ID ---*/
const employee = await session(EMPLOYEE);
await open(employee, '/umowy');
t('pracownik ma Umowy, bez Szablonów', await employee.getByRole('link', { name: 'Umowy', exact: true }).count() >= 1 && await employee.getByRole('link', { name: 'Szablony umów' }).count() === 0);
t('pracownik widzi tylko swoje', (await titles(employee)).join() === 'Cudza na mojej ofercie', (await titles(employee)).join());
t('brak filtra autora', await employee.locator('#f-author').count() === 0);
await open(employee, `/umowy?author=1&q=${encodeURIComponent('alfa')}`);
t('parametr autora nie omija zakresu', await employee.getByText('Żadna umowa').count() === 1);
await open(employee, `/umowy/${ON_MINE}`);
const token = await nonce(employee).catch(() => '');
t('pracownik: umowa do własnej oferty tylko podgląd', await employee.locator('main, body').first().innerText().then((x) => x.includes('Szkic zatwierdza manager')) && await employee.getByRole('button', { name: 'Zapisz wartości' }).count() === 0);
t('pracownik nie ma przycisku zatwierdzenia', await employee.getByRole('button', { name: 'Zatwierdź umowę' }).count() === 0);

for (const id of [DRAFT, APPROVED]) {
  for (const path of [`/umowy/${id}`, `/umowy/${id}?wersja=1`, `/umowy/${id}/platnosci`, `/umowy/${id}/eksport/pdf`, `/umowy/${id}/eksport/docx`, `/umowy/${id}/eksport/pdf?wersja=1`]) {
    t(`cudza umowa GET ${path}: 403`, await get(employee, path) === 403);
  }
}

await open(employee, `/oferty/${EMPLOYEE_OFFER}`);
const create = await employee.locator('input[name="_gst_nonce"]').first().inputValue().catch(() => '');
const guard = token || create;
t('token pracownika dostępny', guard !== '');

for (const action of ['zatwierdz', 'wartosci', 'odswiez', 'platnosci']) {
  t(`cudza umowa POST ${action}: 403`, await post(employee, `/umowy/${DRAFT}/${action}`, guard) === 403);
}

t('pracownik nie zatwierdzi umowy do własnej oferty', await post(employee, `/umowy/${ON_MINE}/zatwierdz`, guard) === 403);
t('pracownik nie wygeneruje z cudzej oferty', await get(employee, `/oferty/${ADMIN_OFFER}/umowy/nowa`) === 403);
t('pracownik nie wygeneruje POST', await post(employee, `/oferty/${ADMIN_OFFER}/umowy`, guard, { template_id: '1', title: 'x' }) === 403);
t('pracownik nie wejdzie w szablony', await get(employee, '/szablony-umow') === 403);
t('nieistniejąca umowa: 404', await get(employee, '/umowy/999999') === 404);

/*--- PEŁNY OBIEG: PRACOWNIK, POTEM MANAGER ---*/
await open(employee, `/oferty/${EMPLOYEE_OFFER}/umowy/nowa`);
await employee.locator('[name="title"]').fill('Obieg pracownika');
await submit(employee, employee.getByRole('button', { name: 'Wygeneruj szkic umowy' }));
t('pracownik generuje szkic z własnej oferty', /\/umowy\/\d+$/.test(employee.url()), employee.url());
const mine = employee.url().split('/').pop();
await employee.locator('[name="values[client.address]"]').fill('ul. Obiegowa 7');
await submit(employee, employee.getByRole('button', { name: 'Zapisz wartości' }));
t('zapis szkicu i podgląd', (await employee.locator('.ct-sheet').allInnerTexts()).join().includes('ul. Obiegowa 7'));
await open(employee, `/umowy/${mine}/eksport/pdf`).catch(() => null);
t('szkicu nie można pobrać', await employee.getByText('tylko dla zatwierdzonej wersji').count() >= 1);

const manager = await session(MANAGER);
await open(manager, `/umowy/${mine}`);
await manager.getByRole('button', { name: 'Zatwierdź umowę' }).click();
const dialog = manager.getByRole('dialog');
t('okno mówi, że nic nie jest wysyłane', (await dialog.innerText()).includes('nie wysyła ani nie podpisuje'));
await manager.screenshot({ path: `${SHOTS}/contract-approve.png` });
await dialog.getByRole('button', { name: 'Anuluj' }).click();
await open(manager, `/umowy/${mine}`);
t('anulowanie nic nie zmienia', await manager.getByRole('button', { name: 'Zatwierdź umowę' }).count() === 1);
await manager.getByRole('button', { name: 'Zatwierdź umowę' }).click();
await submit(manager, manager.getByRole('dialog').getByRole('button', { name: 'Zatwierdź', exact: true }));
t('manager zatwierdza', await manager.getByRole('link', { name: 'Pobierz PDF' }).count() === 1 && await manager.getByRole('button', { name: 'Zatwierdź umowę' }).count() === 0);
await open(employee, `/umowy/${mine}`);
t('autor widzi status i pobranie', await employee.getByRole('link', { name: 'Pobierz DOCX' }).count() === 1 && (await employee.locator('header').first().innerText()).includes('Zatwierdzona'));
t('autor pobiera PDF', await get(employee, `/umowy/${mine}/eksport/pdf`) === 200);
t('autor pobiera DOCX', await get(employee, `/umowy/${mine}/eksport/docx`) === 200);
t('zatwierdzonej nie można zmienić', await post(employee, `/umowy/${mine}/wartosci`, guard) === 302);
await open(manager, '/umowy?status=approved');
t('lista managera pokazuje zatwierdzoną', (await titles(manager)).includes('Obieg pracownika'));
await manager.screenshot({ path: `${SHOTS}/contract-list-manager.png` });

/*--- SZABLON ---*/
await open(manager, '/szablony-umow');
await submit(manager, manager.getByRole('button', { name: 'Kopiuj' }).first());
t('duplikowanie szablonu', /szablony-umow\/\d+/.test(manager.url()), manager.url());

/*--- BRAK WYSYŁKI I PODPISU ---*/
await open(manager, `/umowy/${mine}`);
const actions = (await manager.locator('button, a').allInnerTexts()).join('|');
t('brak wysyłki i podpisu', !/Wyślij|Podpisz|E-?mail/i.test(actions), actions);
t('brak błędów JS', problems.length === 0, problems.slice(0, 3).join(' | '));
await browser.close();
console.log(`WYNIK: ${ok} ok, ${bad} błędów`);
process.exit(bad ? 1 : 0);
