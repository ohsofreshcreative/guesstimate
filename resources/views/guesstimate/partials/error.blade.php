@extends('guesstimate.layouts.bare')

@section('title', $title)

@section('content')
  <span class="flex size-12 items-center justify-center rounded-full bg-canvas text-ink-muted">
    <x-gst.icon :name="$icon ?? 'lock'" class="size-6" />
  </span>
  <h1 class="mt-5 text-2xl font-semibold tracking-tight">{{ $title }}</h1>
  <p class="mt-2 text-ink-soft">{{ $message }}</p>
  <div class="mt-6">
    <x-gst.button :href="home_url('/app/')" variant="secondary">Wróć do pulpitu</x-gst.button>
  </div>
@endsection
