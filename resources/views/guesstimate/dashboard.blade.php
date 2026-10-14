@extends('guesstimate.layouts.app')

@section('title', $module['label'])

@section('content')
  <x-gst.page-header :title="$module['label']" :description="$module['description']">
    @if ($canCreate)
      <x-slot:actions>
        <x-gst.button :href="route('gst.offers.create')" icon="plus">Nowa oferta</x-gst.button>
      </x-slot:actions>
    @endif
  </x-gst.page-header>

  <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-5">
    <section class="min-w-0 rounded-panel border border-line bg-surface p-6 shadow-panel lg:col-span-2 2xl:col-span-3" aria-labelledby="gst-pipeline">
      <div class="flex items-baseline justify-between gap-4">
        <h2 id="gst-pipeline" class="font-semibold">Oferty według statusu</h2>
        <p class="text-ink-soft">Wszystkie: <a href="{{ route('gst.offers.index') }}" class="font-semibold text-ink hover:text-accent">{{ $totalOffers }}</a></p>
      </div>

      <dl class="mt-6 grid grid-cols-2 gap-y-6 sm:grid-cols-3">
        @foreach ($pipeline as $stage)
          <div class="min-w-0 pr-4">
            <dt class="flex items-center gap-2 text-ink-soft">
              <span @class([
                'size-2 shrink-0 rounded-full',
                'bg-status-draft' => in_array($stage['tone'], ['draft', 'cancelled'], true),
                'bg-accent' => $stage['tone'] === 'prepared',
                'bg-status-sent' => $stage['tone'] === 'sent',
                'bg-status-accepted' => $stage['tone'] === 'accepted',
                'bg-status-rejected' => $stage['tone'] === 'rejected',
              ]) aria-hidden="true"></span>
              {{ $stage['label'] }}
            </dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight"><a href="{{ $stage['url'] }}" class="hover:text-accent">{{ $stage['value'] }}</a></dd>
          </div>
        @endforeach
      </dl>
    </section>

    <section class="min-w-0 rounded-panel border border-line bg-surface p-6 shadow-panel">
      <h2 class="text-ink-soft">Łączna wartość ofert</h2>
      <p class="mt-1 text-2xl font-semibold tracking-tight">{{ $totalValue }}</p>
      <p class="mt-2 text-sm text-ink-muted">{{ $totalValueHint }}</p>
    </section>

    <section class="min-w-0 rounded-panel border border-line bg-surface p-6 shadow-panel">
      <h2 class="text-ink-soft">Skuteczność ofertowania</h2>
      <p class="mt-1 text-3xl font-semibold tracking-tight">{{ $winRate }}</p>
      <p class="mt-2 text-sm text-ink-muted">{{ $winRateHint }}</p>
    </section>
  </div>

  <section class="mt-8 rounded-panel border border-line bg-surface shadow-panel" aria-labelledby="gst-recent">
    <div class="flex items-center justify-between gap-4 border-b border-line px-6 py-4">
      <h2 id="gst-recent" class="font-semibold">Ostatnie oferty</h2>
      <a href="{{ route('gst.offers.index') }}" class="text-accent hover:underline">Całe archiwum</a>
    </div>

    @include('guesstimate.offers.partials.filters', ['authors' => []])
    @include('guesstimate.offers.partials.table', [
      'emptyTitle' => $totalOffers ? 'Brak ofert dla tych filtrów' : 'Nie ma jeszcze żadnych ofert',
      'emptyText' => $totalOffers ? 'Zmień filtry lub wyszukiwaną frazę.' : 'Utwórz pierwszą wycenę — kreator przeprowadzi Cię przez mapę strony, funkcjonalności i kalkulację.',
    ])
  </section>
  @include('guesstimate.offers.partials.trash-modal')
@endsection
