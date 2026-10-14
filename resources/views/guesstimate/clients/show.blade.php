@extends('guesstimate.layouts.app')

@section('title', $client->company_name)

@section('content')
  <div class="mb-2"><a href="{{ route('gst.clients.index') }}" class="text-ink-soft hover:text-ink">← Klienci</a></div>

  <x-gst.page-header :title="$client->company_name" :description="$client->website_url ? preg_replace('#^https?://#', '', $client->website_url) : null">
    <x-slot:actions>
      <x-gst.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'client-edit')">Edytuj dane</x-gst.button>
      <x-gst.button :href="route('gst.offers.create', ['klient' => $client->id])" icon="plus">Nowa oferta</x-gst.button>
    </x-slot:actions>
  </x-gst.page-header>

  <div class="grid gap-6 xl:grid-cols-3">
    <section class="min-w-0 rounded-panel border border-line bg-surface p-6 shadow-panel">
      <h2 class="font-semibold">Dane klienta</h2>
      <dl class="mt-4 space-y-3">
        <div><dt class="text-sm text-ink-muted">Osoba kontaktowa</dt><dd>{{ $client->contact_name ?: '—' }}</dd></div>
        <div><dt class="text-sm text-ink-muted">E-mail</dt><dd>@if ($client->email)<a href="mailto:{{ $client->email }}" class="text-accent hover:underline">{{ $client->email }}</a>@else — @endif</dd></div>
        <div><dt class="text-sm text-ink-muted">Telefon</dt><dd>{{ $client->phone ?: '—' }}</dd></div>
        <div><dt class="text-sm text-ink-muted">Strona WWW</dt><dd class="break-all">@if ($client->website_url)<a href="{{ $client->website_url }}" class="text-accent hover:underline" target="_blank" rel="noopener">{{ $client->website_url }}</a>@else — @endif</dd></div>
        <div><dt class="text-sm text-ink-muted">NIP</dt><dd>{{ $client->nip ?: '—' }}</dd></div>
        @if ($client->notes)
          <div><dt class="text-sm text-ink-muted">Notatki</dt><dd class="whitespace-pre-line">{{ $client->notes }}</dd></div>
        @endif
      </dl>
      <div class="mt-6 border-t border-line pt-4">
        @if ($offersAll === 0)
          <button type="button" class="text-sm text-danger hover:underline" data-delete-client="{{ $client->id }}"
            x-data x-on:click="$dispatch('gst-client-delete', { url: @js(route('gst.clients.destroy', $client)), name: @js($client->company_name), from: 'show' })">Usuń klienta</button>
        @else
          <p class="text-sm text-ink-muted" data-delete-blocked>Usunięcie niedostępne: klient jest powiązany z ofertami ({{ $offersAll }}{{ $offersTrashed ? ', w tym w koszu: ' . $offersTrashed : '' }}). Oferty i ich historia muszą zachować klienta.</p>
        @endif
      </div>
    </section>

    <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel xl:col-span-2" aria-labelledby="gst-client-offers">
      <h2 id="gst-client-offers" class="border-b border-line px-6 py-4 font-semibold">Historia wycen</h2>
      @if (empty($offers))
        <x-gst.empty-state icon="offers" title="Brak wycen dla tego klienta" text="Przygotuj pierwszą ofertę — klient zostanie wybrany automatycznie.">
          <x-gst.button :href="route('gst.offers.create', ['klient' => $client->id])" icon="plus">Nowa oferta</x-gst.button>
        </x-gst.empty-state>
      @else
        <div class="relative overflow-x-auto">
          <table class="w-full min-w-max text-left">
            <thead class="border-b border-line text-sm text-ink-soft">
              <tr><th class="px-6 py-3 font-medium">Numer</th><th class="px-4 py-3 font-medium">Projekt</th><th class="px-4 py-3 text-right font-medium">Wycena od–do</th><th class="px-4 py-3 font-medium">Status</th><th class="px-6 py-3 font-medium">Utworzono</th></tr>
            </thead>
            <tbody class="divide-y divide-line">
              @foreach ($offers as $offer)
                <tr class="hover:bg-canvas/60">
                  <td class="px-6 py-3 font-medium"><a href="{{ route('gst.offers.show', $offer['id']) }}" class="hover:text-accent">{{ $offer['number'] }}</a>@if ($offer['version'] > 1) <span class="text-sm text-ink-muted">v{{ $offer['version'] }}</span>@endif</td>
                  <td class="px-4 py-3">{{ $offer['project'] }}</td>
                  <td class="whitespace-nowrap px-4 py-3 text-right">{{ $offer['range'] }}</td>
                  <td class="px-4 py-3"><x-gst.status-badge :status="$offer['status']" /></td>
                  <td class="px-6 py-3 text-ink-soft">{{ $offer['created'] }} · {{ $offer['author'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </section>
  </div>

  <x-gst.modal id="client-edit" title="Edytuj dane klienta" size="lg" :open="($openModal ?? null) === 'client-edit'">
    <form method="post" action="{{ route('gst.clients.update', $client) }}" class="space-y-6" novalidate>
      <x-gst.csrf />
      @include('guesstimate.clients.partials.form', ['client' => $client, 'prefix' => 'edit-'])
      <div class="flex justify-end gap-2">
        <x-gst.button variant="ghost" x-on:click="$dispatch('close-modal', 'client-edit')">Anuluj</x-gst.button>
        <x-gst.button type="submit">Zapisz zmiany</x-gst.button>
      </div>
    </form>
  </x-gst.modal>
  @include('guesstimate.clients.partials.delete-modal')
@endsection
