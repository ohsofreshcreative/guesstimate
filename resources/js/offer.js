/*--- GUESSTIMATE: DOKUMENT OFERTY (strony A4 poziomo) ---*/
// Bez zależności. Tryby (html[data-mode]):
// — public: oferta klienta; na arkuszu (od 1180 px) nadmiar treści trafia na stronę „cd.” tego samego typu,
//   strona „Sytuacja” (data-fit) zamiast tego dopasowuje typografię; na telefonie strony mają dowolną wysokość;
// — editor: duży podgląd jednej strony w edytorze (kliknięcie elementu → pole w panelu edytora);
// — thumbs: miniatury wszystkich stron.
// Edytor steruje ramką przez window.gstDoc (ten sam origin) i dostaje komunikaty postMessage.

const root = document.documentElement;
const mode = root.dataset.mode || 'public';
const viewer = document.querySelector('[data-doc-viewer]');
const main = viewer?.querySelector('main');
const sheetQuery = window.matchMedia('(min-width: 1180px)');
const MAX_FIT = 5;
let originals = [];
let pages = [];
let current = 0;
let selectedKey = null;
let selectedField = null;

const isSheet = () => mode !== 'public' || sheetQuery.matches;
// offsetHeight/clientWidth to wymiary układu (bez transformacji) — pomiar nie zależy od powiększenia.
const idealHeight = (page) => (page.clientWidth * 210) / 297;
const overflows = (page) => page.offsetHeight > idealHeight(page) + 2;

/* Strona „Sytuacja”: kolejne poziomy zmniejszają odstępy, interlinię i (na końcu) tekst. */
function fit(page) {
  let level = 0;
  page.dataset.fitLevel = '0';
  page.removeAttribute('data-overflow');
  while (overflows(page) && level < MAX_FIT) {
    level += 1;
    page.dataset.fitLevel = String(level);
  }
  const overflow = overflows(page);
  if (overflow) page.dataset.overflow = '1';
  return { level, overflow, ratio: overflow ? idealHeight(page) / page.offsetHeight : 1 };
}

/* Przenosi końcowe elementy treści (lub elementy list/siatek) na nową stronę, dopóki strona się mieści. */
function paginate(page, depth = 0) {
  if (depth > 12 || !overflows(page)) return;
  const body = page.querySelector('[data-flow-body]');
  if (!body) return;

  const next = page.cloneNode(true);
  next.removeAttribute('id');
  next.dataset.continued = '1';
  next.removeAttribute('data-edit');
  const nextBody = next.querySelector('[data-flow-body]');
  const title = next.querySelector('[data-flow-title]');
  if (title && !title.dataset.cd) {
    title.dataset.cd = '1';
    title.append(' (cd.)');
  }

  nextBody.innerHTML = '';
  page.after(next);

  let moved = false;
  while (overflows(page)) {
    const last = body.lastElementChild;
    if (!last) break;

    const splittable = last.matches('ul, ol, [data-split]') && last.children.length > 1;
    if (splittable) {
      let target = nextBody.firstElementChild;
      if (!target || target.dataset.splitOf !== '1') {
        target = last.cloneNode(false);
        target.dataset.splitOf = '1';
        nextBody.prepend(target);
      }
      target.prepend(last.lastElementChild);
      moved = true;
      continue;
    }

    if (body.children.length <= 1) break; // pojedynczy element dłuższy niż strona — strona rośnie, bez ucinania
    nextBody.prepend(last);
    moved = true;
  }

  nextBody.querySelectorAll('[data-split-of]').forEach((el) => el.removeAttribute('data-split-of'));

  if (!moved || !nextBody.children.length) {
    next.remove();
    return;
  }

  paginate(next, depth + 1);
}

const report = {};

