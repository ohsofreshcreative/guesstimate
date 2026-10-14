@extends('guesstimate.layouts.app')

@section('title', $offer->number)

@section('content')
  <div class="mb-2">
    <a href="{{ route('gst.offers.index') }}" class="text-ink-soft hover:text-ink">← Oferty</a>
  </div>

  <header class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div class="min-w-0">
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-semibold tracking-tight">{{ $offer->number }}</h1>
        <x-gst.status-badge :status="$offer->status" />
      </div>
      <p class="mt-2 text-ink-soft">
        {{ $summary['project_name'] }}
        @if ($offer->client) — <span class="text-ink">{{ $offer->client->company_name }}</span>@endif
      </p>
      <p class="mt-1 text-sm text-ink-muted">
        Autor: {{ $meta['author'] }}, {{ $meta['created'] }} · Ostatnia zmiana: {{ $meta['editor'] }}, {{ $meta['updated'] }}
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      @if ($canEdit && $offer->status->isEditable())
        @if ($version->isLocked() || ! $isCurrent || ! $offer->currentVersion->isBudget())
          <x-gst.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'new-version')">Nowa wersja</x-gst.button>
        @else
          <x-gst.button :href="route('gst.offers.wizard', ['offer' => $offer, 'step' => 1])">Edytuj</x-gst.button>
        @endif
        @if ($offer->currentVersion->isBudget())
          <x-gst.button :href="route('gst.offers.document', $offer)" variant="secondary">Edytuj dokument</x-gst.button>
        @endif
      @endif

      @if ($canDuplicate)
        <form method="post" action="{{ route('gst.offers.duplicate', $offer) }}">
          <x-gst.csrf />
          <x-gst.button type="submit" variant="secondary">Duplikuj</x-gst.button>
        </form>
      @endif

      @if ($canEdit && $transitions)
        <x-gst.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'status')">Zmień status</x-gst.button>
      @endif

      @if ($canEdit)
        <x-gst.button variant="ghost" class="text-danger" data-trash-url="{{ route('gst.offers.trash.move', $offer) }}" data-trash-number="{{ $offer->number }}" x-data x-on:click="$dispatch('gst-trash', { url: $el.dataset.trashUrl, number: $el.dataset.trashNumber })">Przenieś do kosza</x-gst.button>
      @endif
    </div>
  </header>

  @if (! $isCurrent)
    <x-gst.alert type="warning" class="mb-6">
      Oglądasz wersję {{ $version->version_no }} — historyczną. Bieżąca wersja to {{ $offer->currentVersion->version_no }}.
      <a href="{{ route('gst.offers.show', $offer) }}" class="font-medium underline">Pokaż bieżącą</a>
    </x-gst.alert>
  @elseif ($version->isLocked())
    <x-gst.alert class="mb-6">
      Wersja {{ $version->version_no }} jest zamrożona od {{ $summary['locked_at'] }} (oferta została wysłana). Zmiany wymagają utworzenia nowej wersji — ta pozostanie bez zmian.
    </x-gst.alert>
  @endif

  @if ($isCurrent && $offer->currentVersion->isBudget())
    <div class="mb-6">
      @include('guesstimate.offers.partials.publication', ['publication' => $publication['publication'], 'blockers' => $publication['blockers'], 'back' => 'offer', 'canEdit' => $canEdit])
    </div>
  @elseif ($isCurrent && ! $version->isBudget())
    <x-gst.alert class="mb-6">Ta wersja jest liczona poprzednim modelem kalkulacji (bez zmian w wycenie). Publikacja online i nowy kreator są dostępne po utworzeniu nowej wersji.</x-gst.alert>
  @endif

  @include('guesstimate.offers.partials.details', ['summary' => $summary])

  <div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel" aria-labelledby="gst-versions">
      <h2 id="gst-versions" class="border-b border-line px-5 py-3 font-semibold">Wersje</h2>
      <div class="relative overflow-x-auto">
        <table class="w-full min-w-max text-left">
          <thead class="text-sm text-ink-soft">
            <tr class="border-b border-line">
              <th class="px-5 py-2 font-medium">Wersja</th>
              <th class="px-3 py-2 text-right font-medium">Godziny</th>
              <th class="px-3 py-2 text-right font-medium">Wycena od–do</th>
              <th class="px-5 py-2 font-medium">Utworzył</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            @foreach ($versions as $row)
              <tr @class(['bg-accent-soft/40' => $row['no'] === $version->version_no])>
                <td class="px-5 py-2.5">
                  <a href="{{ route('gst.offers.show', ['offer' => $offer, 'wersja' => $row['no']]) }}" class="font-medium hover:text-accent">Wersja {{ $row['no'] }}</a>
                  @if ($row['current'])<span class="ml-1 text-sm text-accent">bieżąca</span>@endif
                  @if ($row['locked'])<span class="ml-1 text-sm text-ink-muted">zamrożona</span>@endif
                  @if ($row['note'])<p class="text-sm text-ink-muted">{{ $row['note'] }}</p>@endif
                </td>
                <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $row['hours'] }}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-right" @if ($row['levels']) title="Low {{ $row['levels']['low'] }} · Mid {{ $row['levels']['mid'] }} · High {{ $row['levels']['high'] }}" @endif>{{ $row['range'] }}</td>
                <td class="px-5 py-2.5 text-sm text-ink-soft">{{ $row['author'] }}<br>{{ $row['created'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <section class="min-w-0 rounded-panel border border-line bg-surface shadow-panel" aria-labelledby="gst-history">
      <h2 id="gst-history" class="border-b border-line px-5 py-3 font-semibold">Historia</h2>
      <ol class="divide-y divide-line">
        @foreach ($events as $event)
          <li class="px-5 py-2.5">
            <p>@if ($event['link'])<a href="{{ $event['link'] }}" class="hover:text-accent">{{ $event['text'] }}</a>@else{{ $event['text'] }}@endif</p>
            @if ($event['note'])<p class="text-sm text-ink-soft">„{{ $event['note'] }}”</p>@endif
            <p class="text-sm text-ink-muted">{{ $event['user'] }}, {{ $event['date'] }}</p>
          </li>
        @endforeach
      </ol>
    </section>
  </div>

  @if ($canEdit && $transitions)
    <x-gst.modal id="status" title="Zmień status oferty">
      <form method="post" action="{{ route('gst.offers.status', $offer) }}" class="space-y-4">
        <x-gst.csrf />
        <fieldset class="space-y-2">
          <legend class="mb-2 text-ink-soft">Obecny status: {{ $offer->status->label() }}</legend>
          @foreach ($transitions as $i => $status)
            <label class="flex cursor-pointer items-center gap-3 rounded-control border border-line px-3 py-2.5 hover:bg-canvas">
              <input type="radio" name="status" value="{{ $status->value }}" @checked($i === 0) class="text-accent focus:ring-accent">
              <span>{{ $status->actionLabel() }}</span>
            </label>
          @endforeach
        </fieldset>
        @if ($offer->status->value === 'prepared')
          <p class="text-sm text-ink-muted">Po oznaczeniu jako wysłana bieżąca wersja zostanie zamrożona.</p>
        @endif
        <x-gst.field label="Komentarz (opcjonalnie)" for="f-note">
          <x-gst.input name="note" id="f-note" maxlength="190" />
        </x-gst.field>
        <div class="flex justify-end gap-2">
          <x-gst.button variant="ghost" x-on:click="$dispatch('close-modal', 'status')">Anuluj</x-gst.button>
          <x-gst.button type="submit">Zmień status</x-gst.button>
        </div>
      </form>
    </x-gst.modal>
  @endif

  @if ($canEdit && $offer->status->isEditable())
    <x-gst.modal id="new-version" title="Nowa wersja oferty">
      <form method="post" action="{{ route('gst.offers.version', $offer) }}" class="space-y-4">
        <x-gst.csrf />
        <p class="text-ink-soft">Powstanie wersja {{ $offer->versions->max('version_no') + 1 }} — kopia bieżącej wersji ze wszystkimi stawkami i parametrami. Dotychczasowe wersje pozostaną bez zmian.@if ($offer->status->locksVersion()) Status oferty wróci do „Robocza”.@endif</p>
        <x-gst.field label="Czego dotyczy zmiana? (opcjonalnie)" for="f-version-note">
          <x-gst.input name="note" id="f-version-note" maxlength="190" placeholder="np. Rozszerzenie o moduł rezerwacji" />
        </x-gst.field>
        <div class="flex justify-end gap-2">
          <x-gst.button variant="ghost" x-on:click="$dispatch('close-modal', 'new-version')">Anuluj</x-gst.button>
          <x-gst.button type="submit">Utwórz wersję</x-gst.button>
        </div>
      </form>
    </x-gst.modal>
  @endif
  @include('guesstimate.offers.partials.trash-modal')
@endsection
