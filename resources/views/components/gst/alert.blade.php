@props(['type' => 'info'])

<div {{ $attributes->class([
  'flex items-start gap-3 rounded-panel border px-4 py-3',
  'border-accent/30 bg-accent-soft text-accent-strong' => $type === 'success',
  'border-danger/30 bg-danger-soft text-danger' => $type === 'error',
  'border-risk-mid/30 bg-risk-mid-soft text-ink' => $type === 'warning',
  'border-line bg-surface text-ink-soft' => $type === 'info',
]) }} role="{{ $type === 'error' ? 'alert' : 'status' }}">
  <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
