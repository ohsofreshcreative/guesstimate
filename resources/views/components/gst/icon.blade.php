@props(['name'])

<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
  @switch($name)
    @case('dashboard')
      <rect x="3.5" y="3.5" width="7" height="8" rx="1.5" />
      <rect x="13.5" y="3.5" width="7" height="5" rx="1.5" />
      <rect x="13.5" y="11.5" width="7" height="9" rx="1.5" />
      <rect x="3.5" y="14.5" width="7" height="6" rx="1.5" />
      @break
    @case('offers')
      <path d="M7 3.5h7l4.5 4.5v11a1.5 1.5 0 0 1-1.5 1.5H7A1.5 1.5 0 0 1 5.5 19V5A1.5 1.5 0 0 1 7 3.5Z" />
      <path d="M14 3.5V8h4.5" />
      <path d="M8.5 12.5h7M8.5 16h4.5" />
      @break
    @case('clients')
      <path d="M4 20.5V6a1.5 1.5 0 0 1 1.5-1.5h7A1.5 1.5 0 0 1 14 6v14.5" />
      <path d="M14 10h4.5a1.5 1.5 0 0 1 1.5 1.5v9" />
      <path d="M2.5 20.5h19M7.5 8.5h3M7.5 12h3M7.5 15.5h3" />
      @break
    @case('features')
      <path d="M4.5 7h9M17.5 7h2M4.5 12h3M11.5 12h8M4.5 17h7M15.5 17h4" />
      <circle cx="15.5" cy="7" r="2" />
      <circle cx="9.5" cy="12" r="2" />
      <circle cx="13.5" cy="17" r="2" />
      @break
    @case('integrations')
      <path d="M9 7.5 7 5.5a3 3 0 0 0-4.24 4.24l2 2a3 3 0 0 0 4.24 0" />
      <path d="M15 16.5l2 2a3 3 0 0 0 4.24-4.24l-2-2a3 3 0 0 0-4.24 0" />
      <path d="M8.5 15.5l7-7" />
      @break
    @case('settings')
      <path d="M12 8.75a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5Z" />
      <path d="M19.4 13.5a7.6 7.6 0 0 0 0-3l2-1.5-2-3.5-2.4 1a7.5 7.5 0 0 0-2.6-1.5L14 2.5h-4l-.4 2.5A7.5 7.5 0 0 0 7 6.5l-2.4-1-2 3.5 2 1.5a7.6 7.6 0 0 0 0 3l-2 1.5 2 3.5 2.4-1a7.5 7.5 0 0 0 2.6 1.5l.4 2.5h4l.4-2.5a7.5 7.5 0 0 0 2.6-1.5l2.4 1 2-3.5-2-1.5Z" />
      @break
    @case('plus')
      <path d="M12 5v14M5 12h14" />
      @break
    @case('menu')
      <path d="M4 7h16M4 12h16M4 17h16" />
      @break
    @case('close')
      <path d="M6 6l12 12M18 6 6 18" />
      @break
    @case('logout')
      <path d="M14.5 4.5h3a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5h-3" />
      <path d="M10 16.5 5.5 12 10 7.5M5.5 12H15" />
      @break
    @case('lock')
      <rect x="5" y="10.5" width="14" height="10" rx="1.5" />
      <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3" />
      @break
    @default
      <circle cx="12" cy="12" r="8" />
  @endswitch
</svg>
