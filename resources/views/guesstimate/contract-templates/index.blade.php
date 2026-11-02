@extends('guesstimate.layouts.app')

@section('title', 'Szablony umów')

@php
  $openModal ??= null;
  $errors ??= [];
  $old ??= [];
  // Oba formularze mają pole „Nazwa”: błąd i wpisane wartości należą tylko do tego, który wysłano.
  $createErrors = $openModal === 'template-create' ? $errors : [];
  $createOld = $openModal === 'template-create' ? $old : [];
  $importErrors = $openModal === 'template-import' ? $errors : [];
  $importOld = $openModal === 'template-import' ? $old : [];
@endphp

@section('content')
  <x-gst.page-header title="Szablony umów" description="Wzory umów z wersjami. Umowę można przygotować tylko z zatwierdzonej wersji.">
    <x-slot:actions>
      <x-gst.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'template-import')">Importuj DOCX</x-gst.button>
      <x-gst.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'template-create')">Nowy szablon</x-gst.button>
    </x-slot:actions>
  </x-gst.page-header>

  <section class="rounded-panel border border-line bg-surface shadow-panel">
    @if ($templates->isEmpty())
      <x-gst.empty-state icon="contracts" title="Nie ma jeszcze szablonów" text="Utwórz pierwszy szablon albo zaimportuj istniejący wzór z pliku DOCX.">
        <x-gst.button x-data x-on:click="$dispatch('open-modal', 'template-create')">Nowy szablon</x-gst.button>
      </x-gst.empty-state>
    @else
      <div class="relative overflow-x-auto">
        <table class="w-full min-w-max text-left">
          <thead class="text-sm text-ink-soft">
            <tr class="border-b border-line">
              <th class="px-6 py-2 font-medium">Nazwa</th>
              <th class="px-4 py-2 font-medium">Bieżąca wersja</th>
              <th class="px-4 py-2 font-medium">Zatwierdzona</th>
              <th class="px-6 py-2"><span class="sr-only">Działania</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            @foreach ($templates as $template)
              <tr @class(['text-ink-muted' => $template->isArchived()])>
                <td class="px-6 py-2.5">
                  <a href="{{ route('gst.contract-templates.edit', $template) }}" class="font-medium hover:underline">{{ $template->name }}</a>
                  @if ($template->description)<p class="max-w-prose truncate text-sm text-ink-muted">{{ $template->description }}</p>@endif
                </td>
                <td class="px-4 py-2.5">
                  @if ($template->isArchived())
                    Archiwum
                  @else
                    v{{ $template->currentVersion?->version_no }} · {{ $template->currentVersion?->status->label() }}
                  @endif
                </td>
                <td class="px-4 py-2.5">{{ $template->latestApproved ? 'v' . $template->latestApproved->version_no . ', ' . wp_date('j.m.Y', $template->latestApproved->approved_at->getTimestamp()) : '–' }}</td>
                <td class="px-6 py-2.5">
                  <div class="flex justify-end gap-2">
                    <form method="post" action="{{ route('gst.contract-templates.duplicate', $template) }}">
                      <x-gst.csrf />
                      <x-gst.button type="submit" variant="ghost">Kopiuj</x-gst.button>
                    </form>
                    @unless ($template->isArchived())
                      <form method="post" action="{{ route('gst.contract-templates.archive', $template) }}" x-data x-on:submit="if (! confirm('Przenieść szablon do archiwum? Istniejące umowy zostają bez zmian.')) $event.preventDefault()">
                        <x-gst.csrf />
                        <x-gst.button type="submit" variant="ghost">Archiwizuj</x-gst.button>
                      </form>
                    @endunless
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>

  <x-gst.modal id="template-create" title="Nowy szablon" :open="$openModal === 'template-create'">
    <form method="post" action="{{ route('gst.contract-templates.store') }}" class="space-y-4">
      <x-gst.csrf />
      <x-gst.field label="Nazwa" for="template-name" :error="$createErrors['name'] ?? null">
        <x-gst.input id="template-name" name="name" :value="$createOld['name'] ?? ''" maxlength="190" required />
      </x-gst.field>
      <x-gst.field label="Opis" for="template-description" :error="$createErrors['description'] ?? null">
        <x-gst.input id="template-description" name="description" :value="$createOld['description'] ?? ''" maxlength="500" />
      </x-gst.field>
      <div class="flex justify-end"><x-gst.button type="submit">Utwórz</x-gst.button></div>
    </form>
  </x-gst.modal>

  <x-gst.modal id="template-import" title="Import wzoru z pliku DOCX" :open="$openModal === 'template-import'">
    <form method="post" enctype="multipart/form-data" action="{{ route('gst.contract-templates.import') }}" class="space-y-4">
      <x-gst.csrf />
      <p class="text-sm text-ink-soft">Import przenosi tekst i podstawową strukturę: nagłówki, akapity, listy i tabele. To punkt wyjścia, a nie wierna kopia dokumentu Word. Wynik to szkic, który trzeba porównać z oryginałem.</p>
      <x-gst.field label="Nazwa" for="import-name" :error="$importErrors['name'] ?? null">
        <x-gst.input id="import-name" name="name" :value="$importOld['name'] ?? ''" maxlength="190" required />
      </x-gst.field>
      <x-gst.field label="Plik DOCX (do 10 MB)" for="docx" :error="$importErrors['docx'] ?? null">
        <input type="file" name="docx" id="docx" accept=".docx" required class="block w-full text-sm">
      </x-gst.field>
      <div class="flex justify-end"><x-gst.button type="submit">Importuj</x-gst.button></div>
    </form>
  </x-gst.modal>
@endsection