function layout() {
  if (mode === 'public') root.classList.toggle('osf-sheet', sheetQuery.matches);
  // W edytorze widać jedną stronę — na czas pomiaru pokazujemy wszystkie (ukryte nie mają wysokości).
  root.classList.add('osf-measuring');

  // Przywrócenie stron do stanu z serwera, potem ponowny podział (np. po zmianie szerokości okna).
  main.querySelectorAll('[data-continued]').forEach((el) => el.remove());
  originals.forEach(({ page, html }) => { page.innerHTML = html; });
  Object.keys(report).forEach((key) => delete report[key]);

  main.querySelectorAll('[data-doc-page]').forEach((page) => {
    if (page.hasAttribute('data-fit')) {
      if (isSheet()) report[page.dataset.pageKey] = fit(page);
      else page.dataset.fitLevel = '0';
    } else if (isSheet() && page.querySelector('[data-flow-body]')) {
      paginate(page);
    }
  });

  pages = Array.from(main.querySelectorAll('[data-doc-page]'));
  pages.forEach((page, i) => page.setAttribute('aria-label', `${page.dataset.pageLabel} — strona ${i + 1} z ${pages.length}`));
  root.classList.remove('osf-measuring');
  if (mode === 'thumbs') labels();
  markCurrent();
  update();
  if (mode === 'public') observe();
}

function capture() {
  originals = Array.from(main.querySelectorAll('[data-doc-page]')).map((page) => ({ page, html: page.innerHTML }));
}

/*--- Tryb publiczny: nawigacja, pełny ekran, sygnał wyświetlenia ---*/

const counter = viewer?.querySelector('[data-doc-counter]');
const prev = viewer?.querySelector('[data-doc-prev]');
const nextBtn = viewer?.querySelector('[data-doc-next]');

function update() {
  if (!counter) return;
  counter.textContent = `Strona ${current + 1} z ${pages.length}`;
  prev.disabled = current <= 0;
  nextBtn.disabled = current >= pages.length - 1;
}

function go(index) {
  const target = pages[Math.max(0, Math.min(pages.length - 1, index))];
  if (!target) return;
  const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  target.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: window.innerHeight > target.offsetHeight ? 'center' : 'start' });
}

let observer;
function observe() {
  observer?.disconnect();
  observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        current = pages.indexOf(entry.target);
        update();
      }
    });
  }, { rootMargin: '-45% 0px -45% 0px' });
  pages.forEach((page) => observer.observe(page));
}

function fullscreen() {
  const el = document.documentElement;
  const active = document.fullscreenElement || document.webkitFullscreenElement;
  if (active) (document.exitFullscreen || document.webkitExitFullscreen).call(document);
  else (el.requestFullscreen || el.webkitRequestFullscreen)?.call(el);
}

function beacon() {
  const url = viewer?.dataset.beacon;
  if (!url) return;
  let sent = false;
  const send = () => {
    if (sent || document.visibilityState !== 'visible') return;
    sent = true;
    if (navigator.sendBeacon) navigator.sendBeacon(url);
    else fetch(url, { method: 'POST', keepalive: true, credentials: 'omit' });
  };
  setTimeout(send, 1500);
  document.addEventListener('visibilitychange', () => setTimeout(send, 1500));
}

/*--- Tryb edytora i miniatur ---*/

/* Podpisy miniatur: numer strony (strony ukryte bez numeru) i nazwa. */
function labels() {
  main.querySelectorAll('.osf-thumb-label').forEach((el) => el.remove());
  let n = 0;
  pages.forEach((page) => {
    const label = document.createElement('p');
    label.className = 'osf-thumb-label';
    label.dataset.pageKey = page.dataset.pageKey;
    const hidden = page.dataset.hidden;
    if (!hidden) n += 1;
    label.textContent = `${hidden ? '–' : n}. ${page.dataset.pageLabel}${page.dataset.continued ? ' (cd.)' : ''}${hidden ? ' · ukryta' : ''}`;
    page.after(label);
  });
}

const post = (data) => window.parent?.postMessage({ gst: true, ...data }, location.origin);

function markCurrent() {
  if (mode === 'public') return;
  pages.forEach((page) => page.toggleAttribute('data-current', page.dataset.pageKey === selectedKey));
  main.querySelectorAll('[data-selected]').forEach((el) => el.removeAttribute('data-selected'));
  if (mode === 'editor' && selectedField) {
    pages.filter((page) => page.dataset.pageKey === selectedKey).forEach((page) => {
      const target = selectedField === 'background' ? page : page.querySelector(`[data-edit="${CSS.escape(selectedField)}"]`);
      target?.setAttribute('data-selected', '');
    });
  }
}

