@props(['icon' => 'offers', 'title', 'text' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-14 text-center']) }}>
  <span class="flex size-12 items-center justify-center rounded-full bg-canvas text-ink-muted">
    <x-gst.icon :name="$icon" class="size-6" />
  </span>
  <p class="mt-4 text-lg font-semibold text-ink">{{ $title }}</p>
  @if (filled($text))
    <p class="mt-1 max-w-md text-ink-soft">{{ $text }}</p>
  @endif
  @if (! $slot->isEmpty())
    <div class="mt-6">
      {{ $slot }}
    </div>
  @endif
</div>
