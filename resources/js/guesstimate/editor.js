/*--- GUESSTIMATE: LEKKI EDYTOR WYSIWYG ---*/
// contenteditable + kilka poleceń (akapit, pogrubienie, kursywa, listy, link). Bez zewnętrznych bibliotek.
// HTML jest zawsze czyszczony po stronie serwera (OfferContent::clean) — tu dbamy tylko o wygodę.
// Użycie: <div x-data="richText()" x-modelable="html" x-model="...">

export default () => ({
  html: '',
  internal: false,

  init() {
    this.$refs.area.innerHTML = this.html || '';
    this.$watch('html', (value) => {
      if (this.internal) {
        this.internal = false;
        return;
      }
      this.$refs.area.innerHTML = value || '';
    });
  },

  sync() {
    this.internal = true;
    this.html = this.$refs.area.innerHTML;
  },

  cmd(command, value = null) {
    this.$refs.area.focus();
    document.execCommand(command, false, value);
    this.sync();
  },

  /** Kolor tekstu (klasa osf-c-*): zaznaczony fragment, a bez zaznaczenia — cały nagłówek lub akapit z kursorem. */
  color(name) {
    const area = this.$refs.area;
    const selection = window.getSelection();
    if (!selection?.rangeCount || !area.contains(selection.anchorNode)) return;
    const range = selection.getRangeAt(0);
    const blockOf = (node) => (node.nodeType === 1 ? node : node.parentElement)?.closest('h3,h4,h5,p,li');
    const paint = (el) => {
      [...el.classList].filter((cls) => cls.startsWith('osf-c-')).forEach((cls) => el.classList.remove(cls));
      if (name) el.classList.add(`osf-c-${name}`);
      if (!el.classList.length) el.removeAttribute('class');
    };
    const unwrap = (root) => root.querySelectorAll('span[class^="osf-c-"]').forEach((span) => span.replaceWith(...span.childNodes));
    const start = blockOf(range.startContainer);
    const end = blockOf(range.endContainer);

    if (range.collapsed || !start || start !== end) {
      // Bez zaznaczenia albo przez kilka bloków: kolor całych bloków (nagłówków, akapitów).
      const blocks = range.collapsed ? [start] : [...area.querySelectorAll('h3,h4,h5,p,li')].filter((el) => range.intersectsNode(el));
      blocks.filter((el) => el && area.contains(el)).forEach((el) => { unwrap(el); paint(el); });
    } else {
      const span = document.createElement('span');
      span.append(range.extractContents());
      unwrap(span);
      if (name) {
        span.className = `osf-c-${name}`;
        range.insertNode(span);
      } else {
        const nodes = [...span.childNodes];
        nodes.reverse().forEach((node) => range.insertNode(node));
      }
    }
    this.sync();
  },

  link() {
    const selection = window.getSelection();
    if (!selection || selection.isCollapsed) {
      this.$refs.area.focus();
      return;
    }
    const url = (window.prompt('Adres linku (https://…)', 'https://') || '').trim();
    if (/^(https?:\/\/|mailto:|tel:)/i.test(url)) this.cmd('createLink', url);
  },

  paste(event) {
    event.preventDefault();
    const text = event.clipboardData?.getData('text/plain') || '';
    const html = text.split(/\n{2,}/).map((p) => `<p>${p.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])).replace(/\n/g, '<br>')}</p>`).join('');
    document.execCommand('insertHTML', false, html);
    this.sync();
  },

  keydown(event) {
    if ((event.ctrlKey || event.metaKey) && ['b', 'i'].includes(event.key.toLowerCase())) {
      event.preventDefault();
      this.cmd(event.key.toLowerCase() === 'b' ? 'bold' : 'italic');
    }
  },
});
