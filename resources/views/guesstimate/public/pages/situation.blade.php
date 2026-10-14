{{-- Strona zawsze mieści się na jednym arkuszu: zamiast stron „cd.” skrypt dopasowuje typografię (data-fit). --}}
<div class="osf-pad osf-column">
  <div class="osf-center">
    @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit])
    <div class="osf-prose osf-t-base osf-fit-text mt-4" data-flow-body {{ $edit('situation') }}>{!! $offer['content']['situation'] !!}</div>
  </div>
</div>
