@extends('guesstimate.layouts.app')

@section('title', 'Ustawienia – Użytkownicy')

@section('content')
  <x-gst.page-header title="Ustawienia" description="Zmiany obowiązują dla nowych ofert. Zapisane oferty i ich wersje zachowują swoje stawki i parametry." />

  @include('guesstimate.settings.partials.nav')

  <section class="rounded-panel border border-line bg-surface shadow-panel">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
      <div>
        <h2 class="text-lg font-semibold">Użytkownicy i role</h2>
        <p class="mt-1 text-ink-soft">Pracownik tworzy i edytuje własne oferty. Manager widzi i edytuje wszystkie oferty oraz zarządza klientami. Administrator ma pełny dostęp.</p>
      </div>
      <x-gst.button :href="$newUserUrl" variant="secondary">Dodaj konto w WordPressie</x-gst.button>
    </div>
    <div class="relative overflow-x-auto">
      <table class="w-full min-w-max text-left">
        <thead class="text-sm text-ink-soft"><tr class="border-b border-line"><th class="px-6 py-2.5 font-medium">Użytkownik</th><th class="px-3 py-2.5 font-medium">E-mail</th><th class="px-6 py-2.5 font-medium">Rola w aplikacji</th><th class="px-6 py-2.5 font-medium">Kontakt w ofertach</th></tr></thead>
        <tbody class="divide-y divide-line">
          @foreach ($users as $user)
            <tr>
              <td class="px-6 py-3 font-medium">{{ $user['name'] }}@if ($user['is_current']) <span class="text-sm font-normal text-ink-muted">(Ty)</span>@endif</td>
              <td class="px-3 py-3 text-ink-soft">{{ $user['email'] }}</td>
              <td class="px-6 py-3">
                @if ($user['is_admin'])
                  Administrator
                @else
                  <form method="post" action="{{ route('gst.users.update', ['user' => $user['id']]) }}" class="flex items-center gap-2">
                    <x-gst.csrf />
                    <label for="role-{{ $user['id'] }}" class="sr-only">Rola</label>
                    <x-gst.select name="role" :id="'role-' . $user['id']" :options="$roles" :value="$user['role']" class="w-48" />
                    <x-gst.button type="submit" variant="secondary">Zapisz</x-gst.button>
                  </form>
                  @if (! empty($errors['role_' . $user['id']]))<p class="mt-1 text-sm text-danger">{{ $errors['role_' . $user['id']] }}</p>@endif
                @endif
              </td>
              <td class="px-6 py-3">
                <form method="post" action="{{ route('gst.users.contact', ['user' => $user['id']]) }}" class="flex items-center gap-2">
                  <x-gst.csrf />
                  <label for="jt-{{ $user['id'] }}" class="sr-only">Stanowisko</label>
                  <x-gst.input name="job_title" :id="'jt-' . $user['id']" :value="$user['job_title']" placeholder="Stanowisko" class="w-44" />
                  <label for="ph-{{ $user['id'] }}" class="sr-only">Telefon</label>
                  <x-gst.input name="phone" :id="'ph-' . $user['id']" :value="$user['phone']" placeholder="Telefon" class="w-40" />
                  <x-gst.button type="submit" variant="ghost">Zapisz</x-gst.button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
@endsection
