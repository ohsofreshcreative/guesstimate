{{-- Status oferty w tabelach ofert (OfferDisplayStatus): kompaktowy badge. --}}
@props(['status'])

@php($v = $status->value)
<span {{ $attributes->class([
  'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium',
  'bg-canvas text-ink-soft' => $v === 'draft',
  'bg-canvas text-ink' => $v === 'prepared',
  'bg-status-sent/10 text-status-sent' => $v === 'sent',
  'bg-risk-low-soft text-risk-low' => $v === 'published',
  'bg-risk-mid-soft text-risk-mid' => $v === 'withdrawn',
  'bg-accent-soft text-accent-strong' => $v === 'accepted',
  'bg-risk-high-soft text-risk-high' => $v === 'rejected',
  'bg-danger-soft text-danger' => $v === 'cancelled',
]) }} data-offer-status="{{ $v }}">
  <span @class([
    'size-1.5 shrink-0 rounded-full',
    'bg-status-draft' => $v === 'draft',
    'bg-accent' => in_array($v, ['prepared', 'accepted'], true),
    'bg-status-sent' => $v === 'sent',
    'bg-risk-low' => $v === 'published',
    'bg-risk-mid' => $v === 'withdrawn',
    'bg-status-rejected' => $v === 'rejected',
    'bg-danger' => $v === 'cancelled',
  ]) aria-hidden="true"></span>
  {{ $status->label() }}
</span>
