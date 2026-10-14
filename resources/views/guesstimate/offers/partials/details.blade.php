{{-- Podsumowanie wersji: model budżetowy albo poprzedni model (wersje historyczne). --}}
@if ($summary['budget'])
  @include('guesstimate.offers.partials.details-budget', ['summary' => $summary])
@else
  @include('guesstimate.offers.partials.details-legacy', ['summary' => $summary])
@endif
