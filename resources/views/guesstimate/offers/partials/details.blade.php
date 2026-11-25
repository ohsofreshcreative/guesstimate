{{-- Podsumowanie wersji: pakiety support, model budżetowy albo poprzedni model (wersje historyczne). --}}
@if (! empty($summary['support']))
  @include('guesstimate.offers.partials.details-support', ['summary' => $summary])
@elseif ($summary['budget'])
  @include('guesstimate.offers.partials.details-budget', ['summary' => $summary])
@else
  @include('guesstimate.offers.partials.details-legacy', ['summary' => $summary])
@endif
