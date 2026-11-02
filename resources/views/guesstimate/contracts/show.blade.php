@extends('guesstimate.layouts.editor')

{{-- Wersja umowy: strony A4 z danymi ze snapshotu | brakujące pola, wartości, odświeżanie. --}}
@php
  $btn = 'inline-flex items-center justify-center gap-2 rounded-control px-3 py-1.5 text-sm font-medium transition-colors';
  $primary = $btn . ' bg-accent text-white hover:bg-accent-strong';
  $secondary = $btn . ' border border-line-strong bg-surface text-ink hover:bg-canvas';
  $section = 'border-b border-line px-4 py-4';
@endphp

@section('title', $contract->title)

@section('content')
<div class="flex h-full flex-col">
  <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-surface px-4 py-2">
    <div class="flex min-w-0 items-center gap-3">
      <a href="{{ route('gst.contracts.index') }}" class="{{ $secondary }}">Umowy</a>
      @if ($offer)
        <a href="{{ route('gst.offers.show', $offer) }}" class="{{ $secondary }}">{{ $offer->number }}</a>
      @endif
      <p class="truncate font-semibold">{{ $contract->title }}</p>
      <span class="shrink-0 text-sm text-ink-muted">v{{ $version->version_no }} · {{ $version->status->label() }}</span>
    </div>
    @if ($version->isApproved() && $version->isIntact())
      <div class="flex items-center gap-2">
        <a href="{{ route('gst.contracts.export', ['contract' => $contract, 'format' => 'pdf', 'wersja' => $version->version_no]) }}" class="{{ $secondary }}">Pobierz PDF</a>
        <a href="{{ route('gst.contracts.export', ['contract' => $contract, 'format' => 'docx', 'wersja' => $version->version_no]) }}" class="{{ $secondary }}">Pobierz DOCX</a>
      </div>
    @endif
  </header>

  @if ($version->isApproved() && ! $version->isIntact())
    <p class="bg-danger-soft px-4 py-2 text-sm text-danger" role="alert">Treść tej wersji nie zgadza się ze skrótem z zatwierdzenia. Eksport jest zablokowany.</p>
  @endif

  <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
    <div class="min-w-0 shrink-0 overflow-auto bg-canvas px-6 py-6 lg:flex-1" x-data="contractPreview">
      <div hidden x-ref="header">{!! $regions['header'] !!}</div>
      <div hidden x-ref="footer">{!! $regions['footer'] !!}</div>
      <div hidden x-ref="body">{!! $regions['body'] !!}</div>
      <p class="mb-3 text-center text-sm text-ink-soft"><span x-text="pageCount"></span> str. Układ jest przybliżony: podział stron w eksporcie i w Wordzie może się różnić.</p>
      <p class="mb-3 text-center text-sm text-danger" role="alert" x-show="overflow" x-cloak>Część treści nie mieści się na stronie.</p>
      <div class="mx-auto flex w-fit flex-col gap-6" x-ref="sheets"></div>
    </div>

    <aside class="w-full shrink-0 border-line bg-surface lg:w-96 lg:overflow-y-auto lg:border-l" aria-label="Dane umowy">
      @if ($draft)
        <div class="{{ $section }}">
          <h2 class="text-sm font-semibold">Zatwierdzenie</h2>
          @if ($blockers)
            <ul class="mt-1 space-y-1 text-sm text-danger">
              @foreach ($blockers as $blocker)
                <li>{{ $blocker }}</li>
              @endforeach
            </ul>
          @endif
          @if ($canApprove)
            <button type="button" class="{{ $primary }} mt-3 w-full disabled:opacity-50" @disabled($blockers) x-data x-on:click="$dispatch('open-modal', 'contract-approve')">Zatwierdź umowę</button>
          @else
            <p class="mt-1 text-sm text-ink-muted">Szkic zatwierdza manager lub administrator.</p>
          @endif
        </div>
      @endif

      @if ($missing)
        <div class="{{ $section }} bg-danger-soft text-sm text-danger" role="status">
          <p class="font-semibold">Brakujące pola</p>
          <p class="mt-1">{{ implode(', ', $missing) }}</p>
        </div>
      @endif

      @if ($changes)
        <div class="{{ $section }}">
          <h2 class="text-sm font-semibold">Dane w ofercie się zmieniły</h2>
          <p class="mt-1 text-sm text-ink-muted">Umowa używa kopii z {{ wp_date('j.m.Y, H:i', strtotime((string) ($version->data_snapshot['taken_at'] ?? ''))) }}. Odświeżenie jest ręczne.</p>
          <ul class="mt-3 space-y-3 text-sm">
            @foreach ($changes as $change)
              <li>
                <p class="font-medium">{{ $change['label'] }}</p>
                <p class="text-ink-muted"><span class="line-through">{{ $change['old'] ?? 'brak' }}</span> → {{ $change['new'] ?? 'brak' }}</p>
                @if ($change['overridden'])
                  <p class="text-ink-muted">Zostaje wartość wpisana ręcznie.</p>
                @endif
              </li>
            @endforeach
          </ul>
          <form method="post" action="{{ route('gst.contracts.refresh', $contract) }}" class="mt-3">
            <x-gst.csrf />
            <input type="hidden" name="expected" value="{{ $expected }}">
            <button type="submit" class="{{ $secondary }} w-full">Odśwież dane z oferty</button>
          </form>
        </div>
      @endif

      <div class="{{ $section }}">
        <h2 class="text-sm font-semibold">Płatności</h2>
        @if ($payments)
          @if ($payments['agreed'])
            <p class="mt-1 text-sm">{{ \App\Guesstimate\Support\Format::money($payments['agreed']['net'], $payments['currency']) }} netto</p>
          @endif
          @include('guesstimate.contracts.partials.payment-notices', ['summary' => $payments])
        @else
          <p class="mt-1 text-sm text-ink-muted">Bez uzgodnionej kwoty i harmonogramu.</p>
        @endif
        <a href="{{ route('gst.contracts.payments', $contract) }}" class="{{ $secondary }} mt-3 w-full">Harmonogram płatności</a>
      </div>

      <div class="{{ $section }}">
        <h2 class="mb-3 text-sm font-semibold">Wartości</h2>
        @if ($fields)
          <form method="post" action="{{ route('gst.contracts.values', $contract) }}" class="space-y-4">
            <x-gst.csrf />
            <input type="hidden" name="expected" value="{{ $expected }}">
            @include('guesstimate.contracts.partials.fields', ['fields' => $fields, 'readonly' => ! $editable, 'posted' => []])
            @if ($editable)
              <button type="submit" class="{{ $primary }} w-full">Zapisz wartości</button>
            @endif
          </form>
        @else
          <p class="text-sm text-ink-muted">Szablon nie używa zmiennych.</p>
        @endif
      </div>

      <div class="{{ $section }}">
        <h2 class="mb-2 text-sm font-semibold">Wersje</h2>
        <ul class="space-y-1 text-sm">
          @foreach ($versions as $item)
            <li><a href="{{ route('gst.contracts.show', $contract) }}?wersja={{ $item->version_no }}" @class(['hover:underline', 'font-semibold' => $item->id === $version->id])>v{{ $item->version_no }} · {{ $item->status->label() }}</a></li>
          @endforeach
        </ul>
      </div>
    </aside>
  </div>
</div>

@if ($canApprove && ! $blockers)
  <x-gst.modal id="contract-approve" title="Zatwierdzić umowę?">
    <p>Wersja v{{ $version->version_no }} zostanie zamrożona: treści i danych nie będzie można zmienić. Zatwierdzenie nie wysyła ani nie podpisuje umowy.</p>
    @if ($changes)
      <p class="mt-3 text-danger">Dane w ofercie się zmieniły i nie zostały odświeżone. Umowa zachowa wcześniejsze dane.</p>
    @endif
    <form method="post" action="{{ route('gst.contracts.approve', $contract) }}" class="mt-5 flex justify-end gap-2">
      <x-gst.csrf />
      <input type="hidden" name="expected" value="{{ $expected }}">
      <button type="button" class="{{ $secondary }}" x-on:click="open = false">Anuluj</button>
      <button type="submit" class="{{ $primary }}">Zatwierdź</button>
    </form>
  </x-gst.modal>
@endif
@endsection
