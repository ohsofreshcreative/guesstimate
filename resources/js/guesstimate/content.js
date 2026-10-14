/*--- GUESSTIMATE: TREŚCI OFERTY + GENEROWANIE Z AI ---*/
// AI zwraca propozycję. Pustą sekcję wypełniamy od razu; istniejącą treść zastępujemy dopiero po potwierdzeniu.

import { postJson } from './http.js';

const MIN = 3;
const MAX = 5;
const empty = (html) => !String(html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();

export default (initial, aiUrl) => ({
  situation: initial.situation || '',
  blocks: (initial.blocks || []).map((b) => ({ title: b.title || '', html: b.html || '' })),
  prompt: '',
  busy: null,
  error: null,
  proposal: null,
  dirty: false,

  init() {
    while (this.blocks.length < MIN) this.blocks.push({ title: '', html: '' });
    this.$watch('situation', () => { this.dirty = true; });
    this.$watch('blocks', () => { this.dirty = true; }, { deep: true });
    this.$nextTick(() => { this.dirty = false; });
  },

  get canAdd() { return this.blocks.length < MAX; },
  get canRemove() { return this.blocks.length > MIN; },

  addBlock() { if (this.canAdd) this.blocks.push({ title: '', html: '' }); },
  removeBlock(index) { if (this.canRemove) this.blocks.splice(index, 1); },
  moveBlock(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= this.blocks.length) return;
    const [block] = this.blocks.splice(index, 1);
    this.blocks.splice(target, 0, block);
  },

  hasContent(target) {
    if (target === 'all') return !empty(this.situation) || this.blocks.some((b) => b.title.trim() || !empty(b.html));
    if (target === 'situation') return !empty(this.situation);
    const block = this.blocks[Number(target.split(':')[1])];
    return !!block && (block.title.trim() !== '' || !empty(block.html));
  },

  label(target) {
    if (target === 'all') return 'całą treść oferty';
    if (target === 'situation') return 'sekcję „Jak rozumiemy Twoją sytuację?”';
    return `blok ${Number(target.split(':')[1]) + 1}`;
  },

  async run(action, target = 'all') {
    if (this.busy) return;
    this.busy = `${action}:${target}`;
    this.error = null;

    try {
      const result = await postJson(aiUrl, {
        action,
        target,
        prompt: this.prompt,
        content: { situation: this.situation, blocks: this.blocks },
      });

      if (this.hasContent(target)) this.proposal = { target, result };
      else this.apply(target, result);
    } catch (error) {
      this.error = error.message;
    } finally {
      this.busy = null;
    }
  },

  apply(target, result) {
    if (target === 'all') {
      this.situation = result.situation;
      this.blocks = result.blocks.slice(0, MAX).map((b) => ({ title: b.title, html: b.html }));
      while (this.blocks.length < MIN) this.blocks.push({ title: '', html: '' });
    } else if (target === 'situation') {
      this.situation = result.html;
    } else {
      const index = Number(target.split(':')[1]);
      this.blocks[index] = { title: result.title || this.blocks[index].title, html: result.html };
    }
    this.proposal = null;
  },

  accept() { if (this.proposal) this.apply(this.proposal.target, this.proposal.result); },
  reject() { this.proposal = null; },
});
