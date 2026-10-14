@props(['id', 'title', 'open' => false, 'size' => 'md'])

<div
  x-data="{ open: @js((bool) $open) }"
  x-on:open-modal.window="if ($event.detail === @js($id)) open = true"
  x-on:close-modal.window="if ($event.detail === @js($id)) open = false"
  x-on:keydown.escape.window="open = false"
  x-show="open"
  x-cloak
  class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10"
  role="dialog"
  aria-modal="true"
  aria-labelledby="{{ $id }}-title">
  <div class="fixed inset-0 bg-ink/50" x-on:click="open = false" x-show="open" x-transition.opacity></div>

  <div
    x-show="open"
    x-transition
    x-effect="if (open) $nextTick(() => $el.querySelector('input:not([type=hidden]), select, textarea')?.focus())"
    @class([
      'relative w-full rounded-panel bg-surface shadow-overlay',
      'max-w-lg' => $size === 'md',
      'max-w-2xl' => $size === 'lg',
      'max-w-4xl' => $size === 'xl',
    ])>
    <div class="flex items-center justify-between gap-4 border-b border-line px-6 py-4">
      <h2 id="{{ $id }}-title" class="text-lg font-semibold">{{ $title }}</h2>
      <button type="button" class="rounded-control p-1.5 text-ink-muted hover:bg-canvas hover:text-ink" x-on:click="open = false">
        <x-gst.icon name="close" />
        <span class="sr-only">Zamknij</span>
      </button>
    </div>
    <div class="px-6 py-5">
      {{ $slot }}
    </div>
  </div>
</div>
