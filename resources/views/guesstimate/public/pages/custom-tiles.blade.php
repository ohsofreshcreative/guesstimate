{{-- Własna strona „Kafelki”: wstęp i kafelki jak w „Naszym rozwiązaniu”, z pozycjami tej strony (nie z oferty). --}}
@php
  $tiles = $page['layout'] === 'tiles';
@endphp
<div class="osf-pad osf-column">
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  <div class="osf-center">
    @if ($page['heading'] || $editor)
      @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit, 'class' => 'mt-5'])
    @endif
    @if (! empty($page['html']))
      <div class="osf-prose osf-t-sm mt-3 max-w-4xl" {{ $edit('html') }}>{!! $page['html'] !!}</div>
    @endif
    @if ($page['items'] ?? [])
      <ol @class(['mt-6 grid osf-gap-lg', 'md:grid-cols-2 sheet:grid-cols-2' => ! $tiles, 'md:grid-cols-2 sheet:grid-cols-3' => $tiles]) data-flow-body data-split {{ $edit('items') }}>
        @foreach ($page['items'] as $i => $item)
          <li @class(['flex min-w-0 osf-gap', 'flex-col rounded-lg bg-night-raised/80 p-5 sheet:p-6' => $tiles])>
            <svg class="mt-1 size-7 shrink-0 {{ $chevrons[$i % count($chevrons)] }}" viewBox="0 0 40 40" fill="none" aria-hidden="true">
              <path d="M6 8l11 12L6 32" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M20 8l11 12-11 12" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div class="min-w-0">
              <h3 class="osf-t-base font-bold">{{ $item['title'] }}</h3>
              @if ($item['text'] !== '')
                <p class="osf-t-sm mt-1 whitespace-pre-line text-paper-soft">{{ $item['text'] }}</p>
              @endif
            </div>
          </li>
        @endforeach
      </ol>
    @elseif ($editor)
      <p class="osf-t-sm mt-6 text-paper-muted" {{ $edit('items') }}>Dodaj kafelki w panelu.</p>
    @endif
  </div>
</div>
