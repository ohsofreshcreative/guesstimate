@props([
  'href' => null,
  'variant' => 'primary',
  'icon' => null,
  'disabled' => false,
])

@php
  $variants = [
    'primary' => 'bg-accent text-white hover:bg-accent-strong',
    'secondary' => 'border border-line-strong bg-surface text-ink hover:bg-canvas',
    'ghost' => 'text-ink-soft hover:bg-canvas hover:text-ink',
  ];

  $classes = 'inline-flex items-center justify-center gap-2 rounded-control px-4 py-2 font-medium transition-colors '
    . ($variants[$variant] ?? $variants['primary'])
    . ($disabled ? ' cursor-not-allowed opacity-50' : '');
@endphp

@if ($href && ! $disabled)
  <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)<x-gst.icon :name="$icon" class="size-4" />@endif
    {{ $slot }}
  </a>
@else
  <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }} @disabled($disabled)>
    @if ($icon)<x-gst.icon :name="$icon" class="size-4" />@endif
    {{ $slot }}
  </button>
@endif
