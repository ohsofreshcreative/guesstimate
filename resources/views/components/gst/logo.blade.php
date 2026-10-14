{{-- Znak Guesstimate (jak w menu bocznym): nazwa + trzy paski wariantów ryzyka. --}}
@props(['size' => 'md', 'tagline' => true])

<span {{ $attributes->class(['block']) }}>
  <span @class(['block font-bold tracking-tight', 'text-xl' => $size === 'md', 'text-2xl' => $size === 'lg'])>Guesstimate</span>
  <span class="mt-2 flex gap-1" aria-hidden="true">
    <span class="h-1 w-5 rounded-full bg-risk-low"></span>
    <span class="h-1 w-5 rounded-full bg-risk-mid"></span>
    <span class="h-1 w-5 rounded-full bg-risk-high"></span>
  </span>
  @if ($tagline)
    <span class="mt-2 block text-sm opacity-70">OhSoFresh Creative</span>
  @endif
</span>
