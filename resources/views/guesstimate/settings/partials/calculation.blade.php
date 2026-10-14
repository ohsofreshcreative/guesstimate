{{-- Ustawienia modelu budżetowego. --}}
<form method="post" action="{{ route('gst.settings.update', ['section' => 'kalkulacja']) }}" class="max-w-5xl space-y-6" novalidate>
  <x-gst.csrf />

  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Cena i jej podział</h2>
    <p class="mt-1 text-ink-soft">Cena wariantu = godziny × stawka. Warsztaty Discovery są kwotą stałą zawartą w cenie, a resztę pokazujemy jako podział na UX, UI i Development — to prezentacja budżetu, a nie dodatkowe naliczenie.</p>
    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
      <x-gst.field label="Warsztaty Discovery" for="f-workshops_amount" :error="$errors['workshops_amount'] ?? null" hint="0 = bez warsztatów">
        <x-gst.input name="workshops_amount" :value="$o('workshops_amount', $F::moneyInput((int) $settings['workshops_amount']))" :error="$errors['workshops_amount'] ?? null" suffix="zł" inputmode="decimal" />
      </x-gst.field>
      @foreach (['ux' => 'UX Design', 'ui' => 'UI Design', 'dev' => 'Development i wdrożenie'] as $key => $label)
        <x-gst.field :label="$label" :for="'f-split-' . $key" :error="$errors['split.' . $key] ?? null">
          <x-gst.input :name="'split[' . $key . ']'" :id="'f-split-' . $key" :value="$o('split.' . $key, $F::hoursInput($settings['budget_split'][$key] ?? '0'))" :error="$errors['split.' . $key] ?? null" suffix="%" inputmode="decimal" />
        </x-gst.field>
      @endforeach
    </div>
    @if (! empty($errors['split']))<p class="mt-2 text-sm text-danger">{{ $errors['split'] }}</p>@endif
    <p class="mt-3 text-sm text-ink-muted">Przykład: 20 000 zł − 2 000 zł warsztatów = 18 000 zł → UX 3 600 zł, UI 5 400 zł, Development 9 000 zł.</p>
  </section>

  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Godziny na podstronę</h2>
    <p class="mt-1 text-ink-soft">Każda podstrona z mapy i każda jednostka rodzaju projektu dostaje te godziny (w Excelu 4–6 h na podstronę). Pojedyncze podstrony można nadpisać w ofercie.</p>
    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
      @foreach (['ui_min' => 'UI minimum', 'ui_max' => 'UI maksimum', 'dev_min' => 'Dev minimum', 'dev_max' => 'Dev maksimum'] as $key => $label)
        <x-gst.field :label="$label" :for="'f-ph-' . $key" :error="$errors['page_hours.' . $key] ?? null">
          <x-gst.input :name="'page_hours[' . $key . ']'" :id="'f-ph-' . $key" :value="$o('page_hours.' . $key, $F::hoursInput($settings['page_hours'][$key] ?? '0'))" :error="$errors['page_hours.' . $key] ?? null" suffix="h" inputmode="decimal" />
        </x-gst.field>
      @endforeach
    </div>
  </section>

  <section class="rounded-panel border border-line bg-surface shadow-panel">
    <div class="px-6 pt-6">
      <h2 class="text-lg font-semibold">Rodzaje projektu</h2>
      <p class="mt-1 text-ink-soft">Dodatkowe jednostki kalkulacyjne (calc.xlsx: katalog +5, sklep +8; dla „Inny” liczbę jednostek wpisuje się w ofercie). To nie są adresy URL — w wycenie są osobną pozycją, liczoną godzinami na podstronę.</p>
    </div>
    <div class="relative mt-4 overflow-x-auto">
      <table class="w-full min-w-max text-left">
        <thead class="text-sm text-ink-soft"><tr class="border-y border-line"><th class="px-6 py-2 font-medium">Nazwa w ofercie</th><th class="px-6 py-2 font-medium">Dodatkowe jednostki</th></tr></thead>
        <tbody class="divide-y divide-line">
          @foreach ($projectTypes as $key => $type)
            <tr>
              <td class="px-6 py-3">
                <label for="f-pt-{{ $key }}" class="sr-only">Nazwa: {{ $type['label'] }}</label>
                <x-gst.input :name="'project_types[' . $key . '][label]'" :id="'f-pt-' . $key" :value="$o('project_types.' . $key . '.label', $type['label'])" :error="$errors['project_types.' . $key . '.label'] ?? null" maxlength="60" class="w-64" />
              </td>
              <td class="px-6 py-3">
                <label for="f-pu-{{ $key }}" class="sr-only">Jednostki: {{ $type['label'] }}</label>
                @if ($key === \App\Guesstimate\Offers\OfferVersion::CUSTOM_UNITS_TYPE)
                  <span class="text-sm text-ink-muted">wpisywana w ofercie (domyślnie 0)</span>
                @else
                  <x-gst.input :name="'project_types[' . $key . '][units]'" :id="'f-pu-' . $key" :value="$o('project_types.' . $key . '.units', $F::hoursInput($type['units']))" :error="$errors['project_types.' . $key . '.units'] ?? null" inputmode="decimal" class="w-32" />
                @endif
                @if (! empty($errors['project_types.' . $key . '.units']))<p class="mt-1 text-sm text-danger">{{ $errors['project_types.' . $key . '.units'] }}</p>@endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <div class="flex justify-end"><x-gst.button type="submit">Zapisz ustawienia kalkulacji</x-gst.button></div>
</form>
