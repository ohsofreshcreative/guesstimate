@extends('guesstimate.layouts.app')

@section('title', 'Kosz ofert')

@section('content')
  <x-gst.page-header title="Oferty" description="Kosz: oferty przeniesione z archiwum. Przywrócona oferta wraca z numerem, klientem, wersjami i historią — bez ponownej publikacji." />

  @include('guesstimate.offers.partials.tabs', ['tab' => 'trash'])

  <section class="rounded-panel border border-line bg-surface shadow-panel" x-data="{ open: false, url: '', number: '' }">
    @if (! $rows)
      <x-gst.empty-state icon="offers" title="Kosz jest pusty" text="Oferty przeniesione do kosza pojawią się tutaj." />
    @else
      <div class="relative overflow-x-auto">
        <table class="w-full min-w-max text-left">
          <thead class="border-b border-line text-sm text-ink-soft">
            <tr>
              <th scope="col" class="px-6 py-3 font-medium">Numer</th>
              <th scope="col" class="px-4 py-3 font-medium">Nazwa projektu</th>
              <th scope="col" class="px-4 py-3 font-medium">Klient</th>
              <th scope="col" class="px-4 py-3 font-medium">Autor</th>
              <th scope="col" class="px-4 py-3 font-medium">Data usunięcia</th>
              <th scope="col" class="px-4 py-3 font-medium">Usunięta przez</th>
              <th scope="col" class="px-6 py-3 text-right font-medium"><span class="sr-only">Akcje</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            @foreach ($rows as $row)
              <tr data-trash-row="{{ $row['id'] }}">
                <td class="whitespace-nowrap px-6 py-3 font-medium">{{ $row['number'] }}</td>
                <td class="px-4 py-3">{{ $row['project'] }}</td>
                <td class="px-4 py-3">{{ $row['client'] }}</td>
                <td class="px-4 py-3 text-ink-soft">{{ $row['author'] }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-ink-soft">{{ $row['deleted_at'] }}</td>
                <td class="px-4 py-3 text-ink-soft">{{ $row['deleted_by'] }}</td>
                <td class="whitespace-nowrap px-6 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <form method="post" action="{{ route('gst.offers.trash.restore', $row['id']) }}">
                      <x-gst.csrf />
                      <button type="submit" class="rounded-control px-2 py-1 text-accent hover:bg-accent-soft">Przywróć</button>
                    </form>
                    @if ($canForceDelete)
                      <button type="button" class="rounded-control px-2 py-1 text-danger hover:bg-danger-soft"
                        x-on:click="url = @js(route('gst.offers.trash.destroy', $row['id'])); number = @js($row['number']); open = true">Usuń na stałe</button>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if ($canForceDelete)
      <div x-show="open" x-cloak x-on:keydown.escape.window="open = false" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10" role="dialog" aria-modal="true" aria-labelledby="gst-destroy-title">
        <div class="fixed inset-0 bg-ink/50" x-on:click="open = false"></div>
        <form method="post" :action="url" class="relative w-full max-w-lg rounded-panel bg-surface shadow-overlay" x-data="{ confirm: '' }">
          <x-gst.csrf />
          <div class="border-b border-line px-6 py-4"><h2 id="gst-destroy-title" class="text-lg font-semibold">Usuń ofertę na stałe</h2></div>
          <div class="space-y-4 px-6 py-5">
            <p>Oferta <span class="font-medium" x-text="number"></span> zostanie usunięta bezpowrotnie razem z wersjami, publikacjami, historią wyświetleń i historią zmian. Klient i grafiki w bibliotece mediów zostają.</p>
            <x-gst.field label="Wpisz USUŃ, aby potwierdzić" for="f-destroy-confirm">
              <x-gst.input name="confirm" id="f-destroy-confirm" x-model="confirm" autocomplete="off" />
            </x-gst.field>
          </div>
          <div class="flex justify-end gap-2 border-t border-line px-6 py-4">
            <x-gst.button variant="ghost" x-on:click="open = false">Anuluj</x-gst.button>
            <button type="submit" class="inline-flex items-center justify-center rounded-control bg-danger px-4 py-2 font-medium text-white hover:opacity-90 disabled:opacity-40" :disabled="confirm.trim().toUpperCase() !== 'USUŃ'">Usuń na stałe</button>
          </div>
        </form>
      </div>
    @endif
  </section>
@endsection
