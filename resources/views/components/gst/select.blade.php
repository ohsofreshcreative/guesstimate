@props(['name', 'options' => [], 'value' => null, 'error' => null, 'placeholder' => null])

@php $hasWidth = (bool) preg_match('/(^|\s)w-/', (string) $attributes->get('class', '')); @endphp

<select
  name="{{ $name }}"
  id="{{ $attributes->get('id', 'f-' . str_replace(['[', ']', '.'], ['-', '', '-'], $name)) }}"
  {{ $attributes->except('id')->class([
    'block rounded-control border bg-surface py-2 pl-3 pr-9 text-ink focus:border-accent focus:ring-accent',
    'w-full' => ! $hasWidth,
    'border-danger' => filled($error),
    'border-line-strong' => blank($error),
  ]) }}>
  @if ($placeholder !== null)
    <option value="">{{ $placeholder }}</option>
  @endif
  @foreach ($options as $optionValue => $optionLabel)
    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
  @endforeach
</select>
