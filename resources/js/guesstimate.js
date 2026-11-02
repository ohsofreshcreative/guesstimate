/*--- GUESSTIMATE — aplikacja /app/ ---*/
// Osobne wejście Vite: bez GSAP, Swipera i skryptów strony publicznej.

import Alpine from 'alpinejs';
import pageList from './guesstimate/pages.js';
import rowList from './guesstimate/rows.js';
import calcPreview from './guesstimate/preview.js';
import clientPicker from './guesstimate/client-picker.js';
import featurePicker from './guesstimate/features.js';
import richText from './guesstimate/editor.js';
import offerContent from './guesstimate/content.js';
import copyText from './guesstimate/copy.js';
import documentEditor from './guesstimate/document-editor.js';
import contractEditor from './guesstimate/contract-editor.js';
import { renderSheets } from './guesstimate/contract-sheets.js';
import clickupIntegration from './guesstimate/clickup-integration.js';
import mediaLibrary from './guesstimate/media-library.js';
import initLivePrice from './guesstimate/live-price.js';

Alpine.data('pageList', pageList);
Alpine.data('rowList', rowList);
Alpine.data('calcPreview', calcPreview);
Alpine.data('clientPicker', clientPicker);
Alpine.data('featurePicker', featurePicker);
Alpine.data('richText', richText);
Alpine.data('offerContent', offerContent);
Alpine.data('copyText', copyText);
Alpine.data('documentEditor', documentEditor);
Alpine.data('contractEditor', contractEditor);
Alpine.data('contractPreview', () => ({
  pageCount: 0,
  overflow: 0,
  init() {
    const { header, footer, body } = this.$refs;
    const result = renderSheets(this.$refs.sheets, { header: header.innerHTML, footer: footer.innerHTML, nodes: [...body.children] });
    this.pageCount = result.pages;
    this.overflow = result.overflow;
  },
}));
Alpine.data('clickupIntegration', clickupIntegration);
Alpine.data('mediaLibrary', mediaLibrary);

window.Alpine = Alpine;
Alpine.start();

// Po starcie Alpine pola formularza (np. ukryte, generowane przez x-for) mają już wartości.
queueMicrotask(initLivePrice);
