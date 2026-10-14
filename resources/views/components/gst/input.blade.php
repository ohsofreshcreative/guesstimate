@props(['name', 'value' => null, 'type' => 'text', 'error' => null, 'suffix' => null])

@php
  $id = $attributes->get('id', 'f-' . str_replace(['[', ']', '.'], ['-', '', '-'], $name));
  $hasWidth = (bool) preg_match('/(^|\s)w-/', (string) $attributes->get('class', ''));
@endphp

<div @class(['relative flex items-stretch', 'w-full' => ! $hasWidth])>
  <input
    type="{{ $type }}"
    name="{{ $name }}"
    id="{{ $id }}"
    value="{{ $value }}"
    @if (filled($error)) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
    {{ $attributes->except('id')->class([
      'block rounded-control border bg-surface px-3 py-2 text-ink shadow-none placeholder:text-ink-muted focus:border-accent focus:ring-accent',
      'w-full' => ! $hasWidth,
      'border-danger' => filled($error),
      'border-line-strong' => blank($error),
      'pr-14' => filled($suffix),
    ]) }}>
  @if (filled($suffix))
    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-muted">{{ $suffix }}</span>
  @endif
</div>
