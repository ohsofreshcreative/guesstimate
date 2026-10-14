<div @class(['osf-pad osf-column', 'sheet:w-8/12' => $page['layout'] === 'full'])>
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  {{-- Nagłówek, kroki i informacja z żarówką = jedna grupa wyśrodkowana w pionie. --}}
  <div class="osf-center">
  @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit, 'class' => 'mt-5'])
  <div data-flow-body>
    <ol class="mt-5 space-y-3" data-split {{ $edit('next_steps') }}>
      @foreach ($doc['next_steps']['items'] as $i => $step)
        <li class="osf-t-base flex gap-3">
          <svg class="mt-0.5 size-6 shrink-0 {{ $chevrons[$i % count($chevrons)] }}" viewBox="0 0 40 40" fill="none" aria-hidden="true">
            <path d="M6 8l11 12L6 32" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M20 8l11 12-11 12" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span><strong>{{ $step['title'] }}</strong>@if ($step['note']) {{ $step['note'] }}@endif</span>
        </li>
      @endforeach
    </ol>
    @if ($doc['next_steps']['note'])
      <p class="osf-t-lg mt-10 flex max-w-lg gap-4 font-bold" {{ $edit('next_steps_note') }}>
        <svg class="size-9 shrink-0 text-orange" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M11 23h10M12 27h8M16 3a9 9 0 0 0-5 16.5V23h10v-3.5A9 9 0 0 0 16 3z" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>{!! \App\Guesstimate\Document\Markup::html($doc['next_steps']['note']) !!}</span>
      </p>
    @endif
  </div>
  </div>
</div>
