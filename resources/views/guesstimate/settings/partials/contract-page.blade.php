{{-- Układ strony umów jak w Wordzie: marginesy i odległość nagłówka i stopki od krawędzi kartki. --}}
@php($p = \App\Guesstimate\Contracts\PageSetup::from((array) ($settings['contract_page'] ?? []))->values)
<form method="post" action="{{ route('gst.settings.update', ['section' => 'umowy']) }}" class="max-w-2xl space-y-6">
  <x-gst.csrf />
  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Układ strony umów</h2>
    <p class="mt-1 text-ink-soft">Wartości w mm, jak w Wordzie (Układ → Marginesy). Treść zaczyna się na górnym marginesie, a gdy nagłówek jest wyższy — 3 mm pod nim, więc nagłówek, stopka i tło nie nachodzą na tekst. Dotyczy edytora, podglądu, PDF i DOCX wszystkich umów; treść umów się nie zmienia.</p>
    <p class="mt-2 text-sm text-ink-muted">Przy papierze firmowym ustaw marginesy tak, by omijały logo i stopkę z grafiki tła.</p>
    <div class="mt-5 grid gap-5 sm:grid-cols-2">
      @foreach (\App\Guesstimate\Contracts\PageSetup::FIELDS as $field => $text)
        <x-gst.field :label="$text . ' (mm)'" :for="'f-page-' . $field" :error="$errors['page.' . $field] ?? null">
          <x-gst.input :name="'page[' . $field . ']'" :id="'f-page-' . $field" inputmode="decimal" :value="$o('page.' . $field, rtrim(rtrim(number_format($p[$field], 1, ',', ''), '0'), ','))" :error="$errors['page.' . $field] ?? null" />
        </x-gst.field>
      @endforeach
    </div>
  </section>
  <x-gst.button type="submit">Zapisz</x-gst.button>
</form>
