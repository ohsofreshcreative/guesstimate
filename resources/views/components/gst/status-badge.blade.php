@props(['status'])

<span {{ $attributes->class([
  'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-sm font-medium',
  'bg-canvas text-ink-soft' => in_array($status->value, ['draft', 'cancelled'], true),
  'bg-accent-soft text-accent-strong' => $status->value === 'prepared',
  'bg-status-sent/10 text-status-sent' => $status->value === 'sent',
  'bg-risk-low-soft text-risk-low' => $status->value === 'accepted',
  'bg-risk-high-soft text-risk-high' => $status->value === 'rejected',
]) }}>
  <span @class([
    'size-1.5 rounded-full',
    'bg-status-draft' => in_array($status->value, ['draft', 'cancelled'], true),
    'bg-accent' => $status->value === 'prepared',
    'bg-status-sent' => $status->value === 'sent',
    'bg-status-accepted' => $status->value === 'accepted',
    'bg-status-rejected' => $status->value === 'rejected',
  ]) aria-hidden="true"></span>
  {{ $status->label() }}
</span>
