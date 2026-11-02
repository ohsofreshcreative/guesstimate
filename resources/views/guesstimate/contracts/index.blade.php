@extends('guesstimate.layouts.app')

@section('title', 'Umowy')

@section('content')
  <x-gst.page-header title="Umowy" description="Umowy przygotowane z ofert. Nic stąd nie jest wysyłane ani podpisywane.">
    @if ($canManageTemplates)
      <x-slot:actions>
        <x-gst.button :href="route('gst.contract-templates.index')" variant="secondary">Szablony umów</x-gst.button>
      </x-slot:actions>
    @endif
  </x-gst.page-header>

  <section class="rounded-panel border border-line bg-surface shadow-panel">
    <form method="get" action="{{ url()->current() }}" class="grid gap-3 border-b border-line px-6 py-4 md:grid-cols-2 xl:grid-cols-5" role="search">
      <div class="md:col-span-2">
        <label for="f-q" class="sr-only">Szukaj</label>
        <x-gst.input name="q" id="f-q" :value="$filters['q']" placeholder="Szukaj: nazwa, numer oferty lub klient" />
      </div>
      <div>
        <label for="f-status" class="sr-only">Status</label>
        <x-gst.select name="status" id="f-status" :options="$statuses" :value="$filters['status']" placeholder="Wszystkie statusy" />
      </div>
      @if ($authorFilter)
        <div>
          <label for="f-author" class="sr-only">Autor</label>
          <x-gst.select name="author" id="f-author" :options="$authorFilter" :value="$filters['author'] ?: ''" placeholder="Wszyscy autorzy" />
        </div>
      @endif
      <div class="flex gap-2">
        <x-gst.button type="submit" variant="secondary">Filtruj</x-gst.button>
        @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['author'])
          <x-gst.button :href="url()->current()" variant="ghost">Wyczyść</x-gst.button>
        @endif
      </div>
    </form>

    @if ($rows->isEmpty())
      @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['author'])
        <p class="px-6 py-8 text-center text-ink-soft">Żadna umowa nie pasuje do filtrów.</p>
      @else
        <x-gst.empty-state icon="contracts" title="Nie ma jeszcze umów" text="Umowę przygotujesz na karcie oferty, w sekcji „Umowy”.">
          <x-gst.button :href="route('gst.offers.index')">Przejdź do ofert</x-gst.button>
        </x-gst.empty-state>
      @endif
    @else
      <div class="relative overflow-x-auto">
        <table class="w-full min-w-max text-left">
          <thead class="text-sm text-ink-soft">
            <tr class="border-b border-line">
              <th class="px-6 py-2 font-medium">Umowa</th>
              <th class="px-4 py-2 font-medium">Klient</th>
              <th class="px-4 py-2 font-medium">Oferta</th>
              <th class="px-4 py-2 font-medium">Status</th>
              <th class="px-4 py-2 font-medium">Autor</th>
              <th class="px-6 py-2 font-medium">Zmieniono</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            @foreach ($rows as $contract)
              <tr>
                <td class="px-6 py-2.5"><a href="{{ route('gst.contracts.show', $contract) }}" class="font-medium hover:underline">{{ $contract->title }}</a></td>
                <td class="px-4 py-2.5">{{ $contract->client?->company_name }}</td>
                <td class="px-4 py-2.5">{{ $contract->offer?->number }}</td>
                <td class="px-4 py-2.5">
                  @if ($contract->currentVersion)
                    <x-gst.status-badge :status="$contract->currentVersion->status" />
                    <span class="text-sm text-ink-muted">v{{ $contract->currentVersion->version_no }}</span>
                  @endif
                </td>
                <td class="px-4 py-2.5">{{ $authors[$contract->author_id] ?? '' }}</td>
                <td class="px-6 py-2.5">{{ wp_date('j.m.Y, H:i', $contract->currentVersion?->updated_at?->getTimestamp()) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
    <x-gst.pagination :page="$result['page']" :pages="$result['pages']" :total="$result['total']" />
  </section>
@endsection
