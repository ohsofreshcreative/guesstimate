<nav class="mb-6 overflow-x-auto" aria-label="Sekcje ustawień">
  <ul class="flex min-w-max gap-1 border-b border-line">
    @foreach ($sections as $key => $label)
      <li>
        <a href="{{ route('gst.settings.index', ['section' => $key]) }}" @class([
          '-mb-px block border-b-2 px-4 py-2.5',
          'border-ink font-medium text-ink' => $section === $key,
          'border-transparent text-ink-soft hover:text-ink' => $section !== $key,
        ]) @if ($section === $key) aria-current="page" @endif>{{ $label }}</a>
      </li>
    @endforeach
    @if (current_user_can(\App\Guesstimate\Auth\Capabilities::MANAGE_USERS))
      <li>
        <a href="{{ route('gst.users.index') }}" @class([
          '-mb-px block border-b-2 px-4 py-2.5',
          'border-ink font-medium text-ink' => $section === 'uzytkownicy',
          'border-transparent text-ink-soft hover:text-ink' => $section !== 'uzytkownicy',
        ])>Użytkownicy</a>
      </li>
    @endif
  </ul>
  @if (! empty($legacySections))
    <p class="mt-2 text-sm text-ink-muted">Poprzedni model kalkulacji:
      @foreach ($legacySections as $key => $label)
        <a href="{{ route('gst.settings.index', ['section' => $key]) }}" @class(['hover:text-ink', 'font-medium text-ink' => $section === $key])>{{ $label }}</a>@if (! $loop->last) · @endif
      @endforeach
    </p>
  @endif
</nav>
