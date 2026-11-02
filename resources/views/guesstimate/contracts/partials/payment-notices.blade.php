{{-- Blokady zatwierdzenia i ostrzeżenia harmonogramu. --}}
@if ($summary['blockers'])
  <ul class="mt-3 space-y-1 text-sm text-danger" role="status" data-payment-blockers>
    @foreach ($summary['blockers'] as $blocker)
      <li>{{ $blocker }}</li>
    @endforeach
  </ul>
@endif
@if ($summary['warnings'])
  <ul class="mt-3 space-y-1 text-sm text-ink-soft" data-payment-warnings>
    @foreach ($summary['warnings'] as $warning)
      <li>{{ $warning }}</li>
    @endforeach
  </ul>
@endif
