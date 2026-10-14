{{-- Propozycja AI — treść jest zastępowana dopiero po akceptacji. --}}
<div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10" x-show="proposal || aiError" x-cloak role="dialog" aria-modal="true" aria-labelledby="gst-proposal-title">
  <div class="fixed inset-0 bg-ink/50"></div>
  <div class="relative w-full max-w-2xl rounded-panel bg-surface shadow-overlay">
    <div class="border-b border-line px-5 py-3">
      <h2 id="gst-proposal-title" class="text-lg font-semibold">
        <span x-show="aiError">Asystent AI</span>
        <span x-show="proposal" x-text="proposal ? (proposal.action === 'fit' ? 'Propozycja AI: tekst dopasowany do strony' : `Propozycja AI: ${aiLabel(proposal.target)}`) : ''"></span>
      </h2>
      <p class="text-sm text-ink-muted" x-show="proposal">Obecna treść zostanie zastąpiona dopiero po akceptacji. Zmianę możesz jeszcze edytować przed zapisem.</p>
    </div>
    <div class="max-h-120 overflow-y-auto px-5 py-4">
      <p class="text-sm text-danger" x-show="aiError" x-text="aiError"></p>
      <template x-if="proposal">
        <div class="gst-prose rounded-control border border-line bg-canvas/50 p-4" data-proposal>
          <p x-show="proposal.result.title"><strong x-text="proposal.result.title"></strong></p>
          <template x-if="proposal.result.html"><div x-html="proposal.result.html"></div></template>
          <template x-if="proposal.result.text"><p class="whitespace-pre-line" x-text="proposal.result.text"></p></template>
        </div>
      </template>
    </div>
    <div class="flex justify-end gap-2 border-t border-line px-5 py-3">
      <button type="button" class="{{ $ghost }}" x-on:click="reject(); aiError = null" x-text="aiError ? 'Zamknij' : 'Zostaw obecną treść'"></button>
      <button type="button" class="{{ $primary }}" x-show="proposal" x-on:click="accept()" data-proposal-accept>Zastąp treść</button>
    </div>
  </div>
</div>
