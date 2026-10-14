@extends('guesstimate.layouts.app')

@section('title', 'Oferty')

@section('content')
  <x-gst.page-header title="Oferty" :description="$clientName ? 'Oferty klienta ' . $clientName : 'Archiwum wycen z wersjami, statusami i historią zmian.'">
    @if ($canCreate)
      <x-slot:actions>
        <x-gst.button :href="route('gst.offers.create')" icon="plus">Nowa oferta</x-gst.button>
      </x-slot:actions>
    @endif
  </x-gst.page-header>

  @include('guesstimate.offers.partials.tabs', ['tab' => 'archive'])

  <section class="rounded-panel border border-line bg-surface shadow-panel">
    @include('guesstimate.offers.partials.filters', ['showDates' => true])
    @include('guesstimate.offers.partials.table')
    <x-gst.pagination :page="$result['page']" :pages="$result['pages']" :total="$result['total']" />
  </section>

  @include('guesstimate.offers.partials.trash-modal')
@endsection
