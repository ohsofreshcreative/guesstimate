{{-- Własna strona „Lista kroków”: wstęp i kroki jak w „Kolejnych krokach”, z pozycjami tej strony (nie z oferty). --}}
<div @class(['osf-pad osf-column', 'sheet:w-8/12' => $page['layout'] === 'full'])>
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  <div class="osf-center">
    @if ($page['heading'] || $editor)
      @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit, 'class' => 'mt-5'])
    @endif
    <div data-flow-body>
      @if (! empty($page['html']))
        <div class="osf-prose osf-t-sm mt-3" {{ $edit('html') }}>{!! $page['html'] !!}</div>
      @endif
      @if ($page['items'] ?? [])
        <ol class="mt-5 space-y-3" data-split {{ $edit('items') }}>
          @foreach ($page['items'] as $i => $step)
            <li class="osf-t-base flex gap-3">
              <svg class="mt-0.5 size-6 shrink-0 {{ $chevrons[$i % count($chevrons)] }}" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                <path d="M6 8l11 12L6 32" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M20 8l11 12-11 12" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span><strong>{{ $step['title'] }}</strong>@if ($step['note'] !== '') {{ $step['note'] }}@endif</span>
            </li>
          @endforeach
        </ol>
      @elseif ($editor)
        <p class="osf-t-sm mt-5 text-paper-muted" {{ $edit('items') }}>Dodaj kroki w panelu.</p>
      @endif
    </div>
  </div>
</div>
