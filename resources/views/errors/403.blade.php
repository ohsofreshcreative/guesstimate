@include('guesstimate.partials.error', [
  'title' => 'Brak dostępu',
  'message' => filled($exception->getMessage()) ? $exception->getMessage() : 'Nie masz uprawnień do tej strony.',
])
