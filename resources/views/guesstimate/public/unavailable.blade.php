@extends('guesstimate.public.layout')

@section('title', $title)

@section('body')
  <main class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-6 py-16">
    <p class="text-2xl font-bold tracking-tight">oh!sofresh</p>
    <h1 class="mt-10 text-4xl font-bold tracking-tight text-orange sm:text-5xl">{{ $title }}</h1>
    <p class="mt-5 text-lg text-paper-soft">{{ $text }}</p>
  </main>
@endsection
