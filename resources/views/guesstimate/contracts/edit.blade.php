@extends('guesstimate.layouts.editor')

{{-- Edycja treści umowy (szkic bieżącej wersji): ten sam edytor i te same kartki A4 co szablon. --}}
@php
  $btn = 'inline-flex items-center justify-center gap-2 rounded-control px-3 py-1.5 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50';
  $primary = $btn . ' bg-accent text-white hover:bg-accent-strong';
  $secondary = $btn . ' border border-line-strong bg-surface text-ink hover:bg-canvas';
  $tool = 'rounded-control px-2 py-1 text-sm text-ink-soft hover:bg-canvas hover:text-ink';
  $select = 'rounded-control border-line-strong py-1 pl-2 pr-7 text-sm';
  $section = 'border-b border-line px-4 py-4';
@endphp

@section('title', $contract->title . ' – edycja treści')

@section('content')
<div class="flex h-full flex-col" style="{{ $pageCss }}" x-data="contractEditor(@js($config))"
  x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }">
  <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-surface px-4 py-2">
    <div class="flex min-w-0 items-center gap-3">
      <a href="{{ route('gst.contracts.show', $contract) }}" class="{{ $secondary }}">Wróć do umowy</a>
      <p class="truncate font-semibold">{{ $contract->title }}</p>
      <span class="shrink-0 text-sm text-ink-muted">v{{ $version->version_no }} · {{ $version->status->label() }}</span>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-sm text-ink-muted" x-show="dirty && !status" x-cloak>Niezapisane zmiany</span>
      <span class="text-sm text-ink-muted" x-show="status" x-text="status" x-cloak></span>
      <button type="button" class="{{ $secondary }}" x-on:click="togglePreview()" x-text="mode === 'edit' ? 'Podgląd A4' : 'Wróć do edycji'"></button>
      <button type="button" class="{{ $primary }}" x-on:click="save()" x-bind:disabled="busy">Zapisz</button>
    </div>
  </header>

  <p class="bg-danger-soft px-4 py-2 text-sm text-danger" role="alert" x-show="error" x-text="error" x-cloak></p>

  @include('guesstimate.contract-templates.partials.toolbar')

  <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
    @include('guesstimate.contract-templates.partials.sheets')

    <aside class="w-full shrink-0 overflow-y-auto border-l border-line bg-surface lg:w-80">
      <div class="{{ $section }} space-y-2 text-sm text-ink-soft">
        <p>Zmieniasz treść tej umowy. Szablon zostaje bez zmian.</p>
        <p>Zmienne (np. dane klienta) zostają polami: wartości uzupełnia się na stronie umowy.</p>
        <p>Każdy zapis zostaje w historii umowy. Po zmianach umowę trzeba zatwierdzić ponownie.</p>
      </div>
      <p class="px-4 py-4 text-sm text-ink-muted">Edytor obsługuje prosty dokument. Nie odwzorowuje wszystkich funkcji Worda, a wklejony tekst traci formatowanie.</p>
    </aside>
  </div>
</div>
@endsection
