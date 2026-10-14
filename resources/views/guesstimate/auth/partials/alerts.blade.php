@if (! empty($notice))
  <p class="mt-6 rounded-control border border-accent/30 bg-accent-soft px-4 py-3 text-sm font-medium text-accent-strong" role="status" data-auth-notice>{{ $notice }}</p>
@endif
@if (! empty($error))
  <p class="mt-6 rounded-control border border-danger/30 bg-danger-soft px-4 py-3 text-sm font-medium text-danger" role="alert" id="auth-error" data-auth-error>{{ $error }}</p>
@endif
