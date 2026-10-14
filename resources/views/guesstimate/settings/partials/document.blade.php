{{-- Domyślne treści dokumentu oferty i domyślne tła stron. --}}
@php
  $d = $settings['document'] ?? [];
  $includes = implode("\n", (array) ($d['price_includes'] ?? []));
  $extras = array_pad(array_values((array) ($d['extras'] ?? [])), 4, ['name' => '', 'description' => '', 'price' => '']);
  $steps = array_pad(array_values((array) ($d['next_steps'] ?? [])), 5, ['title' => '', 'note' => '']);
  $bgOptions = collect($backgrounds)->pluck('name', 'id')->all();
@endphp
<form method="post" action="{{ route('gst.settings.update', ['section' => 'dokument']) }}" enctype="multipart/form-data" class="max-w-5xl space-y-6" novalidate>
  <x-gst.csrf />

  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Domyślne tła stron</h2>
    <p class="mt-1 text-ink-soft">Przypisywane automatycznie nowym ofertom. Zmiana nie wpływa na istniejące ani opublikowane oferty. Grafiki dodasz w zakładce <a href="{{ route('gst.settings.index', ['section' => 'tla']) }}" class="text-accent underline">Biblioteka teł</a>.</p>
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      @foreach ($docPages as $key => $label)
        <x-gst.field :label="$loop->iteration . '. ' . $label" :for="'f-bg-' . $key">
          <x-gst.select :name="'backgrounds[' . $key . ']'" :id="'f-bg-' . $key" :options="$bgOptions" :value="$o('backgrounds.' . $key, $d['backgrounds'][$key] ?? '')" placeholder="Styl domyślny (bez grafiki)" />
        </x-gst.field>
      @endforeach
    </div>
  </section>

  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Okładka i stopka</h2>
    <div class="mt-5 grid gap-5 sm:grid-cols-2">
      <x-gst.field label="Nagłówek okładki" for="f-cover-heading" :error="$errors['document.cover_heading'] ?? null" class="sm:col-span-2" hint="Słowo w **gwiazdkach** będzie pomarańczowe.">
        <x-gst.input name="document[cover_heading]" id="f-cover-heading" :value="$o('document.cover_heading', $d['cover_heading'] ?? '')" maxlength="160" />
      </x-gst.field>
      <x-gst.field label="Krótki opis na okładce" for="f-cover-lead" class="sm:col-span-2">
        <x-gst.textarea name="document[cover_lead]" id="f-cover-lead" :value="$o('document.cover_lead', $d['cover_lead'] ?? '')" rows="2" />
      </x-gst.field>
      <x-gst.field label="Adres strony agencji" for="f-website">
        <x-gst.input name="website" id="f-website" :value="$o('website', $d['website'] ?? '')" />
      </x-gst.field>
      <x-gst.field label="Ważność oferty (dni)" for="f-validity" :error="$errors['validity_days'] ?? null" hint="0 = nie pokazuj">
        <x-gst.input type="number" name="validity_days" id="f-validity" min="0" max="365" :value="$o('validity_days', $d['validity_days'] ?? 30)" />
      </x-gst.field>
      <div class="sm:col-span-2">
        <p class="mb-1.5 font-medium">Logo w dokumencie (jasne, na ciemne tło)</p>
        <div class="flex flex-wrap items-center gap-4">
          @if ($logoThumb)<img src="{{ $logoThumb }}" alt="" class="h-10 w-auto rounded bg-ink p-1.5">@else<span class="text-sm text-ink-muted">Domyślne logo oh!sofresh</span>@endif
          <input type="hidden" name="logo_attachment_id" value="{{ $d['logo_attachment_id'] ?? '' }}">
          <input type="file" name="logo" accept="image/png,image/webp,image/jpeg" class="text-sm" aria-label="Prześlij logo">
          @if ($logoThumb)<x-gst.checkbox name="logo_remove" label="Przywróć logo domyślne" />@endif
        </div>
        @if (! empty($errors['logo']))<p class="mt-1 text-sm text-danger">{{ $errors['logo'] }}</p>@endif
      </div>
    </div>
  </section>

  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Wycena: „Cena zawiera”</h2>
    <x-gst.field label="Jedna pozycja w wierszu" for="f-includes" :error="$errors['document.price_includes'] ?? null" class="mt-4">
      <x-gst.textarea name="document[price_includes]" id="f-includes" :value="$o('document.price_includes', $includes)" rows="8" />
    </x-gst.field>
  </section>

  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Usługi dodatkowe (domyślne)</h2>
    <p class="mt-1 text-ink-soft">Ceny to tekst (np. „+15%”, „100–120 zł / 1000 zzs”) — nigdy nie są doliczane do ceny projektu.</p>
    <x-gst.field label="Wstęp" for="f-extras-intro" class="mt-4">
      <x-gst.textarea name="document[extras_intro]" id="f-extras-intro" :value="$o('document.extras_intro', $d['extras_intro'] ?? '')" rows="3" />
    </x-gst.field>
    <div class="mt-4 space-y-3">
      @foreach ($extras as $i => $item)
        <div class="grid gap-3 rounded-control border border-line p-3 md:grid-cols-3">
          <x-gst.input :name="'document[extras][' . $i . '][name]'" :value="$o('document.extras.' . $i . '.name', $item['name'])" placeholder="Nazwa usługi" aria-label="Nazwa usługi {{ $i + 1 }}" />
          <x-gst.input :name="'document[extras][' . $i . '][price]'" :value="$o('document.extras.' . $i . '.price', $item['price'])" placeholder="Cena (tekst)" aria-label="Cena usługi {{ $i + 1 }}" />
          <x-gst.textarea :name="'document[extras][' . $i . '][description]'" :id="'f-extra-desc-' . $i" :value="$o('document.extras.' . $i . '.description', $item['description'])" rows="2" placeholder="Opis — każda linia to punkt" class="md:col-span-3" aria-label="Opis usługi {{ $i + 1 }}" />
        </div>
      @endforeach
    </div>
  </section>

  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Realizacje i kolejne kroki</h2>
    <x-gst.field label="Wstęp do realizacji („W kooperacji siła”)" for="f-ref-intro" class="mt-4" hint="**pogrubienie**">
      <x-gst.textarea name="document[references_intro]" id="f-ref-intro" :value="$o('document.references_intro', $d['references_intro'] ?? '')" rows="3" />
    </x-gst.field>
    <p class="mb-1.5 mt-5 font-medium">Kolejne kroki</p>
    <div class="space-y-2">
      @foreach ($steps as $i => $step)
        <div class="grid gap-2 md:grid-cols-3">
          <x-gst.input :name="'document[next_steps][' . $i . '][title]'" :value="$o('document.next_steps.' . $i . '.title', $step['title'])" placeholder="Krok {{ $i + 1 }}" class="md:col-span-2" aria-label="Krok {{ $i + 1 }}" />
          <x-gst.input :name="'document[next_steps][' . $i . '][note]'" :value="$o('document.next_steps.' . $i . '.note', $step['note'])" placeholder="Dopisek, np. (1–3 dni)" aria-label="Dopisek kroku {{ $i + 1 }}" />
        </div>
      @endforeach
    </div>
    <x-gst.field label="Wyróżniona informacja pod krokami" for="f-steps-note" class="mt-4" hint="**pomarańczowy fragment**">
      <x-gst.input name="document[next_steps_note]" id="f-steps-note" :value="$o('document.next_steps_note', $d['next_steps_note'] ?? '')" />
    </x-gst.field>
  </section>

  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Kontakt domyślny</h2>
    <p class="mt-1 text-ink-soft">Na stronie „Kontakt” pokazujemy opiekuna oferty (imię i e-mail z konta, stanowisko i telefon z zakładki Użytkownicy). Te dane uzupełniają brakujące pola albo zastępują opiekuna bez konta.</p>
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      @foreach (['name' => 'Imię i nazwisko', 'role' => 'Stanowisko', 'phone' => 'Telefon', 'email' => 'E-mail'] as $field => $label)
        <x-gst.field :label="$label" :for="'f-contact-' . $field" :error="$errors['contact.' . $field] ?? null">
          <x-gst.input :name="'contact[' . $field . ']'" :id="'f-contact-' . $field" :value="$o('contact.' . $field, $d['contact'][$field] ?? '')" />
        </x-gst.field>
      @endforeach
    </div>
  </section>

  <div class="flex justify-end"><x-gst.button type="submit">Zapisz ustawienia dokumentu</x-gst.button></div>
</form>
