@props(['name', 'label', 'checked' => false, 'value' => '1', 'hint' => null])

<label class="flex cursor-pointer items-start gap-3">
  <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked) {{ $attributes->merge(['class' => 'mt-0.5 size-4 rounded border-line-strong text-accent focus:ring-accent']) }}>
  <span>
    <span class="block text-ink">{{ $label }}</span>
    @if (filled($hint))
      <span class="block text-sm text-ink-muted">{{ $hint }}</span>
    @endif
  </span>
</label>
