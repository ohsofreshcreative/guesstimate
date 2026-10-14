@include('guesstimate.partials.error', [
  'title' => 'Formularz wygasł',
  'message' => filled($exception->getMessage()) ? $exception->getMessage() : 'Odśwież stronę i spróbuj ponownie.',
])
