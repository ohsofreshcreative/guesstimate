@extends('guesstimate.layouts.app')

@section('title', 'Funkcjonalności')

@php
  $categoryOptions = $categories->pluck('name', 'id')->all();
  $openModal ??= null;
@endphp

@section('content')
  <x-gst.page-header title="Funkcjonalności" description="Biblioteka funkcjonalności z widełkami godzin. Zmiany obowiązują przy kolejnych wyborach w ofertach — zapisane oferty mają własne kopie.">
    <x-slot:actions>
      <x-gst.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'category-create')">Nowa kategoria</x-gst.button>
      <x-gst.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'feature-create')" :disabled="empty($categoryOptions)">Nowa funkcjonalność</x-gst.button>
    </x-slot:actions>
  </x-gst.page-header>

  @if ($categories->isEmpty())
    <section class="rounded-panel border border-line bg-surface shadow-panel">
      <x-gst.empty-state icon="features" title="Biblioteka jest pusta" text="Zacznij od kategorii, np. WordPress, WooCommerce, Integracje.">
        <x-gst.button x-data x-on:click="$dispatch('open-modal', 'category-create')">Dodaj kategorię</x-gst.button>
      </x-gst.empty-state>
    </section>
  @endif

  <div class="space-y-6">
    @foreach ($categories as $category)
      <section id="kategoria-{{ $category->id }}" class="scroll-mt-6 rounded-panel border border-line bg-surface shadow-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-3">
          <h2 class="font-semibold">{{ $category->name }} <span class="font-normal text-ink-muted">({{ $category->features->count() }})</span></h2>
          <button type="button" class="text-sm text-ink-soft hover:text-ink" x-data x-on:click="$dispatch('open-modal', 'category-{{ $category->id }}')">Zmień nazwę</button>
        </div>
        @if ($category->features->isEmpty())
          <p class="px-6 py-4 text-ink-muted">Brak funkcjonalności w tej kategorii.</p>
        @else
          <div class="relative overflow-x-auto">
            <table class="w-full min-w-max text-left">
              <thead class="text-sm text-ink-soft">
                <tr class="border-b border-line"><th class="px-6 py-2 font-medium">Nazwa</th><th class="px-4 py-2 text-right font-medium">Godziny</th><th class="px-4 py-2 font-medium">Etap</th><th class="px-4 py-2 font-medium">Status</th><th class="px-6 py-2"><span class="sr-only">Akcje</span></th></tr>
              </thead>
              <tbody class="divide-y divide-line">
                @foreach ($category->features as $feature)
                  <tr @class(['text-ink-muted' => ! $feature->is_active])>
                    <td class="px-6 py-2.5">
                      <p class="font-medium">{{ $feature->name }}</p>
                      @if ($feature->description)<p class="max-w-prose truncate text-sm text-ink-muted">{{ $feature->description }}</p>@endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-2.5 text-right">{{ \App\Guesstimate\Support\Format::hoursRange(['min' => (string) $feature->hours_min, 'max' => (string) $feature->hours_max]) }}</td>
                    <td class="px-4 py-2.5">{{ $stages[$feature->stage_key] ?? $feature->stage_key }}</td>
                    <td class="px-4 py-2.5">{{ $feature->is_active ? 'Aktywna' : 'Nieaktywna' }}</td>
                    <td class="px-6 py-2.5 text-right"><button type="button" class="rounded-control px-2 py-1 text-accent hover:bg-accent-soft" x-data x-on:click="$dispatch('open-modal', 'feature-{{ $feature->id }}')">Edytuj</button></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </section>

      <x-gst.modal :id="'category-' . $category->id" title="Nazwa kategorii" :open="$openModal === 'category-' . $category->id">
        <form method="post" action="{{ route('gst.features.categories.update', $category) }}" class="space-y-4">
          <x-gst.csrf />
          <x-gst.field label="Nazwa" :for="'cat-' . $category->id" :error="$openModal === 'category-' . $category->id ? ($errors['name'] ?? null) : null">
            <x-gst.input name="name" :id="'cat-' . $category->id" :value="$category->name" maxlength="150" />
          </x-gst.field>
          <div class="flex justify-end gap-2"><x-gst.button type="submit">Zapisz</x-gst.button></div>
        </form>
      </x-gst.modal>

      @foreach ($category->features as $feature)
        <x-gst.modal :id="'feature-' . $feature->id" title="Edytuj funkcjonalność" size="lg" :open="$openModal === 'feature-' . $feature->id">
          <form method="post" action="{{ route('gst.features.update', $feature) }}" class="space-y-6" novalidate>
            <x-gst.csrf />
            @include('guesstimate.features.partials.form', ['feature' => $feature, 'modalId' => 'feature-' . $feature->id])
            <div class="flex justify-end gap-2">
              <x-gst.button variant="ghost" x-on:click="$dispatch('close-modal', 'feature-{{ $feature->id }}')">Anuluj</x-gst.button>
              <x-gst.button type="submit">Zapisz</x-gst.button>
            </div>
          </form>
        </x-gst.modal>
      @endforeach
    @endforeach
  </div>

  <x-gst.modal id="feature-create" title="Nowa funkcjonalność" size="lg" :open="$openModal === 'feature-create'">
    <form method="post" action="{{ route('gst.features.store') }}" class="space-y-6" novalidate>
      <x-gst.csrf />
      @include('guesstimate.features.partials.form', ['feature' => null, 'modalId' => 'feature-create'])
      <div class="flex justify-end gap-2">
        <x-gst.button variant="ghost" x-on:click="$dispatch('close-modal', 'feature-create')">Anuluj</x-gst.button>
        <x-gst.button type="submit">Dodaj</x-gst.button>
      </div>
    </form>
  </x-gst.modal>

  <x-gst.modal id="category-create" title="Nowa kategoria" :open="$openModal === 'category-create'">
    <form method="post" action="{{ route('gst.features.categories.store') }}" class="space-y-4">
      <x-gst.csrf />
      <x-gst.field label="Nazwa" for="cat-new" :error="$openModal === 'category-create' ? ($errors['name'] ?? null) : null">
        <x-gst.input name="name" id="cat-new" maxlength="150" />
      </x-gst.field>
      <div class="flex justify-end gap-2"><x-gst.button type="submit">Dodaj kategorię</x-gst.button></div>
    </form>
  </x-gst.modal>
@endsection
