<div x-show="navOpen" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-ink/50 lg:hidden" @click="navOpen = false"></div>

<aside
  id="gst-sidebar"
  class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-rail text-rail-ink transition-transform lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:translate-x-0"
  :class="{ 'translate-x-0': navOpen }"
  aria-label="Nawigacja aplikacji">

  <div class="flex items-start justify-between px-6 pb-6 pt-7">
    <a href="{{ route('gst.dashboard') }}" class="block text-white">
      <span class="block text-xl font-bold tracking-tight">Guesstimate</span>
      <span class="mt-2 flex gap-1" aria-hidden="true">
        <span class="h-1 w-5 rounded-full bg-risk-low"></span>
        <span class="h-1 w-5 rounded-full bg-risk-mid"></span>
        <span class="h-1 w-5 rounded-full bg-risk-high"></span>
      </span>
      <span class="mt-2 block text-sm text-rail-muted">OhSoFresh Creative</span>
    </a>

    <button type="button" class="rounded-control p-2 text-rail-muted hover:bg-rail-raised hover:text-white lg:hidden" @click="navOpen = false">
      <x-gst.icon name="close" />
      <span class="sr-only">Zamknij menu</span>
    </button>
  </div>

  <nav class="flex-1 overflow-y-auto px-3">
    <ul class="space-y-1">
      @foreach ($navigation as $item)
        <li>
          <a
            href="{{ $item['url'] }}"
            @class([
              'flex items-center gap-3 rounded-control px-3 py-2.5 transition-colors',
              'bg-rail-raised font-medium text-white' => $item['active'],
              'hover:bg-rail-raised hover:text-white' => ! $item['active'],
            ])
            @if ($item['active']) aria-current="page" @endif>
            <x-gst.icon :name="$item['icon']" :class="$item['active'] ? 'text-white' : 'text-rail-muted'" />
            {{ $item['label'] }}
          </a>
        </li>
      @endforeach
    </ul>
  </nav>

  <div class="border-t border-rail-raised px-4 py-4">
    <div class="flex items-center gap-3">
      <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-rail-raised text-sm font-semibold text-white" aria-hidden="true">
        {{ $currentUser['initials'] }}
      </span>
      <a href="{{ $profileUrl }}" class="min-w-0 flex-1 hover:text-white">
        <span class="block truncate font-medium text-white">{{ $currentUser['name'] }}</span>
        <span class="block truncate text-sm text-rail-muted">{{ $currentUser['role'] }}</span>
      </a>
      <a href="{{ $logoutUrl }}" class="rounded-control p-2 text-rail-muted hover:bg-rail-raised hover:text-white">
        <x-gst.icon name="logout" />
        <span class="sr-only">Wyloguj się</span>
      </a>
    </div>
  </div>
</aside>
