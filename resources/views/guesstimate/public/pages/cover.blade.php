@php $withMedia = $page['layout'] === 'text_image'; @endphp
<div class="osf-pad flex flex-1 flex-col">
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  <div class="flex flex-1 items-center py-8 sheet:py-0">
    <div @class(['sheet:w-8/12' => ! $withMedia, 'w-full' => $withMedia])>
      @if ($doc['cover']['lead'] || $editor)
        <p @class(['osf-t-sm mb-4 max-w-md text-paper sheet:mb-5', 'sheet:pl-14' => ! $withMedia]) {{ $edit('cover_lead') }}>{{ $doc['cover']['lead'] ?: ($editor ? 'Krótki opis na okładce' : '') }}</p>
      @endif
      <h1 class="osf-t-display font-bold tracking-tight" {{ $edit('cover_heading') }}>{!! \App\Guesstimate\Document\Markup::html($doc['cover']['heading']) !!}</h1>
    </div>
  </div>
  <div class="grid items-end gap-6 sheet:grid-cols-12">
    <p @class(['osf-t-lg font-bold', 'sheet:col-span-6' => ! $withMedia, 'sheet:col-span-12' => $withMedia])>
      @if ($doc['website'])<a href="https://{{ preg_replace('#^https?://#', '', $doc['website']) }}" class="underline decoration-2 underline-offset-4" rel="noopener" target="_blank">{{ $doc['website'] }}</a>@endif
    </p>
    <div @class(['sheet:col-span-6' => ! $withMedia, 'sheet:col-span-12' => $withMedia])>
      @if ($offer['client'])
        <p class="osf-t-xl font-bold">oferta dla:</p>
        <p class="osf-t-xl font-bold text-orange">{{ $offer['client'] }}</p>
      @endif
      <p class="osf-t-xs mt-2 text-paper-soft">{{ $offer['title'] }} · {{ $offer['number'] }}</p>
      @if ($doc['cover']['date'])
        <p class="osf-t-lg mt-3 font-bold">z dnia: <span class="text-orange">{{ $doc['cover']['date'] }}</span></p>
      @endif
    </div>
  </div>
</div>
