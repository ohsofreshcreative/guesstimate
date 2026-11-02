@extends('guesstimate.layouts.app')

@section('title', 'Harmonogram płatności – ' . $contract->title)

@php
  $errors ??= [];
  $F = \App\Guesstimate\Support\Format::class;
  $value = fn ($key) => isset($old) ? data_get($old, $key) : $form[$key];
  $rows = isset($old) ? array_values((array) data_get($old, 'stages')) : $form['stages'];
  $money = fn ($amount) => $F::money($amount, $summary['currency']);
  $control = 'block w-full rounded-control border-line-strong px-2 py-1.5 text-sm';
@endphp

@section('content')
  <div class="mb-2">
    <a href="{{ route('gst.contracts.show', $contract) }}" class="text-ink-soft hover:text-ink">← {{ $contract->title }}</a>
  </div>
  <x-gst.page-header title="Harmonogram płatności" description="Kwotę umowy ustalają strony. Cena z oferty jest tylko orientacyjna i nie wypełnia niczego sama." />

  <form method="post" action="{{ route('gst.contracts.payments.save', $contract) }}" class="space-y-6">
    <x-gst.csrf />
    <input type="hidden" name="expected" value="{{ $expected }}">

    <fieldset class="space-y-6" @disabled(! $editable)>
      <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
        <h2 class="text-lg font-semibold">Kwota</h2>
        <div class="mt-5 grid gap-5 sm:grid-cols-3">
          <x-gst.field label="Cena orientacyjna z oferty" for="f-estimate" hint="Wybrany wariant, tylko informacyjnie.">
            <p id="f-estimate" class="py-2 text-ink">{{ $estimate ?? 'Brak' }}</p>
          </x-gst.field>
          <x-gst.field label="Uzgodniona kwota umowy netto" for="f-agreed" :error="$errors['agreed_net'] ?? null">
            <x-gst.input name="agreed_net" id="f-agreed" inputmode="decimal" :value="$value('agreed_net')" :error="$errors['agreed_net'] ?? null" />
          </x-gst.field>
          <x-gst.field label="Stawka VAT (%)" for="f-vat" :error="$errors['vat_rate'] ?? null">
            <x-gst.input name="vat_rate" id="f-vat" inputmode="decimal" :value="$value('vat_rate')" :error="$errors['vat_rate'] ?? null" />
          </x-gst.field>
        </div>
      </section>

      <section class="rounded-panel border border-line bg-surface shadow-panel" x-data="rowList(@js($rows), @js(['name' => '', 'milestone' => '', 'net' => '', 'percent' => '', 'due' => '']))">
        <div class="flex items-center justify-between gap-3 border-b border-line px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold">Etapy</h2>
            <p class="text-sm text-ink-muted">VAT i brutto liczą się po zapisaniu. Procent jest opcjonalnym zapisem z umowy i nie zmienia kwot.</p>
          </div>
          <x-gst.button icon="plus" variant="secondary" x-on:click="add()">Dodaj etap</x-gst.button>
        </div>
        <template x-if="rows.length === 0"><p class="px-6 py-5 text-ink-muted">Brak etapów.</p></template>
        <ul class="divide-y divide-line">
          <template x-for="(row, index) in rows" :key="index">
            <li data-row class="grid gap-3 px-6 py-3 lg:grid-cols-12">
              <label class="lg:col-span-3"><span class="sr-only">Nazwa etapu</span><input type="text" data-first :name="`stages[${index}][name]`" x-model="row.name" placeholder="Nazwa etapu" class="{{ $control }}"></label>
              <label class="lg:col-span-3"><span class="sr-only">Kamień milowy</span><input type="text" :name="`stages[${index}][milestone]`" x-model="row.milestone" placeholder="Kamień milowy" class="{{ $control }}"></label>
              <label class="lg:col-span-2"><span class="sr-only">Netto</span><input type="text" inputmode="decimal" :name="`stages[${index}][net]`" x-model="row.net" placeholder="Netto" class="{{ $control }}"></label>
              <label class="lg:col-span-1"><span class="sr-only">Procent</span><input type="text" inputmode="decimal" :name="`stages[${index}][percent]`" x-model="row.percent" placeholder="%" class="{{ $control }}"></label>
              <label class="lg:col-span-2"><span class="sr-only">Termin</span><input type="text" :name="`stages[${index}][due]`" x-model="row.due" placeholder="Termin" class="{{ $control }}"></label>
              <button type="button" class="text-sm text-ink-soft hover:text-danger lg:col-span-1" x-on:click="remove(index)">Usuń</button>
            </li>
          </template>
        </ul>
        @if (collect($errors)->keys()->contains(fn ($key) => str_starts_with($key, 'stages')))
          <ul class="border-t border-line px-6 py-3 text-sm text-danger" role="alert">
            @foreach ($errors as $key => $message)
              @if (preg_match('/^stages\.(\d+)/', $key, $match))
                <li>Etap {{ $match[1] + 1 }}: {{ $message }}</li>
              @elseif (str_starts_with($key, 'stages'))
                <li>{{ $message }}</li>
              @endif
            @endforeach
          </ul>
        @endif
      </section>

      <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
        <h2 class="text-lg font-semibold">Zadatek</h2>
        <p class="mt-1 text-sm text-ink-muted">Opcjonalny. Sposób rozliczenia wybierasz sam, nie ma wartości domyślnej.</p>
        <div class="mt-5 grid gap-5 sm:grid-cols-4">
          <x-gst.field label="Kwota netto" for="f-deposit" :error="$errors['deposit_net'] ?? null">
            <x-gst.input name="deposit_net" id="f-deposit" inputmode="decimal" :value="$value('deposit_net')" :error="$errors['deposit_net'] ?? null" />
          </x-gst.field>
          <x-gst.field label="Rozliczenie" for="f-deposit-mode">
            <x-gst.select name="deposit_mode" id="f-deposit-mode" :options="['' => 'Wybierz…'] + \App\Guesstimate\Contracts\PaymentSchedule::MODES" :value="$value('deposit_mode')" />
          </x-gst.field>
          <x-gst.field label="Numer etapu" for="f-deposit-stage" hint="Tylko przy zaliczeniu." :error="$errors['deposit_stage'] ?? null">
            <x-gst.input name="deposit_stage" id="f-deposit-stage" inputmode="numeric" :value="$value('deposit_stage')" :error="$errors['deposit_stage'] ?? null" />
          </x-gst.field>
          <x-gst.field label="Termin" for="f-deposit-due" :error="$errors['deposit_due'] ?? null">
            <x-gst.input name="deposit_due" id="f-deposit-due" :value="$value('deposit_due')" :error="$errors['deposit_due'] ?? null" />
          </x-gst.field>
        </div>
      </section>
    </fieldset>

    @if ($editable)
      <x-gst.button type="submit">Zapisz harmonogram</x-gst.button>
    @else
      <x-gst.alert>Ta wersja jest tylko do odczytu.</x-gst.alert>
    @endif
  </form>

  @if ($summary['agreed'] || $summary['stages'])
    <section class="mt-6 rounded-panel border border-line bg-surface p-6 shadow-panel" aria-labelledby="gst-settlement">
      <h2 id="gst-settlement" class="text-lg font-semibold">Rozliczenie</h2>
      <div class="relative mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="text-ink-muted">
            <tr>
              <th class="py-2 pr-4 font-medium">Pozycja</th>
              <th class="py-2 pr-4 font-medium">Netto</th>
              <th class="py-2 pr-4 font-medium">VAT</th>
              <th class="py-2 pr-4 font-medium">Brutto</th>
              <th class="py-2 pr-4 font-medium">% z kwot</th>
              <th class="py-2 pr-4 font-medium">% w umowie</th>
              <th class="py-2 font-medium">Do zapłaty</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            @foreach ($summary['stages'] as $stage)
              <tr>
                <td class="py-2 pr-4">{{ $stage['name'] }}</td>
                <td class="py-2 pr-4">{{ $money($stage['net']) }}</td>
                <td class="py-2 pr-4">{{ $money($stage['vat']) }}</td>
                <td class="py-2 pr-4">{{ $money($stage['gross']) }}</td>
                <td class="py-2 pr-4">{{ $stage['share'] !== null ? $F::percent($stage['share']) : '–' }}</td>
                <td class="py-2 pr-4">{{ $stage['percent'] !== null ? $F::percent($stage['percent']) : '–' }}</td>
                <td class="py-2">{{ $money($stage['payable']) }}</td>
              </tr>
            @endforeach
            @if ($summary['deposit'])
              <tr>
                <td class="py-2 pr-4">Zadatek</td>
                <td class="py-2 pr-4">{{ $money($summary['deposit']['net']) }}</td>
                <td class="py-2 pr-4">{{ $money($summary['deposit']['vat']) }}</td>
                <td class="py-2 pr-4">{{ $money($summary['deposit']['gross']) }}</td>
                <td class="py-2 pr-4">–</td>
                <td class="py-2 pr-4">–</td>
                <td class="py-2">{{ $money($summary['deposit']['gross']) }}</td>
              </tr>
            @endif
            <tr class="font-semibold" data-settlement-total>
              <td class="py-2 pr-4">Razem płatności</td>
              <td class="py-2 pr-4">{{ $money($summary['paid']['net']) }}</td>
              <td class="py-2 pr-4">{{ $money($summary['paid']['gross'] - $summary['paid']['net']) }}</td>
              <td class="py-2 pr-4">{{ $money($summary['paid']['gross']) }}</td>
              <td class="py-2 pr-4"></td>
              <td class="py-2 pr-4"></td>
              <td class="py-2">{{ $money($summary['paid']['gross']) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      @include('guesstimate.contracts.partials.payment-notices', ['summary' => $summary])
    </section>
  @endif
@endsection