function summary() {
  const keys = [];
  pages.forEach((page) => {
    const key = page.dataset.pageKey;
    const entry = keys.find((k) => k.key === key);
    if (entry) entry.count += 1;
    else keys.push({ key, label: page.dataset.pageLabel, count: 1, hidden: page.dataset.hidden || null });
  });
  return { pages: keys, fit: { ...report } };
}

window.gstDoc = {
  /** Podmienia strony (HTML z serwera) bez przeładowania ramki: style i fonty zostają. */
  render(html) {
    const y = window.scrollY;
    main.innerHTML = html;
    capture();
    layout();
    window.scrollTo(0, y);
    const result = summary();
    post({ type: 'rendered', ...result });
    return result;
  },
  select(key, field = null) {
    selectedKey = key;
    selectedField = field;
    markCurrent();
    if (mode === 'thumbs') pages.find((page) => page.dataset.pageKey === key)?.scrollIntoView({ block: 'nearest' });
  },
  /** Szerokość strony w px (powiększenie podglądu). Arkusz skaluje się proporcjonalnie (cqw). */
  setWidth(px) {
    root.style.setProperty('--page-w', `${Math.round(px)}px`);
  },
  summary,
};

function editorEvents() {
  main.addEventListener('click', (event) => {
    const label = event.target.closest('.osf-thumb-label');
    if (label) {
      post({ type: 'select', page: label.dataset.pageKey });
      return;
    }
    const page = event.target.closest('[data-doc-page]');
    if (!page) return;
    event.preventDefault();
    if (mode === 'thumbs') {
      post({ type: 'select', page: page.dataset.pageKey });
      return;
    }
    const target = event.target.closest('[data-edit]');
    post({ type: 'edit', page: page.dataset.pageKey, field: target ? target.dataset.edit : 'background' });
  });
}

/*--- Start ---*/

if (viewer && main) {
  capture();

  const start = () => {
    if (mode !== 'public') {
      selectedKey = pages[0]?.dataset.pageKey || main.querySelector('[data-doc-page]')?.dataset.pageKey;
    }
    layout();
    if (mode !== 'public') {
      if (!selectedKey) selectedKey = pages[0]?.dataset.pageKey;
      markCurrent();
      post({ type: 'ready', ...summary() });
      return;
    }
    const hash = decodeURIComponent(location.hash.replace('#', ''));
    if (hash) document.getElementById(hash)?.scrollIntoView({ block: 'start' });
  };

  // Podział stron po wczytaniu fontów, żeby mierzyć ostateczne wysokości.
  (document.fonts?.ready || Promise.resolve()).then(start);

  if (mode === 'public') {
    let lastWidth = window.innerWidth;
    let timer;
    window.addEventListener('resize', () => {
      if (Math.abs(window.innerWidth - lastWidth) < 40) return;
      lastWidth = window.innerWidth;
      clearTimeout(timer);
      timer = setTimeout(layout, 250);
    });

    prev?.addEventListener('click', () => go(current - 1));
    nextBtn?.addEventListener('click', () => go(current + 1));

    const fsButton = viewer.querySelector('[data-doc-fullscreen]');
    if (fsButton && (document.documentElement.requestFullscreen || document.documentElement.webkitRequestFullscreen)) {
      fsButton.addEventListener('click', fullscreen);
    } else {
      fsButton?.remove();
    }

    document.addEventListener('keydown', (event) => {
      if (event.target.closest?.('input, textarea, select, [contenteditable]')) return;
      if (['PageDown', 'ArrowRight'].includes(event.key)) { event.preventDefault(); go(current + 1); }
      if (['PageUp', 'ArrowLeft'].includes(event.key)) { event.preventDefault(); go(current - 1); }
    });

    beacon();
  } else {
    editorEvents();
    // Powiększenie zmienia szerokość strony — pomiar i podział stron od nowa.
    let timer;
    let width = main.clientWidth;
    new ResizeObserver(() => {
      if (main.clientWidth === width) return;
      width = main.clientWidth;
      clearTimeout(timer);
      timer = setTimeout(() => { layout(); post({ type: 'rendered', ...summary() }); }, 120);
    }).observe(main);
  }
}
