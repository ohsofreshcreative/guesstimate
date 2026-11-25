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
{{-- contractPreview na całym widoku: linki eksportu dostają podział stron z podglądu. --}}
<div class="flex h-full flex-col" style="{{ $pageCss }}" x-data="contractPreview">
  <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-surface px-4 py-2">
    <div class="flex min-w-0 items-center gap-3">
      <a href="{{ route('gst.contracts.index') }}" class="{{ $secondary }}">Umowy</a>
      @if ($offer)
        <a href="{{ route('gst.offers.show', $offer) }}" class="{{ $secondary }}">{{ $offer->number }}</a>
      @endif
      <p class="truncate font-semibold">{{ $contract->title }}</p>
      <span class="shrink-0 text-sm text-ink-muted">v{{ $version->version_no }} · {{ $version->status->label() }}</span>
    </div>
    <div class="flex items-center gap-2">
    @if ($editable)
      <a href="{{ route('gst.contracts.edit', $contract) }}" class="{{ $secondary }}">Edytuj treść</a>
    @elseif ($canRevise)
      <button type="button" class="{{ $secondary }}" x-on:click="$dispatch('open-modal', 'contract-revise')">Edytuj umowę</button>
    @endif
    @if ($version->isApproved() && $version->isIntact())
      <div class="flex items-center gap-2">
        @foreach (['pdf' => 'Pobierz PDF', 'docx' => 'Pobierz DOCX'] as $format => $formatLabel)
          @php $exportUrl = route('gst.contracts.export', ['contract' => $contract, 'format' => $format, 'wersja' => $version->version_no]); @endphp
          <a href="{{ $exportUrl }}" x-bind:href="exportUrl(@js($exportUrl))" class="{{ $secondary }}">{{ $formatLabel }}</a>
        @endforeach
      </div>
    @endif
    </div>
  </header>

  @if ($version->isApproved() && ! $version->isIntact())
    <p class="bg-danger-soft px-4 py-2 text-sm text-danger" role="alert">Treść tej wersji nie zgadza się ze skrótem z zatwierdzenia. Eksport jest zablokowany.</p>
  @endif

  <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
    <div class="min-w-0 shrink-0 overflow-auto bg-canvas px-6 py-6 lg:flex-1">
      <div hidden x-ref="header">{!! $regions['header'] !!}</div>
      <div hidden x-ref="footer">{!! $regions['footer'] !!}</div>
      <div hidden x-ref="body">{!! $regions['body'] !!}</div>
      <p class="mb-3 text-center text-sm text-ink-soft"><span x-text="pageCount"></span> str. Pobrany PDF i DOCX mają ten sam podział stron co ten podgląd.</p>
      <p class="mb-3 text-center text-sm text-danger" role="alert" x-show="overflow" x-cloak>Część treści nie mieści się na stronie.</p>
      <div class="mx-auto flex w-fit flex-col gap-6" x-ref="sheets"@if ($background) style="--ct-bg: url('{{ $background }}')"@endif></div>
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

@if ($canRevise)
  <x-gst.modal id="contract-revise" title="Edytować zatwierdzoną umowę?">
    <p>Powstanie nowa wersja robocza v{{ $versions->max('version_no') + 1 }} z tą samą treścią, wartościami i płatnościami, a dane oferty zostaną pobrane na nowo. Zatwierdzona wersja v{{ $version->version_no }} zostaje bez zmian w historii.</p>
    <p class="mt-3">Nową wersję można edytować, a potem trzeba ją ponownie zatwierdzić.</p>
    <form method="post" action="{{ route('gst.contracts.new-version', $contract) }}" class="mt-5 flex justify-end gap-2">
      <x-gst.csrf />
      <button type="button" class="{{ $secondary }}" x-on:click="open = false">Anuluj</button>
      <button type="submit" class="{{ $primary }}">Utwórz wersję do edycji</button>
    </form>
  </x-gst.modal>
@endif

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
