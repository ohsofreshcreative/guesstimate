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
import mediaPick from './guesstimate/media-pick.js';
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
  starts: '',
  init() {
    const { header, footer, body } = this.$refs;
    // Podział liczymy po wczytaniu kroju dokumentu: inny font dałby inne strony niż w eksporcie.
    document.fonts.ready.then(() => {
      const result = renderSheets(this.$refs.sheets, { header: header.innerHTML, footer: footer.innerHTML, nodes: [...body.children] });
      this.pageCount = result.pages;
      this.overflow = result.overflow;
      this.starts = result.starts;
    });
  },
  /** Link pobierania z podziałem stron z panelu. */
  exportUrl(url) { return this.starts ? `${url}&strony=${this.starts}` : url; },
}));
Alpine.data('clickupIntegration', clickupIntegration);
Alpine.data('mediaLibrary', mediaLibrary);
Alpine.data('mediaPick', mediaPick);

window.Alpine = Alpine;
Alpine.start();

// Po starcie Alpine pola formularza (np. ukryte, generowane przez x-for) mają już wartości.
queueMicrotask(initLivePrice);
