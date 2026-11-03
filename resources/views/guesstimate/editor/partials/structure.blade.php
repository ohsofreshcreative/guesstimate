{{-- Strony dokumentu: kolejność, kopie i własne strony. Strony gotowe można przesuwać i duplikować, dodatkowe także usuwać. --}}
<div class="{{ $section }}">
  <div class="flex items-center justify-between gap-2">
    <h2 class="font-semibold">Strony dokumentu</h2>
    <span class="text-xs text-ink-muted" x-text="`dodatkowe: ${extraCount}/{{ $config['maxExtraPages'] }}`"></span>
  </div>
  <p class="mt-1 text-xs text-ink-muted">Kolejność stron u klienta. Strony ukryte (wyłączone albo bez treści) klient nie zobaczy.</p>
  <p class="mt-1 text-xs text-danger" x-text="fieldError('document.page_order')"></p>

  <ol class="mt-3 space-y-1.5" data-page-order>
    <template x-for="(item, index) in pageList" :key="item.key">
      <li class="flex items-center gap-1 rounded-control border p-1.5" :class="selected === item.key ? 'border-accent bg-accent-soft' : 'border-line'" :data-page-item="item.key">
        <button type="button" class="min-w-0 flex-1 truncate px-1.5 text-left text-sm" x-on:click="select(item.key); tab = 'page'">
          <span class="text-ink-muted" x-text="`${index + 1}.`"></span>
          <span x-text="item.label"></span>
          <span class="text-xs text-ink-muted" x-show="hiddenReason(item.key)">· ukryta</span>
        </button>
        <template x-if="editable">
          <span class="flex shrink-0 items-center">
            <button type="button" class="rounded-control px-1.5 text-ink-muted hover:bg-canvas disabled:opacity-30" x-on:click="movePage(item.key, -1)" :disabled="index === 0" aria-label="Przenieś wyżej">↑</button>
            <button type="button" class="rounded-control px-1.5 text-ink-muted hover:bg-canvas disabled:opacity-30" x-on:click="movePage(item.key, 1)" :disabled="index === pageList.length - 1" aria-label="Przenieś niżej">↓</button>
            <button type="button" class="rounded-control px-1.5 text-xs text-ink-muted hover:bg-canvas disabled:opacity-30" x-show="item.copyable" x-on:click="duplicatePage(item.key)" :disabled="extraCount >= {{ $config['maxExtraPages'] }}" :aria-label="`Duplikuj stronę ${item.label}`">Duplikuj</button>
            <button type="button" class="rounded-control px-1.5 text-xs text-danger hover:bg-danger-soft" x-show="item.extra" x-on:click="removePage(item.key)" :aria-label="`Usuń stronę ${item.label}`">✕</button>
          </span>
        </template>
      </li>
    </template>
  </ol>

  @if ($config['editable'])
    <button type="button" class="{{ $secondary }} mt-3" x-on:click="addCustomPage(); tab = 'page'" :disabled="extraCount >= {{ $config['maxExtraPages'] }}" data-page-add>+ Dodaj własną stronę</button>
    <p class="mt-1 text-xs text-ink-muted">Nowa strona pojawi się za wybraną. Treść wpiszesz w edytorze tekstu, wygląd ustawisz jak na innych stronach.</p>
  @endif
</div>
