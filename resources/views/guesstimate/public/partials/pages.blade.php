{{-- Strony dokumentu. $offer (DocumentView::fromSnapshot), $mode: public | editor | thumbs.
     W edytorze elementy mają data-edit (kliknięcie otwiera właściwe pole w panelu) i widać strony ukryte. --}}
@php
  $mode ??= 'public';
  $editor = $mode !== 'public';
  $doc = $offer['document'];
  $logo = $doc['logo'] ?: \Illuminate\Support\Facades\Vite::asset('resources/images/gst/osf-logo.png');
  $chevrons = ['text-orange', 'text-flame', 'text-magenta', 'text-indigo', 'text-violet', 'text-orange'];
  $edit = fn (string $field) => $editor ? new \Illuminate\Support\HtmlString('data-edit="' . e($field) . '"') : '';
  $hiddenNotes = ['disabled' => 'Strona wyłączona — klient jej nie zobaczy.', 'empty' => 'Strona bez treści — klient jej nie zobaczy, dopóki jej nie uzupełnisz.'];
@endphp
@foreach ($doc['pages'] as $i => $page)
  @php
    // Bez wybranego zdjęcia treść zajmuje całą szerokość — także w edytorze (pomiar jak u klienta);
    // edytor pokazuje wtedy tylko przycisk „+ Zdjęcie strony”.
    $type = $page['type'] ?? $page['key']; // strona dodatkowa (własna albo kopia) ma szablon swojego typu
    $slot = \App\Guesstimate\Document\Pages::hasMedia($page['layout']);
    $media = $slot && $page['image'];
    $side = $page['layout'] === 'image_text' ? 'left' : 'right';
    // Szablony oznaczają pola przez $edit, więc pozycja z układu trafia tam bez zmian w szablonach stron.
    $place = fn (string $field) => new \Illuminate\Support\HtmlString(trim($edit($field) . ' ' . \App\Guesstimate\Document\FieldLayout::attributes($page['fields'] ?? null, $field)));
  @endphp
  <section id="page-{{ $page['key'] }}"
    @class(['osf-page', 'osf-layout-' . $page['layout'], 'osf-media-' . $side => $media])
    data-doc-page data-page-key="{{ $page['key'] }}" data-page-label="{{ $page['label'] }}"
    @if (in_array($page['key'], \App\Guesstimate\Document\Pages::FIT, true)) data-fit data-fit-level="0" @endif
    @if ($editor && $page['hidden']) data-hidden="{{ $page['hidden'] }}" @endif
    {{ $edit('background') }}
    aria-label="{{ $page['label'] }}"@if (! empty($page['fields'])) data-free @endif>
    <div class="osf-page-bg" aria-hidden="true">
      @if ($page['background'])
        <img src="{{ $page['background']['url'] }}"
          @if ($page['background']['srcset']) srcset="{{ $page['background']['srcset'] }}" sizes="(min-width: 1312px) 1280px, 100vw" @endif
          width="{{ $page['background']['width'] }}" height="{{ $page['background']['height'] }}"
          alt="" loading="{{ $i < 2 || $editor ? 'eager' : 'lazy' }}" decoding="async" @if ($i === 0) fetchpriority="high" @endif
          @class(['osf-bg-image', 'object-cover' => $page['fit'] !== 'contain', 'object-contain' => $page['fit'] === 'contain'])>
      @else
        <div class="osf-glow absolute inset-0"></div>
        <div class="osf-gradient osf-bar-top"></div>
        @if ($type === 'cover')<div class="osf-gradient osf-bar-bottom"></div>@endif
      @endif
    </div>

    @if ($media)
      <figure class="osf-media"@if (! empty($page['polish']['image_fit'])) data-image-fit="contain"@endif {{ $edit('image') }}>
        <img src="{{ $page['image']['url'] }}"
          @if ($page['image']['srcset']) srcset="{{ $page['image']['srcset'] }}" sizes="(min-width: 1180px) 600px, 100vw" @endif
          width="{{ $page['image']['width'] }}" height="{{ $page['image']['height'] }}"
          alt="{{ $page['image']['alt'] }}" loading="{{ $i < 2 || $editor ? 'eager' : 'lazy' }}" decoding="async"
          @if (! empty($page['image_crop'])) style="{{ \App\Guesstimate\Document\ImageCrop::style($page['image_crop']) }}" @endif>
      </figure>
    @elseif ($slot && $editor)
      <span class="osf-media-add osf-t-xs" {{ $edit('image') }}>+ Zdjęcie strony</span>
    @endif

    <div class="osf-content">
      @include('guesstimate.public.pages.' . str_replace('_', '-', $type), ['offer' => $offer, 'doc' => $doc, 'page' => $page, 'logo' => $logo, 'chevrons' => $chevrons, 'edit' => $place, 'editor' => $editor])@includeWhen(! empty($page['fields']), 'guesstimate.public.partials.field-copies', ['fields' => $page['fields'] ?? null, 'place' => $place])
    </div>

    @if ($editor && $page['hidden'])
      <p class="osf-hidden-note osf-t-sm absolute inset-x-0 bottom-0 bg-night/90 px-4 py-2 text-center font-bold text-orange">{{ $hiddenNotes[$page['hidden']] }}</p>
    @endif
  </section>
@endforeach
