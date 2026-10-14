@props(['page', 'pages', 'total'])

@if ($pages > 1)
  @php $link = fn ($p) => url()->current() . '?' . http_build_query(array_merge(request()->query(), ['page' => $p])); @endphp
  <nav class="flex items-center justify-between gap-4 border-t border-line px-6 py-3" aria-label="Stronicowanie">
    <p class="text-sm text-ink-muted">Strona {{ $page }} z {{ $pages }} · {{ $total }} wyników</p>
    <div class="flex gap-2">
      @if ($page > 1)
        <a href="{{ $link($page - 1) }}" class="rounded-control border border-line-strong px-3 py-1.5 hover:bg-canvas">Poprzednia</a>
      @endif
      @if ($page < $pages)
        <a href="{{ $link($page + 1) }}" class="rounded-control border border-line-strong px-3 py-1.5 hover:bg-canvas">Następna</a>
      @endif
    </div>
  </nav>
@endif
