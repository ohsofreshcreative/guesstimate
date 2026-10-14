@php
  $blocks = array_values(array_filter($offer['content']['blocks'], fn ($b) => $b['title'] !== ''));
  $tiles = $page['layout'] === 'tiles';
  $columns = $page['layout'] === 'full';
@endphp
<div class="osf-pad osf-column">
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  <div class="osf-center">
  @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit, 'class' => 'mt-5'])
  <ol @class(['mt-6 grid osf-gap-lg', 'md:grid-cols-2 sheet:grid-cols-2' => $columns, 'md:grid-cols-2 sheet:grid-cols-3' => $tiles]) data-flow-body data-split>
    @foreach ($blocks as $i => $block)
      <li @class(['flex min-w-0 osf-gap', 'flex-col rounded-lg bg-night-raised/80 p-5 sheet:p-6' => $tiles]) {{ $edit('block:' . $i) }}>
        <svg class="mt-1 size-7 shrink-0 {{ $chevrons[$i % count($chevrons)] }}" viewBox="0 0 40 40" fill="none" aria-hidden="true">
          <path d="M6 8l11 12L6 32" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M20 8l11 12-11 12" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <div class="min-w-0">
          <h3 class="osf-t-base font-bold">{{ $block['title'] }}</h3>
          <div class="osf-prose osf-t-sm mt-1">{!! $block['html'] !!}</div>
        </div>
      </li>
    @endforeach
  </ol>
  </div>
</div>
