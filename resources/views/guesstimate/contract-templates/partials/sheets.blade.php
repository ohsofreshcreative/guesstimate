{{-- Dokument A4: edycja na rozdzielonych stronach albo podgląd stron. Wymaga $regions, $editable. --}}
<main class="min-w-0 flex-1 overflow-auto bg-canvas p-6">
  <div class="mx-auto w-fit" x-show="mode === 'edit'">
    <p class="mb-3 text-center text-sm text-ink-soft"><span x-text="pageCount"></span> str. A4, podział jak w podglądzie i eksporcie. Nagłówek edytujesz na pierwszej stronie, stopkę na ostatniej.</p>
    {{-- Odstępy przenoszące treść na kolejną kartkę: osobny arkusz stylów, więc nie trafiają do zapisu. --}}
    <style x-ref="spacers"></style>
    <div x-ref="paper" class="ct-paper ct-pages ct-doc relative isolate" :style="paperStyle()" x-on:keydown="keydown($event)" x-on:input.debounce.400ms="record()" x-on:paste="paste($event)">
      {{-- Kartki (tło i przerwy) oraz kopie nagłówka i stopki: poza częściami dokumentu, więc nie trafiają do zapisu. --}}
      <template x-for="sheet in sheets" :key="sheet.page">
        <div class="ct-page-bg pointer-events-none absolute inset-x-0 -z-10" :style="`top: ${sheet.top}px; height: ${pageHeight}px`" aria-hidden="true" data-page-sheet>
          <span class="absolute bottom-full right-0 mb-1 text-xs text-ink-muted" x-text="`Strona ${sheet.page}`"></span>
        </div>
      </template>
      <div x-ref="header" class="ct-region ct-region-side ct-paper-header" data-placeholder="Nagłówek każdej strony" aria-label="Nagłówek dokumentu" @if ($editable) contenteditable="true" @endif>{!! $regions['header'] !!}</div>
      <div x-ref="body" class="ct-region ct-doc-body my-3" aria-label="Treść dokumentu" @if ($editable) contenteditable="true" @endif>{!! $regions['body'] !!}</div>
      <div x-ref="footer" class="ct-region ct-region-side ct-paper-footer" :style="footerStyle" data-placeholder="Stopka każdej strony" aria-label="Stopka dokumentu" @if ($editable) contenteditable="true" @endif>{!! $regions['footer'] !!}</div>
      <template x-for="sheet in sheets.slice(1)" :key="`h${sheet.page}`">
        <div class="ct-region-side ct-paper-header pointer-events-none absolute opacity-60" :style="`top: ${sheet.top + page.header}px; left: ${page.left}px; right: ${page.right}px`" x-html="headerHtml" aria-hidden="true"></div>
      </template>
      <template x-for="sheet in sheets.slice(0, -1)" :key="`f${sheet.page}`">
        <div class="ct-region-side ct-paper-footer pointer-events-none absolute opacity-60" :style="`top: ${sheet.top + pageHeight - page.footer - footerHeight}px; left: ${page.left}px; right: ${page.right}px`" x-html="footerHtml" aria-hidden="true"></div>
      </template>
    </div>
    <div x-ref="measure" class="pointer-events-none invisible fixed left-0 top-0" aria-hidden="true"></div>
  </div>
  <div x-show="mode === 'preview'" x-cloak>
    <p class="mb-3 text-center text-sm text-ink-soft">
      <span x-text="pageCount"></span> str. Tak samo podzielone będą PDF i DOCX pobrane z umowy.
    </p>
    <p class="mb-3 text-center text-sm text-danger" x-show="overflow" x-cloak>Niektóre bloki są wyższe niż strona A4 i zostały ucięte w podglądzie.</p>
    <div x-ref="sheets" class="mx-auto flex w-fit flex-col gap-6" :style="background ? `--ct-bg: url('${background.url}')` : ''"></div>
  </div>
</main>
