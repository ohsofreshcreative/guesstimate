{{-- Dane firmy do tokenów umowy. --}}
@php($c = $settings['contractor'] ?? [])
<form method="post" action="{{ route('gst.settings.update', ['section' => 'wykonawca']) }}" class="max-w-2xl space-y-6">
  <x-gst.csrf />
  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Dane wykonawcy</h2>
    <p class="mt-1 text-ink-soft">Trafiają do nowych umów. Istniejące umowy mają własną kopię i zmieniają ją tylko po świadomym odświeżeniu.</p>
    <div class="mt-5 grid gap-5">
      @foreach (['company_name' => 'Nazwa firmy', 'address' => 'Adres', 'nip' => 'NIP', 'representative' => 'Reprezentant'] as $field => $text)
        <x-gst.field :label="$text" :for="'f-contractor-' . $field" :error="$errors['contractor.' . $field] ?? null">
          <x-gst.input :name="'contractor[' . $field . ']'" :id="'f-contractor-' . $field" :value="$o('contractor.' . $field, $c[$field] ?? '')" :error="$errors['contractor.' . $field] ?? null" />
        </x-gst.field>
      @endforeach
    </div>
  </section>
  <x-gst.button type="submit">Zapisz</x-gst.button>
</form>
