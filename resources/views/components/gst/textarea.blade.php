@props(['name', 'value' => null, 'error' => null, 'rows' => 4])

<textarea
  name="{{ $name }}"
  id="{{ $attributes->get('id', 'f-' . $name) }}"
  rows="{{ $rows }}"
  {{ $attributes->except('id')->class([
    'block w-full rounded-control border bg-surface px-3 py-2 text-ink focus:border-accent focus:ring-accent',
    'border-danger' => filled($error),
    'border-line-strong' => blank($error),
  ]) }}>{{ $value }}</textarea>
