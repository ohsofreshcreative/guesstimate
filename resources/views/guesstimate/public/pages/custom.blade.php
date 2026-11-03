{{-- Własna strona: nagłówek i treść z edytora (HTML czyszczony jak treści oferty). Nadmiar treści → strona „cd.”. --}}
<div @class(['osf-pad osf-column', 'sheet:w-8/12' => $page['layout'] === 'full'])>
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  <div class="osf-center">
    @if ($page['heading'] || $editor)
      @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit, 'class' => 'mt-5'])
    @endif
    <div class="osf-prose osf-t-base mt-5" data-flow-body {{ $edit('html') }}>{!! $page['html'] ?? '' !!}</div>
  </div>
</div>
