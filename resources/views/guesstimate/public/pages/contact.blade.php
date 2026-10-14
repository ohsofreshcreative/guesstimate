@php $c = $doc['contact'] ?? null; @endphp
<div class="osf-pad flex flex-1 flex-col">
  <img src="{{ $logo }}" alt="OhSoFresh Creative" class="osf-logo self-start">
  <div class="my-auto py-10" {{ $edit('contact') }}>
    @include('guesstimate.public.partials.heading', ['page' => $page, 'edit' => $edit])
    @if ($c && $c['name'])
      <p class="osf-t-lg mt-10 font-bold">{{ $c['name'] }}</p>
      @if ($c['role'])<p class="osf-t-sm font-bold text-orange">{{ $c['role'] }}</p>@endif
      <dl class="osf-t-sm mt-5 space-y-1 font-bold">
        @if ($c['phone'])<div><dt class="inline text-orange">mobile:</dt> <dd class="inline"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $c['phone']) }}" class="hover:underline">{{ $c['phone'] }}</a></dd></div>@endif
        @if ($c['email'])<div><dt class="inline text-orange">email:</dt> <dd class="inline"><a href="mailto:{{ $c['email'] }}" class="hover:underline">{{ $c['email'] }}</a></dd></div>@endif
      </dl>
    @endif
  </div>
</div>
