@props(['level'])

<span @class([
  'inline-block size-2.5 shrink-0 rounded-full',
  'bg-risk-low' => $level === 'low',
  'bg-risk-mid' => $level === 'mid',
  'bg-risk-high' => $level === 'high',
]) aria-hidden="true"></span>
