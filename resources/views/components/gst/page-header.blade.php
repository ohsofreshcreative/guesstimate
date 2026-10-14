@props(['title', 'description' => null])

<header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
  <div>
    <h1 class="text-3xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
    @if (filled($description))
      <p class="mt-2 max-w-prose text-ink-soft">{{ $description }}</p>
    @endif
  </div>

  @isset($actions)
    <div class="flex shrink-0 items-center gap-3">
      {{ $actions }}
    </div>
  @endisset
</header>
