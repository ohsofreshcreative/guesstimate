{{-- Wzór oferty pakietów support: teksty stron, pakiety i punkty. Nowa oferta support dostaje kopię, a zmiany wzoru nie zmieniają zapisanych ofert.
     Stan w Alpine (supportTemplate), zapis zwykłym formularzem: cały wzór jako JSON w polu „payload”. --}}
@php
  use App\Guesstimate\Offers\SupportPackages;
  $btn = 'inline-flex items-center justify-center gap-2 rounded-control px-3 py-1.5 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50';
  $secondary = $btn . ' border border-line-strong bg-surface text-ink hover:bg-canvas';
  $input = 'block w-full rounded-control border-line-strong px-3 py-1.5 text-sm focus:border-accent focus:ring-accent';
  $label = 'mb-1 block text-sm font-medium';
  // Po błędzie walidacji wracamy do wysłanego stanu (ceny są już tekstem pola).
  $sent = isset($old['payload']) ? json_decode((string) $old['payload'], true) : null;
  $stored = SupportPackages::template($settings);
  $template = is_array($sent) ? $sent : array_merge($stored, SupportPackages::editorState($stored));
  $texts = [
    'packages' => ['Strona pakietów', 'f-support-packages'],
    'legend' => ['Strona legendy', 'f-support-legend'],
  ];
@endphp
<form method="post" action="{{ route('gst.settings.update', ['section' => 'pakiety']) }}" class="max-w-6xl space-y-6"
  x-data="supportTemplate(@js(['template' => $template, 'errors' => $errors]))" novalidate>
  <x-gst.csrf />
  <input type="hidden" name="payload" :value="JSON.stringify(template)">

  <section class="rounded-panel border border-line bg-surface p-6 shadow-panel">
    <h2 class="text-lg font-semibold">Pakiety support — wzór oferty</h2>
    <p class="mt-1 text-ink-soft">Nowa oferta support (Oferty → „Oferta support”) dostaje kopię tego wzoru: okładkę, stronę pakietów, legendę i kontakt. W ofercie zmienisz wszystko w edytorze dokumentu; zmiana wzoru nie zmienia zapisanych ofert.</p>
    @if (! empty($errors['payload']))<p class="mt-2 text-sm text-danger">{{ $errors['payload'] }}</p>@endif

    <div class="mt-6 grid gap-5 lg:grid-cols-2">
      <div>
        <label class="{{ $label }}" for="f-support-cover-heading">Nagłówek okładki</label>
        <input type="text" id="f-support-cover-heading" class="{{ $input }}" x-model="template.cover_heading" maxlength="160">
        <p class="mt-1 text-xs text-ink-muted">**słowo** = pomarańczowe.</p>
        @if (! empty($errors['cover_heading']))<p class="mt-1 text-sm text-danger">{{ $errors['cover_heading'] }}</p>@endif
      </div>
      <div>
        <label class="{{ $label }}" for="f-support-cover-lead">Krótki opis na okładce</label>
        <textarea id="f-support-cover-lead" rows="3" class="{{ $input }}" x-model="template.cover_lead" maxlength="400"></textarea>
      </div>
      @foreach ($texts as $prefix => [$title, $id])
        <div class="min-w-0">
          <label class="{{ $label }}" for="{{ $id }}-heading">{{ $title }} — nagłówek</label>
          <input type="text" id="{{ $id }}-heading" class="{{ $input }}" x-model="template.{{ $prefix }}_heading" maxlength="160">
          <p class="mt-1 text-xs text-ink-muted">**słowo** = pomarańczowe.</p>
          <p class="{{ $label }} mt-3">{{ $title }} — wstęp</p>
          <div x-data="richText()" x-modelable="html" x-model="template.{{ $prefix }}_intro" class="min-w-0">
            @include('guesstimate.partials.rich-text-body', ['label' => $title . ' — wstęp', 'id' => $id . '-intro', 'headings' => true])
          </div>
          @if (! empty($errors[$prefix . '_intro']))<p class="mt-1 text-sm text-danger">{{ $errors[$prefix . '_intro'] }}</p>@endif
        </div>
      @endforeach
    </div>
  </section>

  <div class="grid gap-6 lg:grid-cols-2">
    <section class="min-w-0 rounded-panel border border-line bg-surface p-6 shadow-panel">
      @include('guesstimate.partials.support-editor', ['model' => 'template', 'parts' => ['packages']])
    </section>
    <section class="min-w-0 rounded-panel border border-line bg-surface p-6 shadow-panel">
      @include('guesstimate.partials.support-editor', ['model' => 'template', 'parts' => ['points']])
    </section>
  </div>

  <x-gst.button type="submit">Zapisz wzór</x-gst.button>
</form>
