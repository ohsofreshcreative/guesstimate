/*--- GUESSTIMATE: ZAPYTANIA FETCH (nonce WP, JSON) ---*/

export const nonce = () => document.querySelector('meta[name="gst-nonce"]')?.content || '';

export async function getJson(url) {
  const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  return parse(response);
}

export async function postJson(url, body) {
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-GST-Nonce': nonce() },
    body: JSON.stringify(body),
  });
  return parse(response);
}

async function parse(response) {
  let data = null;
  try {
    data = await response.json();
  } catch (error) {
    data = null;
  }

  if (!response.ok) {
    const message = data?.error || data?.message || (response.status === 419 ? 'Sesja wygasła. Odśwież stronę.' : `Błąd serwera (${response.status}).`);
    const error = new Error(message);
    error.status = response.status;
    error.data = data;
    throw error;
  }

  return data;
}
