@extends('guesstimate.layouts.app')

@section('title', 'Ustawienia – ' . ($sections[$section] ?? $legacySections[$section] ?? ''))

@php
  $errors ??= [];
  $openModal ??= null;
  $hasOld = isset($old) && $openModal === null;
  $o = fn ($key, $default) => $hasOld ? data_get($old, $key) : $default;
  $F = \App\Guesstimate\Support\Format::class;
@endphp

@section('content')
  <x-gst.page-header title="Ustawienia" description="Zmiany obowiązują dla nowych ofert. Zapisane oferty i ich wersje zachowują swoje stawki i parametry." />

  @include('guesstimate.settings.partials.nav')

  @if ($errors && $openModal === null)
    <x-gst.alert type="error" class="mb-6">Nie zapisano ustawień — popraw zaznaczone pola.</x-gst.alert>
  @endif

  @if ($section === 'kalkulacja')
    @include('guesstimate.settings.partials.calculation')
  @endif

  @if ($section === 'dokument')
    @include('guesstimate.settings.partials.document')
  @endif

  @if ($section === 'tla')
    @include('guesstimate.settings.partials.backgrounds')
  @endif

  @if ($section === 'realizacje')
    @include('guesstimate.settings.partials.cases')
  @endif

  @if ($section === 'integracje')
    @include('guesstimate.settings.partials.integrations')
  @endif

  @if (array_key_exists($section, $legacySections))
    <x-gst.alert type="warning" class="mb-6 max-w-5xl">Ta sekcja dotyczy poprzedniego modelu kalkulacji. Nowe oferty jej nie używają — wycena opiera się na ustawieniach w zakładce „Kalkulacja i rodzaje projektu”.</x-gst.alert>
  @endif

  @if ($section === 'stawki')
    <form method="post" action="{{ route('gst.settings.update', ['section' => 'stawki']) }}" class="max-w-3xl rounded-panel border border-line bg-surface p-6 shadow-panel" novalidate>
      <x-gst.csrf />
      <h2 class="text-lg font-semibold">Stawki godzinowe</h2>
      <p class="mt-1 text-ink-soft">Każdy poziom ryzyka ma własną stawkę netto. Godziny są wspólne dla wszystkich wariantów — wybór poziomu nie zmienia liczby godzin.</p>
      <div class="mt-6 grid gap-5 sm:grid-cols-3">
        @foreach (['low' => 'Low Risk', 'mid' => 'Mid Risk', 'high' => 'High Risk'] as $level => $label)
          <x-gst.field :label="$label" :for="'f-rate-' . $level" :error="$errors['rates.' . $level] ?? null" required>
            <div class="mb-2 flex items-center gap-2 text-sm text-ink-muted"><x-gst.risk-dot :level="$level" /> domyślnie {{ ['low' => 150, 'mid' => 180, 'high' => 200][$level] }} zł/h</div>
            <x-gst.input :name="'rates[' . $level . ']'" :id="'f-rate-' . $level" :value="$o('rates.' . $level, $F::moneyInput($settings['hourly_rates'][$level]))" :error="$errors['rates.' . $level] ?? null" suffix="zł/h" inputmode="decimal" />
          </x-gst.field>
        @endforeach
      </div>
      <p class="mt-4 text-sm text-ink-muted">Wymagane: wartość większa od zera, najwyżej dwa miejsca po przecinku, kolejność Low ≤ Mid ≤ High. Limit techniczny: 100 000 zł/h.</p>
      <div class="mt-6 flex justify-end"><x-gst.button type="submit">Zapisz stawki</x-gst.button></div>
    </form>
  @endif

  @if ($section === 'ux')
    <form method="post" action="{{ route('gst.settings.update', ['section' => 'ux']) }}" class="max-w-3xl rounded-panel border border-line bg-surface p-6 shadow-panel" novalidate>
      <x-gst.csrf />
      <h2 class="text-lg font-semibold">Zakres UX</h2>
      <p class="mt-1 text-ink-soft">Każda oferta ma jeden z dwóch zakresów UX. Godziny UX pochodzą wyłącznie stąd — szablony podstron ich nie zawierają.</p>
      <div class="mt-6 space-y-5">
        @foreach (\App\Guesstimate\Offers\BudgetSettings::uxScopes((array) $settings['ux_scopes']) as $key => $scope)
          <fieldset class="grid gap-4 rounded-control border border-line p-4 sm:grid-cols-3">
            <legend class="px-1 font-medium">{{ $scope['label'] }}</legend>
            <x-gst.field label="Godziny minimum" :for="'f-ux-' . $key . '-min'" :error="$errors['ux.' . $key . '.min'] ?? null">
              <x-gst.input :name="'ux[' . $key . '][min]'" :id="'f-ux-' . $key . '-min'" :value="$o('ux.' . $key . '.min', $F::hoursInput($scope['min']))" :error="$errors['ux.' . $key . '.min'] ?? null" suffix="h" inputmode="decimal" />
            </x-gst.field>
            <x-gst.field label="Godziny maksimum" :for="'f-ux-' . $key . '-max'" :error="$errors['ux.' . $key . '.max'] ?? null">
              <x-gst.input :name="'ux[' . $key . '][max]'" :id="'f-ux-' . $key . '-max'" :value="$o('ux.' . $key . '.max', $F::hoursInput($scope['max']))" :error="$errors['ux.' . $key . '.max'] ?? null" suffix="h" inputmode="decimal" />
            </x-gst.field>
          </fieldset>
        @endforeach
      </div>
      <div class="mt-6 flex justify-end"><x-gst.button type="submit">Zapisz zakresy UX</x-gst.button></div>
    </form>
  @endif

  @if ($section === 'etapy')
    <form method="post" action="{{ route('gst.settings.update', ['section' => 'etapy']) }}" class="max-w-4xl space-y-6" novalidate>
      <x-gst.csrf />
      <section class="rounded-panel border border-line bg-surface shadow-panel">
        <div class="border-b border-line px-6 py-4">
          <h2 class="text-lg font-semibold">Etapy projektu</h2>
          <p class="mt-1 text-ink-soft">Domyślne ustawienia etapów dla nowych ofert. Udziały procentowe działają w modelu procentowym; kwota stała Discovery jest w nim częścią kwoty projektu (jak w Excelu), a w modelu godzinowym jest doliczana.</p>
        </div>
        @if (! empty($errors['stages.percent_sum']))
          <x-gst.alert type="error" class="mx-6 mt-4">{{ $errors['stages.percent_sum'] }}</x-gst.alert>
        @endif
        <div class="relative overflow-x-auto">
          <table class="w-full min-w-max text-left">
            <thead class="text-sm text-ink-soft"><tr class="border-b border-line"><th class="px-6 py-2.5 font-medium">Nazwa</th><th class="px-3 py-2.5 font-medium">Domyślnie włączony</th><th class="px-3 py-2.5 font-medium">Udział w puli</th><th class="px-6 py-2.5 font-medium">Kwota stała</th></tr></thead>
            <tbody class="divide-y divide-line">
              @foreach ($settings['stages'] as $stage)
                @php $k = $stage['key']; @endphp
                <tr>
                  <td class="px-6 py-3">
                    <x-gst.input :name="'stages[' . $k . '][name]'" :value="$o('stages.' . $k . '.name', $stage['name'])" :error="$errors['stages.' . $k . '.name'] ?? null" maxlength="100" aria-label="Nazwa etapu" />
                  </td>
                  <td class="px-3 py-3">
                    @if ($k === 'ux')
                      <span class="text-sm text-ink-muted">zawsze</span>
                    @else
                      <input type="checkbox" name="stages[{{ $k }}][enabled]" value="1" @checked($hasOld ? ! empty($old['stages'][$k]['enabled']) : $stage['enabled']) class="size-4 rounded border-line-strong text-accent focus:ring-accent" aria-label="Włączony">
                    @endif
                  </td>
                  <td class="px-3 py-3">
                    @if ($stage['in_pool'])
                      <x-gst.input :name="'stages[' . $k . '][percent]'" :value="$o('stages.' . $k . '.percent', $F::hoursInput($stage['percent']))" :error="$errors['stages.' . $k . '.percent'] ?? null" class="w-24" suffix="%" inputmode="decimal" aria-label="Udział" />
                    @else
                      <span class="text-ink-muted">—</span>
                    @endif
                  </td>
                  <td class="px-6 py-3">
                    @if ($k === 'discovery')
                      <x-gst.input name="stages[discovery][fixed_amount]" :value="$o('stages.discovery.fixed_amount', $F::moneyInput($stage['fixed_amount']))" :error="$errors['stages.discovery.fixed_amount'] ?? null" class="w-32" suffix="zł" inputmode="decimal" aria-label="Kwota stała" />
                      @if (! empty($errors['stages.discovery.fixed_amount']))<p class="mt-1 max-w-xs text-sm text-danger">{{ $errors['stages.discovery.fixed_amount'] }}</p>@endif
                    @else
                      <span class="text-ink-muted">—</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>

      <section class="grid gap-5 rounded-panel border border-line bg-surface p-6 shadow-panel sm:grid-cols-2">
        <x-gst.field label="Domyślny model kalkulacji" for="f-default_calc_model" :error="$errors['default_calc_model'] ?? null">
          <x-gst.select name="default_calc_model" :options="$calcModels" :value="$o('default_calc_model', $settings['default_calc_model'])" />
        </x-gst.field>
        <x-gst.field label="Etap dla wprowadzania treści" for="f-content_stage" :error="$errors['content_stage'] ?? null" hint="Godziny wprowadzania treści podstron trafiają do tego etapu.">
          <x-gst.select name="content_stage" :options="array_column($settings['stages'], 'name', 'key')" :value="$o('content_stage', $settings['content_stage'])" />
        </x-gst.field>
      </section>
      <div class="flex justify-end"><x-gst.button type="submit">Zapisz etapy</x-gst.button></div>
    </form>
  @endif

  @if ($section === 'ogolne')
    <form method="post" action="{{ route('gst.settings.update', ['section' => 'ogolne']) }}" class="max-w-3xl space-y-6" novalidate>
      <x-gst.csrf />
      <section class="grid gap-5 rounded-panel border border-line bg-surface p-6 shadow-panel sm:grid-cols-2">
        <h2 class="text-lg font-semibold sm:col-span-2">Waluta i zaokrąglenia</h2>
        <x-gst.field label="Waluta" for="f-currency" :error="$errors['currency'] ?? null">
          <x-gst.select name="currency" :options="$currencies" :value="$o('currency', $settings['currency'])" />
        </x-gst.field>
        <x-gst.field label="Stawka VAT (informacyjnie)" for="f-vat_rate" :error="$errors['vat_rate'] ?? null" hint="Wyceny są netto.">
          <x-gst.input name="vat_rate" :value="$o('vat_rate', $F::hoursInput($settings['vat_rate']))" :error="$errors['vat_rate'] ?? null" suffix="%" inputmode="decimal" />
        </x-gst.field>
        <x-gst.field label="Zaokrąglanie godzin etapu" for="f-hours_step" :error="$errors['hours_step'] ?? null">
          <x-gst.select name="hours_step" :options="$hoursSteps" :value="$o('hours_step', $settings['rounding']['hours_step'])" />
        </x-gst.field>
        <x-gst.field label="Zaokrąglanie ceny końcowej" for="f-price_step" :error="$errors['price_step'] ?? null" hint="Różnica jest pokazywana w wycenie jako osobna pozycja.">
          <x-gst.select name="price_step" :options="$priceSteps" :value="$o('price_step', $settings['rounding']['price_step'])" />
        </x-gst.field>
        <x-gst.field label="Zaokrąglanie kwot UX i UI w podziale ceny" for="f-part_step" :error="$errors['part_step'] ?? null" hint="Development przejmuje resztę, więc suma zawsze równa się cenie.">
          <x-gst.select name="part_step" :options="$priceSteps" :value="$o('part_step', $settings['rounding']['part_step'] ?? 100)" />
        </x-gst.field>
      </section>
      <section class="grid gap-5 rounded-panel border border-line bg-surface p-6 shadow-panel sm:grid-cols-2">
        <h2 class="text-lg font-semibold sm:col-span-2">Numeracja ofert</h2>
        <x-gst.field label="Prefiks" for="f-prefix" :error="$errors['prefix'] ?? null">
          <x-gst.input name="prefix" :value="$o('prefix', $settings['numbering']['prefix'])" :error="$errors['prefix'] ?? null" maxlength="10" />
        </x-gst.field>
        <x-gst.field label="Liczba cyfr numeru" for="f-padding" :error="$errors['padding'] ?? null">
          <x-gst.input type="number" name="padding" min="3" max="6" :value="$o('padding', $settings['numbering']['padding'])" :error="$errors['padding'] ?? null" />
        </x-gst.field>
        <p class="text-ink-soft sm:col-span-2">Przykład: <span class="font-medium text-ink">{{ \App\Guesstimate\Offers\OfferNumberGenerator::format($settings['numbering']['prefix'], (int) wp_date('Y'), 1, (int) $settings['numbering']['padding']) }}</span>. Licznik startuje od 1 w każdym roku; numery nie są używane ponownie.</p>
      </section>
      <div class="flex justify-end"><x-gst.button type="submit">Zapisz</x-gst.button></div>
    </form>
  @endif

  @if ($section === 'typy-podstron')
    <section class="rounded-panel border border-line bg-surface shadow-panel">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
        <div>
          <h2 class="text-lg font-semibold">Typy podstron</h2>
          <p class="mt-1 text-ink-soft">Domyślne widełki godzin szablonu (UI i Development) oraz wprowadzania treści na sztukę. UX liczony jest osobno, z zakresu UX.</p>
        </div>
        <x-gst.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'page-type-create')">Nowy typ</x-gst.button>
      </div>
      <div class="relative overflow-x-auto">
        <table class="w-full min-w-max text-left">
          <thead class="text-sm text-ink-soft"><tr class="border-b border-line"><th class="px-6 py-2.5 font-medium">Nazwa</th><th class="px-3 py-2.5 font-medium">Rodzaj</th><th class="px-3 py-2.5 text-right font-medium">UI</th><th class="px-3 py-2.5 text-right font-medium">Development</th><th class="px-3 py-2.5 text-right font-medium">Treść / szt.</th><th class="px-3 py-2.5 font-medium">Status</th><th class="px-6 py-2.5"><span class="sr-only">Akcje</span></th></tr></thead>
          <tbody class="divide-y divide-line">
            @foreach ($pageTypes as $type)
              <tr @class(['text-ink-muted' => ! $type->is_active])>
                <td class="px-6 py-2.5 font-medium">{{ $type->name }}</td>
                <td class="px-3 py-2.5">{{ $type->kindLabel() }}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $F::hoursRange(['min' => (string) $type->ui_min, 'max' => (string) $type->ui_max]) }}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $F::hoursRange(['min' => (string) $type->dev_min, 'max' => (string) $type->dev_max]) }}</td>
                <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ $F::hoursRange(['min' => (string) $type->content_min, 'max' => (string) $type->content_max]) }}</td>
                <td class="px-3 py-2.5">{{ $type->is_active ? 'Aktywny' : 'Nieaktywny' }}</td>
                <td class="px-6 py-2.5 text-right"><button type="button" class="rounded-control px-2 py-1 text-accent hover:bg-accent-soft" x-data x-on:click="$dispatch('open-modal', 'page-type-{{ $type->id }}')">Edytuj</button></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    @foreach (array_merge([null], $pageTypes->all()) as $type)
      @php
        $modalId = $type ? 'page-type-' . $type->id : 'page-type-create';
        $isOld = isset($old) && $openModal === $modalId;
        $pv = fn ($key) => $isOld ? ($old[$key] ?? null) : (in_array($key, ['name', 'kind'], true) ? $type?->{$key} : $F::hoursInput($type?->{$key}));
        $pe = fn ($key) => $isOld ? ($errors[$key] ?? null) : null;
      @endphp
      <x-gst.modal :id="$modalId" :title="$type ? 'Edytuj typ podstrony' : 'Nowy typ podstrony'" size="lg" :open="$openModal === $modalId">
        <form method="post" action="{{ $type ? route('gst.settings.page-types.update', $type) : route('gst.settings.page-types.store') }}" class="space-y-5" novalidate>
          <x-gst.csrf />
          <div class="grid gap-4 sm:grid-cols-2">
            <x-gst.field label="Nazwa" :for="$modalId . '-name'" :error="$pe('name')" required>
              <x-gst.input name="name" :id="$modalId . '-name'" :value="$pv('name')" :error="$pe('name')" maxlength="150" />
            </x-gst.field>
            <x-gst.field label="Rodzaj" :for="$modalId . '-kind'" :error="$pe('kind')" required>
              <x-gst.select name="kind" :id="$modalId . '-kind'" :options="\App\Guesstimate\Library\PageType::KINDS" :value="$pv('kind') ?? 'template'" />
            </x-gst.field>
            @foreach (['ui' => 'UI Design', 'dev' => 'Development', 'content' => 'Wprowadzanie treści (na sztukę)'] as $group => $label)
              <fieldset class="grid grid-cols-2 gap-3 sm:col-span-2">
                <legend class="mb-1 font-medium">{{ $label }}</legend>
                <x-gst.field label="Minimum" :for="$modalId . '-' . $group . '-min'" :error="$pe($group . '_min')">
                  <x-gst.input :name="$group . '_min'" :id="$modalId . '-' . $group . '-min'" :value="$pv($group . '_min') ?? '0'" :error="$pe($group . '_min')" suffix="h" inputmode="decimal" />
                </x-gst.field>
                <x-gst.field label="Maksimum" :for="$modalId . '-' . $group . '-max'" :error="$pe($group . '_max')">
                  <x-gst.input :name="$group . '_max'" :id="$modalId . '-' . $group . '-max'" :value="$pv($group . '_max') ?? '0'" :error="$pe($group . '_max')" suffix="h" inputmode="decimal" />
                </x-gst.field>
              </fieldset>
            @endforeach
            <div class="sm:col-span-2">
              <x-gst.checkbox name="is_active" label="Aktywny" hint="Nieaktywne typy nie są dostępne w edytorze mapy strony." :checked="$isOld ? ! empty($old['is_active']) : ($type?->is_active ?? true)" />
            </div>
          </div>
          <div class="flex justify-end gap-2">
            <x-gst.button variant="ghost" x-on:click="$dispatch('close-modal', '{{ $modalId }}')">Anuluj</x-gst.button>
            <x-gst.button type="submit">Zapisz</x-gst.button>
          </div>
        </form>
      </x-gst.modal>
    @endforeach
  @endif
@endsection
