{{-- Nagłówek strony (Markup: **fragment** = pomarańczowy). $page, $edit, $class. --}}
<h2 class="osf-t-3xl font-bold tracking-tight {{ $class ?? '' }}" data-flow-title {{ $edit('heading') }}>{!! \App\Guesstimate\Document\Markup::html($page['heading']) !!}</h2>
