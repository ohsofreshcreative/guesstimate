@extends('guesstimate.layouts.app')

@section('title', 'Przygotuj umowę – ' . $offer->number)

@php
  $errors ??= [];
  $submitted = isset($old);
@endphp

@section('content')
  <div class="mb-2">
    <a href="{{ route('gst.offers.show', $offer) }}" class="text-ink-soft hover:text-ink">← {{ $offer->number }}</a>
  </div>
  <x-gst.page-header title="Przygotuj umowę" description="Dane zostaną skopiowane do umowy. Późniejsze zmiany oferty nie zmienią jej same." />

  @if ($templates->isEmpty())
    <x-gst.alert type="warning">Nie ma zatwierdzonego szablonu umowy.</x-gst.alert>
  @else
    <form method="get" action="{{ route('gst.contracts.create', $offer) }}" class="mb-6 max-w-2xl">
      <x-gst.field label="Szablon" for="f-template" :error="$errors['template_id'] ?? null">
        <select name="szablon" id="f-template" class="block w-full rounded-control border-line-strong" onchange="this.form.submit()">
          <option value="">Wybierz szablon…</option>
          @foreach ($templates as $template)
            <option value="{{ $template->id }}" @selected($selected?->id === $template->id)>{{ $template->name }} (v{{ $template->latestApproved->version_no }})</option>
          @endforeach
        </select>
      </x-gst.field>
    </form>
  @endif

  @if ($selected)
    @if ($missing)
      <x-gst.alert type="warning" class="mb-6 max-w-2xl">
        Brakujące pola: {{ implode(', ', $missing) }}. Szkic można wygenerować także bez nich, ale wtedy umowy nie da się zatwierdzić.
      </x-gst.alert>
    @endif

    <form method="post" action="{{ route('gst.contracts.store', $offer) }}" class="max-w-2xl space-y-6">
      <x-gst.csrf />
      <input type="hidden" name="template_id" value="{{ $selected->id }}">

      @if (! empty($errors['values']))
        <x-gst.alert type="error">{{ $errors['values'] }}</x-gst.alert>
      @endif

      <x-gst.field label="Nazwa umowy" for="f-title" :error="$errors['title'] ?? null">
        <x-gst.input name="title" id="f-title" :value="$submitted ? data_get($old, 'title') : $defaultTitle" :error="$errors['title'] ?? null" />
      </x-gst.field>

      <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
        <h2 class="text-lg font-semibold">Dane umowy</h2>
        <div class="mt-5 grid gap-5">
          @include('guesstimate.contracts.partials.fields', ['fields' => $fields, 'readonly' => false, 'posted' => $submitted ? (array) data_get($old, 'values') : []])
        </div>
      </section>

      <x-gst.button type="submit">Wygeneruj szkic umowy</x-gst.button>
    </form>
  @endif
@endsection
