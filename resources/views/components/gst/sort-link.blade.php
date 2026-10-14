@props(['column', 'label', 'filters'])

@php
  $active = $filters['sort'] === $column;
  $dir = $active && $filters['dir'] === 'asc' ? 'desc' : 'asc';
  $query = array_merge(request()->query(), ['sort' => $column, 'dir' => $dir, 'page' => 1]);
@endphp

<a href="{{ url()->current() . '?' . http_build_query($query) }}" class="group inline-flex items-center gap-1 hover:text-ink" @if ($active) aria-sort="{{ $filters['dir'] === 'asc' ? 'ascending' : 'descending' }}" @endif>
  {{ $label }}
  <span @class(['text-ink-muted', 'opacity-0 group-hover:opacity-100' => ! $active]) aria-hidden="true">{{ $active && $filters['dir'] === 'asc' ? '↑' : '↓' }}</span>
</a>
