@php $mode ??= 'public'; @endphp
<!doctype html>
<html lang="pl" data-mode="{{ $mode }}" @class(['osf-sheet' => $mode !== 'public', 'osf-editor' => $mode === 'editor', 'osf-thumbs' => $mode === 'thumbs'])>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="referrer" content="no-referrer">
  <title>@yield('title') – OhSoFresh Creative</title>
  @if ($mode === 'public')
    {{-- Arkusz A4 od 1180 px — klasa przed pierwszym renderem, żeby strona nie „przeskakiwała”. --}}
    <script>if (window.matchMedia('(min-width: 1180px)').matches) document.documentElement.classList.add('osf-sheet');</script>
  @endif
  @vite(['resources/css/offer.css'])
</head>
<body class="min-h-screen bg-night font-sans text-paper osf-glow">
  @yield('body')
</body>
</html>
