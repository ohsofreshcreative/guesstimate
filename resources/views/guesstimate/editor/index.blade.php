@extends('guesstimate.layouts.editor')

{{-- Edytor wizualny dokumentu: miniatury stron | duży podgląd A4 (LIVE) | ustawienia wybranej strony.
     Ten sam widok to krok 6 kreatora ($mode = wizard) i „Edytuj dokument” ($mode = document). --}}
@php
  use App\Guesstimate\Offers\OfferStatus;
  use App\Guesstimate\Offers\PublicationStatus;
  $status = $publication['status'];
  $published = $status === PublicationStatus::Published;
  $withdrawn = $status === PublicationStatus::Withdrawn;
  $back = $mode === 'wizard' ? 'wizard' : 'document';
  $btn = 'inline-flex items-center justify-center gap-2 rounded-control px-3 py-1.5 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50';
  $primary = $btn . ' bg-accent text-white hover:bg-accent-strong';
  $secondary = $btn . ' border border-line-strong bg-surface text-ink hover:bg-canvas';
  $ghost = $btn . ' text-ink-soft hover:bg-canvas hover:text-ink';
  $ghostDesktop = str_replace('inline-flex', 'hidden lg:inline-flex', $ghost);
  $input = 'block w-full rounded-control border-line-strong px-3 py-1.5 text-sm focus:border-accent focus:ring-accent';
  $label = 'mb-1 block text-sm font-medium';
  $section = 'border-b border-line px-4 py-4';
  $steps = \App\Guesstimate\Http\Controllers\WizardController::STEPS;
@endphp

@section('title', 'Dokument – ' . $offer->number)

