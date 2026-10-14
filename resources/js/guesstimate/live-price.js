/*--- GUESSTIMATE: WYCENA NA ŻYWO (panel podsumowania w kreatorze) ---*/
// Przeglądarka niczego nie liczy: wysyła dane formularza bieżącego kroku do serwera (ten sam silnik PHP
// co przy zapisie) i podmienia zawartość panelu. Debounce, przerywanie starszych zapytań i numer sekwencji,
// żeby spóźniona odpowiedź nie nadpisała nowszej. Nic nie jest zapisywane.

import { nonce } from './http.js';

export default function initLivePrice() {
  const panel = document.querySelector('[data-live-price][data-url]');
  if (!panel) return;

  const form = Array.from(document.querySelectorAll('form')).find((f) => /\/kreator\/\d+$/.test(f.getAttribute('action') || ''));
  if (!form) return;

  const body = panel.querySelector('[data-live-body]');
  const status = panel.querySelector('[data-live-status]');
  let seq = 0;
  let applied = 0;
  let timer = null;
  let controller = null;

  const showError = (message) => {
    body.innerHTML = '';
    const p = document.createElement('p');
    p.className = 'mt-2 text-sm text-danger';
    p.dataset.liveError = '';
    p.textContent = message;
    const hint = document.createElement('p');
    hint.className = 'mt-1 text-sm text-ink-muted';
    hint.textContent = 'Wycena nie jest aktualna — cena pojawi się po ponownym przeliczeniu.';
    body.append(p, hint);
  };

  async function run() {
    const mine = ++seq;
    controller?.abort();
    controller = new AbortController();
    status.hidden = false;
    panel.setAttribute('aria-busy', 'true');

    const data = new FormData(form);
    data.delete('action');
    data.delete('_gst_nonce');
    data.set('seq', String(mine));

    try {
      const response = await fetch(panel.dataset.url, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'X-GST-Nonce': nonce(), Accept: 'application/json' },
        signal: controller.signal,
      });

      if (!response.ok) throw new Error(response.status === 419 ? 'Sesja wygasła — odśwież stronę.' : `Nie udało się przeliczyć wyceny (błąd ${response.status}).`);
      const result = await response.json();

      // Starszy wynik nigdy nie nadpisuje nowszego.
      if (result.seq !== seq || result.seq <= applied) return;
      applied = result.seq;
      body.innerHTML = result.html;
      panel.dataset.liveSeq = String(applied);
      panel.dispatchEvent(new CustomEvent('gst-live-updated', { detail: result, bubbles: true }));
    } catch (error) {
      if (error.name === 'AbortError') return;
      if (mine === seq) showError(error.message || 'Nie udało się przeliczyć wyceny.');
    } finally {
      if (mine === seq) {
        status.hidden = true;
        panel.removeAttribute('aria-busy');
      }
    }
  }

  const schedule = () => {
    clearTimeout(timer);
    timer = setTimeout(run, 300);
  };

  form.addEventListener('input', schedule);
  form.addEventListener('change', schedule);
  window.addEventListener('gst-recalc', schedule);
}
