{{-- Nagłówek strony (Markup: **fragment** = pomarańczowy). $page, $edit, $class. --}}
<h2 class="osf-t-3xl font-bold tracking-tight {{ $class ?? '' }}" data-flow-title {{ $edit('heading') }}>{!! \App\Guesstimate\Document\Markup::html($page['heading']) !!}</h2>@if (\App\Guesstimate\Document\FieldLayout::holdsHeadingSpace($page))<div class="osf-t-3xl font-bold tracking-tight {{ $class ?? '' }} osf-field-spacer" aria-hidden="true">{!! \App\Guesstimate\Document\Markup::html($page['heading']) !!}</div>@endif

