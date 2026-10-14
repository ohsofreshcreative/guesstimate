@props(['label', 'for' => null, 'error' => null, 'hint' => null, 'required' => false])

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
  <label @if ($for) for="{{ $for }}" @endif class="mb-1.5 block font-medium text-ink">
    {{ $label }}
    @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
  </label>
  {{ $slot }}
  @if (filled($error))
    <p class="mt-1.5 text-sm text-danger" @if ($for) id="{{ $for }}-error" @endif>{{ $error }}</p>
  @elseif (filled($hint))
    <p class="mt-1.5 text-sm text-ink-muted">{{ $hint }}</p>
  @endif
</div>
