@extends('guesstimate.wizard.layout')

@php
  $errors ??= [];
  $hasOld = isset($old);
  $v = fn ($key) => $hasOld ? data_get($old, $key) : ($values[$key] ?? null);
  $newClient = $hasOld && ! empty($old['new_client']);
@endphp

@section('step')
  <form method="post"
    action="{{ $offer ? route('gst.offers.wizard.save', ['offer' => $offer, 'step' => 1]) : route('gst.offers.store') }}"
    x-data="{ dirty: false }"
    x-on:input="dirty = true"
    x-on:change="dirty = true"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }"
    novalidate>
    <x-gst.csrf />

    <div class="space-y-6">
      <section class="rounded-panel border border-line bg-surface p-6 shadow-panel"
        x-data="clientPicker(@js([
          'selected' => $client,
          'searchUrl' => $clientSearchUrl,
          'importUrl' => $clickupImportUrl,
          'newClient' => $newClient,
        ]))">
        <h2 class="text-lg font-semibold">Klient <span class="text-danger" aria-hidden="true">*</span></h2>
        <input type="hidden" name="client_id" :value="selected ? selected.id : ''">
        <input type="hidden" name="new_client" :value="newClient ? 1 : ''">

        <div class="mt-4" x-show="! newClient">
          <template x-if="selected">
            <div class="flex flex-wrap items-start justify-between gap-4 rounded-control border border-accent/40 bg-accent-soft/50 p-4">
              <div class="min-w-0">
                <p class="font-medium" x-text="selected.name"></p>
                <p class="mt-1 text-sm text-ink-soft" x-text="[selected.website_url, selected.contact_name, selected.email, selected.phone, selected.nip ? 'NIP ' + selected.nip : null].filter(Boolean).join(' · ') || 'Brak danych kontaktowych'"></p>
                <p class="mt-1 text-sm text-ink-muted" x-show="selected.clickup">Zaimportowany z ClickUp</p>
                <p class="mt-1 text-sm text-risk-mid" x-show="selected.note" x-text="selected.note"></p>
              </div>
              <button type="button" class="rounded-control px-3 py-1.5 text-sm text-ink-soft hover:bg-surface hover:text-ink" x-on:click="clear()">Zmień klienta</button>
            </div>
          </template>

          <div x-show="! selected" class="relative">
            <label for="f-client-search" class="mb-1.5 block font-medium">Wyszukaj klienta</label>
            <input type="search" id="f-client-search" x-model="q" x-on:input.debounce.350ms="search()" x-on:focus="open = results.local.length + results.clickup.length > 0"
              x-on:keydown.escape="open = false" autocomplete="off" placeholder="Nazwa firmy, NIP, e-mail…"
              @class(['block w-full rounded-control border bg-surface px-3 py-2 focus:border-accent focus:ring-accent', 'border-danger' => ! empty($errors['client_id']), 'border-line-strong' => empty($errors['client_id'])])
              aria-describedby="f-client-search-hint" role="combobox" :aria-expanded="open">
            <p id="f-client-search-hint" class="mt-1.5 text-sm text-ink-muted">Szukamy wśród klientów w Guesstimate<span x-show="clickupStatus === 'ok' || clickupStatus === 'short'"> i w Twoich firmach z ClickUp</span>. Wybór uzupełni dane firmy.</p>
            @if (! empty($errors['client_id']))<p class="mt-1.5 text-sm text-danger">{{ $errors['client_id'] }}</p>@endif

            <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute z-20 mt-1 max-h-96 w-full overflow-y-auto rounded-panel border border-line bg-surface shadow-overlay">
              <p class="px-4 py-3 text-sm text-ink-muted" x-show="loading">Szukam…</p>
              <template x-if="results.local.length">
                <div>
                  <p class="bg-canvas px-4 py-1.5 text-sm font-medium text-ink-soft">W Guesstimate</p>
                  <template x-for="item in results.local" :key="'l' + item.id">
                    <button type="button" class="block w-full px-4 py-2 text-left hover:bg-canvas" x-on:click="choose(item)">
                      <span class="block font-medium" x-text="item.name"></span>
                      <span class="block text-sm text-ink-muted" x-text="[item.website_url, item.nip ? 'NIP ' + item.nip : null, item.email].filter(Boolean).join(' · ')"></span>
                    </button>
                  </template>
                </div>
              </template>
              <template x-if="results.clickup.length">
                <div>
                  <p class="bg-canvas px-4 py-1.5 text-sm font-medium text-ink-soft">Z ClickUp</p>
                  <template x-for="item in results.clickup" :key="'c' + item.clickup_id">
                    <button type="button" class="block w-full px-4 py-2 text-left hover:bg-canvas disabled:opacity-50" x-on:click="importClickUp(item)" :disabled="importing || ! canImport" data-clickup-result>
                      <span class="block font-medium"><span x-text="item.name"></span> <span class="text-sm font-normal text-ink-muted" x-show="item.local_id">· już w Guesstimate</span></span>
                      <span class="block text-sm text-ink-muted" x-text="details(item)"></span>
                    </button>
                  </template>
                </div>
              </template>
              <p class="px-4 py-3 text-sm text-ink-muted" x-show="nothingFound" data-client-empty>Nie znaleziono firm.</p>
              <p class="border-t border-line px-4 py-2 text-sm text-ink-muted" x-show="! loading && clickupStatus === 'ok' && ! results.clickup.length && results.local.length">Nie znaleziono firm w ClickUp.</p>
              <p class="border-t border-line px-4 py-3 text-sm text-ink-soft" x-show="! loading && clickupMessage" data-clickup-message>
                <span x-text="clickupMessage"></span>
                <a :href="integrationsUrl" class="font-medium text-accent-strong underline" x-show="integrationsUrl">Moje integracje</a>
              </p>
              <template x-if="choice && choice.deleted">
                <div class="border-t border-line px-4 py-3 text-sm" data-clickup-deleted>
                  <p class="font-medium">Firma „<span x-text="choice.item.name"></span>” była klientem Guesstimate, ale została usunięta<span x-show="choice.deleted.deleted_at" x-text="` ${choice.deleted.deleted_at}`"></span><span x-show="choice.deleted.deleted_by" x-text="` przez: ${choice.deleted.deleted_by}`"></span>.</p>
                  <p class="mt-1 text-ink-muted">Synchronizacja z ClickUp nie przywraca usuniętych klientów automatycznie. Zdecyduj, co zrobić.</p>
                  <div class="mt-3 flex flex-wrap gap-3">
                    <button type="button" class="rounded-control border border-line-strong px-3 py-1.5 font-medium hover:bg-canvas" x-show="choice.canRestore" x-on:click="restoreDeleted()" :disabled="importing" data-choice-restore>Przywróć klienta</button>
                    <button type="button" class="font-medium text-accent hover:underline" x-on:click="createNew()" :disabled="importing" data-choice-new>Zaimportuj jako nowego klienta</button>
                    <button type="button" class="text-ink-soft hover:underline" x-on:click="choice = null">Anuluj</button>
                  </div>
                  <p class="mt-2 text-ink-muted" x-show="! choice.canRestore">Przywrócić klienta może Administrator lub Manager (Klienci → Usunięci).</p>
                </div>
              </template>
              <template x-if="choice && ! choice.deleted">
                <div class="border-t border-line px-4 py-3 text-sm" data-clickup-choice>
                  <p class="font-medium">W Guesstimate są podobni klienci. Czy „<span x-text="choice.item.name"></span>” to jeden z nich?</p>
                  <ul class="mt-2 space-y-1">
                    <template x-for="client in choice.candidates" :key="client.id">
                      <li class="flex flex-wrap items-center justify-between gap-2">
                        <span><span class="font-medium" x-text="client.name"></span> <span class="text-ink-muted" x-text="[client.website_url, client.email, client.nip ? 'NIP ' + client.nip : null].filter(Boolean).join(' · ')"></span></span>
                        <button type="button" class="rounded-control border border-line-strong px-2 py-1 hover:bg-canvas" x-on:click="useExisting(client)" :disabled="importing" data-choice-existing>Użyj tego klienta</button>
                      </li>
                    </template>
                  </ul>
                  <div class="mt-3 flex gap-3">
                    <button type="button" class="font-medium text-accent hover:underline" x-on:click="createNew()" :disabled="importing" data-choice-new>To inna firma — dodaj nowego klienta</button>
                    <button type="button" class="text-ink-soft hover:underline" x-on:click="choice = null">Anuluj</button>
                  </div>
                  <p class="mt-2 text-ink-muted">Dane istniejącego klienta nie zostaną nadpisane — uzupełnimy tylko puste pola.</p>
                </div>
              </template>
              <p class="px-4 py-3 text-sm text-danger" x-show="error" x-text="error"></p>
            </div>
          </div>
        </div>

        @if ($canCreateClient)
          <div class="mt-4">
            <button type="button" class="text-sm font-medium text-accent hover:underline" x-show="! newClient" x-on:click="newClient = true">+ Dodaj nowego klienta ręcznie</button>
            <button type="button" class="text-sm font-medium text-ink-soft hover:underline" x-show="newClient" x-cloak x-on:click="newClient = false">← Wróć do wyszukiwania</button>
          </div>

          <div x-show="newClient" x-cloak class="mt-4 grid gap-4 border-t border-line pt-4 md:grid-cols-2">
            <x-gst.field label="Nazwa firmy" for="f-client_company_name" :error="$errors['client_company_name'] ?? null" required class="md:col-span-2">
              <x-gst.input name="client_company_name" :value="$v('client_company_name')" :error="$errors['client_company_name'] ?? null" />
            </x-gst.field>
            <x-gst.field label="Strona WWW" for="f-client_website_url" :error="$errors['client_website_url'] ?? null">
              <x-gst.input name="client_website_url" :value="$v('client_website_url')" :error="$errors['client_website_url'] ?? null" />
            </x-gst.field>
            <x-gst.field label="Osoba kontaktowa" for="f-client_contact_name" :error="$errors['client_contact_name'] ?? null">
              <x-gst.input name="client_contact_name" :value="$v('client_contact_name')" />
            </x-gst.field>
            <x-gst.field label="E-mail" for="f-client_email" :error="$errors['client_email'] ?? null">
              <x-gst.input type="email" name="client_email" :value="$v('client_email')" :error="$errors['client_email'] ?? null" />
            </x-gst.field>
            <x-gst.field label="Telefon" for="f-client_phone" :error="$errors['client_phone'] ?? null">
              <x-gst.input type="tel" name="client_phone" :value="$v('client_phone')" :error="$errors['client_phone'] ?? null" />
            </x-gst.field>
            <x-gst.field label="NIP" for="f-client_nip" :error="$errors['client_nip'] ?? null" hint="Opcjonalnie">
              <x-gst.input name="client_nip" :value="$v('client_nip')" :error="$errors['client_nip'] ?? null" />
            </x-gst.field>
          </div>
        @endif
      </section>

      <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
        <h2 class="text-lg font-semibold">Projekt</h2>

        <div class="mt-5 space-y-6">
          <x-gst.field label="Nazwa projektu" for="f-project_name" :error="$errors['project_name'] ?? null" required>
            <x-gst.input name="project_name" :value="$v('project_name')" :error="$errors['project_name'] ?? null" maxlength="190" required placeholder="np. Nowa strona internetowa restauracji" />
          </x-gst.field>

          <fieldset x-data="{ type: @js((string) $v('project_type')) }">
            <legend class="mb-1.5 font-medium">Rodzaj projektu <span class="text-danger" aria-hidden="true">*</span></legend>
            <p class="mb-3 text-sm text-ink-muted">Jeden rodzaj na ofertę. Dodatkowe jednostki kalkulacyjne to osobna pozycja wyceny — nie są dopisywane do mapy strony.</p>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              @foreach ($projectTypes as $key => $type)
                <label class="flex cursor-pointer flex-col gap-0.5 rounded-control border border-line px-3 py-2.5 hover:bg-canvas has-checked:border-accent has-checked:bg-accent-soft">
                  <span class="flex items-center gap-3">
                    <input type="radio" name="project_type" value="{{ $key }}" @checked($v('project_type') === $key) x-model="type" class="text-accent focus:ring-accent">
                    <span class="font-medium">{{ $type['label'] }}</span>
                  </span>
                  <span class="pl-7 text-sm text-ink-muted">{{ $type['units'] }}</span>
                </label>
              @endforeach
            </div>
            @if (! empty($errors['project_type']))<p class="mt-1.5 text-sm text-danger">{{ $errors['project_type'] }}</p>@endif

            <div class="mt-4 max-w-xs" x-show="type === 'other'" x-cloak>
              <x-gst.field label="Liczba dodatkowych jednostek kalkulacyjnych" for="f-custom_units" :error="$errors['custom_units'] ?? null" hint="Liczba całkowita ≥ 0. Każda jednostka to godziny UI i Dev jednej podstrony.">
                <x-gst.input type="number" name="custom_units" id="f-custom_units" min="0" max="999" step="1" inputmode="numeric" :value="$v('custom_units') ?? '0'" :error="$errors['custom_units'] ?? null" x-bind:disabled="type !== 'other'" />
              </x-gst.field>
            </div>
          </fieldset>

          <fieldset>
            <legend class="mb-1.5 font-medium">Zakres UX <span class="text-danger" aria-hidden="true">*</span></legend>
            <div class="grid gap-2 sm:grid-cols-2">
              @foreach ($uxScopes as $key => $scope)
                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-control border border-line px-3 py-3 hover:bg-canvas has-checked:border-accent has-checked:bg-accent-soft">
                  <span class="flex items-center gap-3">
                    <input type="radio" name="ux_scope" value="{{ $key }}" @checked($v('ux_scope') === $key) class="text-accent focus:ring-accent">
                    <span>{{ $scope['label'] }}</span>
                  </span>
                  <span class="text-ink-soft">{{ $scope['hours'] }}</span>
                </label>
              @endforeach
            </div>
            @if (! empty($errors['ux_scope']))<p class="mt-1.5 text-sm text-danger">{{ $errors['ux_scope'] }}</p>@endif
          </fieldset>

          <details class="group rounded-control border border-line" @if ($v('internal_notes')) open @endif>
            <summary class="cursor-pointer list-none px-4 py-3 font-medium marker:hidden">
              <span class="text-ink-soft group-open:hidden">+</span><span class="hidden text-ink-soft group-open:inline">−</span>
              Notatki wewnętrzne <span class="font-normal text-ink-muted">(opcjonalnie, tylko dla zespołu)</span>
            </summary>
            <div class="border-t border-line p-4">
              <label for="f-internal_notes" class="sr-only">Notatki wewnętrzne</label>
              <x-gst.textarea name="internal_notes" :value="$v('internal_notes')" rows="3" placeholder="Ustalenia, ryzyka, kontekst rozmowy — nie trafiają do oferty ani do AI." />
            </div>
          </details>
        </div>
      </section>
    </div>

    @include('guesstimate.wizard.partials.actions', ['nextLabel' => $offer ? 'Zapisz i dalej' : 'Utwórz ofertę i przejdź dalej'])
  </form>
@endsection
