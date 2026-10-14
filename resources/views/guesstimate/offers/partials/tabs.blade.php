{{-- Zakładki modułu Oferty: Archiwum | Kosz (kosz tylko dla managera i administratora). --}}
@if (app(\App\Guesstimate\Offers\OfferPolicy::class)->canManageTrash())
  <nav class="mb-4 overflow-x-auto" aria-label="Widok ofert">
    <ul class="flex min-w-max gap-1 border-b border-line">
      @foreach (['archive' => ['Archiwum', route('gst.offers.index')], 'trash' => ['Kosz', route('gst.offers.trash')]] as $key => [$label, $url])
        <li>
          <a href="{{ $url }}" @class([
            '-mb-px block border-b-2 px-4 py-2.5',
            'border-ink font-medium text-ink' => $tab === $key,
            'border-transparent text-ink-soft hover:text-ink' => $tab !== $key,
          ]) @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        </li>
      @endforeach
    </ul>
  </nav>
@endif
