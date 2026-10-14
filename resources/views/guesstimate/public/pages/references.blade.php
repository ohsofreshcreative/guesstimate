<div class="osf-pad osf-column sheet:pr-24">
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  <div class="osf-center">
  @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit, 'class' => 'mt-5'])
  <div data-flow-body>
    @if ($doc['references']['intro'])
      <p class="osf-t-sm mt-3 max-w-xl" {{ $edit('references_intro') }}>{!! \App\Guesstimate\Document\Markup::html($doc['references']['intro'], 'font-bold') !!}</p>
    @endif
    <div class="mt-6 grid osf-gap-lg md:grid-cols-3 sheet:grid-cols-3" data-split {{ $edit('references') }}>
      @foreach ($doc['references']['items'] as $ref)
        <article class="min-w-0">
          @if ($ref['image'])
            <img src="{{ $ref['image']['url'] }}" @if ($ref['image']['srcset']) srcset="{{ $ref['image']['srcset'] }}" sizes="(min-width: 768px) 400px, 100vw" @endif
              width="{{ $ref['image']['width'] }}" height="{{ $ref['image']['height'] }}" alt="{{ $ref['image']['alt'] ?: 'Realizacja: ' . $ref['name'] }}"
              loading="lazy" decoding="async" class="aspect-video w-full rounded-lg object-cover">
          @else
            <div class="flex aspect-video w-full items-center justify-center rounded-lg bg-night-raised"><span class="osf-t-lg font-bold text-paper-soft">{{ $ref['name'] }}</span></div>
          @endif
          <h3 class="osf-t-sm mt-3 font-bold text-paper-soft">{{ $ref['name'] }}</h3>
          <dl class="mt-2 space-y-2">
            @foreach ($ref['metrics'] as $metric)
              <div>
                <dt class="osf-t-xl flex items-center gap-2 font-bold">
                  <svg class="size-6 shrink-0 text-orange" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M3 29h26M7 25v-6M13 25v-9M19 25v-12M25 25V8M5 15l7-6 6 3 9-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  {{ $metric['value'] }}
                </dt>
                <dd class="osf-t-xs font-bold">{{ $metric['label'] }}</dd>
              </div>
            @endforeach
          </dl>
          @if ($ref['description'])<p class="osf-t-xs mt-3 text-paper-soft">{{ $ref['description'] }}</p>@endif
          @if ($ref['url'])
            <a href="{{ $ref['url'] }}" target="_blank" rel="noopener noreferrer" class="osf-t-sm mt-3 inline-block font-bold text-orange underline underline-offset-4">{{ $ref['url_label'] }}</a>
          @endif
        </article>
      @endforeach
    </div>
  </div>
  </div>
</div>
<p class="osf-edge-label" aria-hidden="true">W kooperacji siła</p>