@section('content')
<div class="flex h-full flex-col" x-data="documentEditor(@js($config))"
  x-on:message.window="receive($event)"
  x-on:resize.window.debounce.150ms="applyZoom()"
  x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }">

  {{-- Pasek górny --}}
  <header class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-line bg-surface px-4 py-2.5">
    <a href="{{ route('gst.offers.show', $offer) }}" class="text-sm text-ink-soft hover:text-ink">← {{ $offer->number }}</a>
    <div class="min-w-0">
      <p class="truncate font-semibold">{{ $version->project_name }}</p>
      <p class="truncate text-xs text-ink-muted">Dokument · wersja {{ $version->version_no }} · {{ $offer->status->label() }} · online: {{ $status->label() }}</p>
    </div>

    @if ($mode === 'wizard')
      <nav class="hidden items-center gap-1 xl:flex" aria-label="Kroki kreatora">
        @foreach ($steps as $number => $stepLabel)
          @if ($number === 6)
            <span class="rounded-control bg-ink px-2 py-1 text-xs font-medium text-white" aria-current="step">{{ $number }}. {{ $stepLabel }}</span>
          @else
            <a href="{{ route('gst.offers.wizard', ['offer' => $offer, 'step' => $number]) }}" class="rounded-control px-2 py-1 text-xs text-ink-soft hover:bg-canvas hover:text-ink">{{ $number }}. {{ $stepLabel }}</a>
          @endif
        @endforeach
      </nav>
    @endif

    <div class="ml-auto flex flex-wrap items-center gap-2">
      <label class="sr-only" for="f-zoom">Powiększenie podglądu</label>
      <select id="f-zoom" x-model="zoom" class="rounded-control border-line-strong py-1.5 pl-3 pr-8 text-sm" title="Powiększenie podglądu (nie zmienia dokumentu klienta)">
        <option value="fit">Dopasuj</option>
        <option value="75">75%</option>
        <option value="100">100%</option>
        <option value="125">125%</option>
      </select>
      <button type="button" class="{{ $ghostDesktop }}" x-on:click="showThumbs = ! showThumbs" :aria-pressed="showThumbs">Strony</button>
      <button type="button" class="{{ $ghostDesktop }}" x-on:click="showPanel = ! showPanel" :aria-pressed="showPanel">Panel</button>
      <span class="mx-1 hidden h-6 w-px bg-line lg:block" aria-hidden="true"></span>

      <span class="text-sm" aria-live="polite" data-editor-status>
        <span x-show="saving" class="text-ink-muted">Zapisywanie…</span>
        <span x-show="! saving && dirty" class="font-medium text-risk-mid">Niezapisane zmiany</span>
        <span x-show="! saving && ! dirty && editable" class="text-ink-muted">Zapisano</span>
      </span>
      <button type="button" class="rounded-full bg-danger-soft px-2.5 py-1 text-xs font-medium text-danger" x-show="overflow" x-cloak x-on:click="select('situation', 'situation')" data-overflow-badge>Tekst nie mieści się na stronie</button>
      @if ($config['editable'])
        <button type="button" class="{{ $primary }}" x-on:click="save()" :disabled="saving || ! dirty" data-editor-save>Zapisz</button>
      @endif
      <a href="{{ $publication['preview_url'] }}" target="_blank" rel="noopener" class="{{ $secondary }}">Podgląd klienta</a>
      @if ($canEdit)
        <form method="post" action="{{ route('gst.offers.publish', $offer) }}" x-on:submit="if (! canPublish) { $event.preventDefault(); return } publishing = true" data-publish-form>
          <x-gst.csrf /><input type="hidden" name="back" value="{{ $back }}"><input type="hidden" name="after" value="summary">
          <button type="submit" class="{{ $primary }}" :disabled="! canPublish" :aria-busy="publishing" :title="dirty ? 'Najpierw zapisz zmiany' : (overflow ? 'Strona „Sytuacja” nie mieści się na A4' : '')" data-editor-publish>
            {{ $published ? 'Opublikuj nową wersję' : 'Opublikuj' }}
          </button>
        </form>
      @endif
    </div>
  </header>

  @foreach ($messages as $message)
    <x-gst.alert :type="$message['type']" class="mx-4 mt-3" x-data="{ show: true }" x-show="show" data-flash>
      <div class="flex items-start justify-between gap-4">
        <span>{{ $message['message'] }}</span>
        <button type="button" class="shrink-0 opacity-70 hover:opacity-100" x-on:click="show = false"><x-gst.icon name="close" class="size-4" /><span class="sr-only">Zamknij komunikat</span></button>
      </div>
    </x-gst.alert>
  @endforeach

  <div class="flex min-h-0 flex-1 flex-col overflow-y-auto lg:flex-row lg:overflow-hidden">
    {{-- Miniatury stron --}}
    <aside class="hidden w-44 shrink-0 flex-col border-r border-line bg-surface lg:flex" x-show="showThumbs" aria-label="Strony dokumentu">
      <p class="border-b border-line px-3 py-2 text-xs font-medium uppercase tracking-wide text-ink-muted">Strony</p>
      <iframe x-ref="thumbs" src="{{ route('gst.offers.document.frame', ['offer' => $offer, 'tryb' => 'miniatury']) }}" title="Miniatury stron dokumentu" class="min-h-0 w-full flex-1" data-editor-thumbs></iframe>
    </aside>

    {{-- Duży podgląd strony A4 --}}
    <section class="relative h-120 min-w-0 shrink-0 bg-line md:h-160 lg:h-auto lg:flex-1" aria-label="Podgląd strony">
      <iframe x-ref="frame" src="{{ route('gst.offers.document.frame', $offer) }}" title="Podgląd strony dokumentu (jak u klienta)" class="absolute inset-0 size-full" data-editor-frame></iframe>
      <div class="pointer-events-none absolute inset-x-0 bottom-3 flex justify-center">
        <div class="pointer-events-auto flex items-center gap-1 rounded-full bg-ink/90 px-2 py-1 text-sm text-white shadow-overlay">
          <button type="button" class="rounded-full px-2.5 py-1 hover:bg-white/10" x-on:click="go(-1)" aria-label="Poprzednia strona">←</button>
          <span class="min-w-40 px-2 text-center" x-text="`${pageNumber(selected) ? pageNumber(selected) + '. ' : ''}${page.label}${hiddenReason(selected) ? ' (ukryta)' : ''}`"></span>
          <button type="button" class="rounded-full px-2.5 py-1 hover:bg-white/10" x-on:click="go(1)" aria-label="Następna strona">→</button>
        </div>
      </div>
      <p class="absolute right-3 top-3 rounded-full bg-surface/90 px-2.5 py-1 text-xs text-ink-muted shadow-panel" x-show="previewing" x-cloak>odświeżanie podglądu…</p>
    </section>

    {{-- Panel ustawień --}}
    <aside class="flex w-full shrink-0 flex-col border-line bg-surface lg:w-96 lg:border-l" x-show="showPanel" aria-label="Ustawienia strony">
      <div class="flex border-b border-line" role="tablist">
        <button type="button" role="tab" class="flex-1 px-4 py-2.5 text-sm font-medium" :class="tab === 'page' ? 'border-b-2 border-accent text-ink' : 'text-ink-muted hover:text-ink'" :aria-selected="tab === 'page'" x-on:click="tab = 'page'">Strona</button>
        <button type="button" role="tab" class="flex-1 px-4 py-2.5 text-sm font-medium" :class="tab === 'publish' ? 'border-b-2 border-accent text-ink' : 'text-ink-muted hover:text-ink'" :aria-selected="tab === 'publish'" x-on:click="tab = 'publish'">Publikacja</button>
      </div>

      <div class="min-h-0 flex-1 lg:overflow-y-auto" data-editor-panel>
        <p class="mx-4 mt-3 rounded-control bg-danger-soft px-3 py-2 text-sm text-danger" x-show="error" x-text="error" x-cloak role="alert"></p>
        <p class="mx-4 mt-3 rounded-control bg-accent-soft px-3 py-2 text-sm text-accent-strong" x-show="message && ! dirty" x-text="message" x-cloak role="status"></p>

        <div x-show="tab === 'page'">
          @include('guesstimate.editor.partials.lock')
          <fieldset @disabled(! $config['editable']) class="min-w-0">
            @include('guesstimate.editor.partials.page')
          </fieldset>
        </div>

        <div x-show="tab === 'publish'" x-cloak>
          @include('guesstimate.editor.partials.publish')
        </div>
      </div>
    </aside>
  </div>

  @include('guesstimate.editor.partials.picker')
  @include('guesstimate.editor.partials.proposal')
</div>
@endsection
