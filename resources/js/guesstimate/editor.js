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
