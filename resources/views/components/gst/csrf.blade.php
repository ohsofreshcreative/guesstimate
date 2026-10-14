<input type="hidden" name="{{ \App\Guesstimate\Http\Middleware\VerifyNonce::FIELD }}" value="{{ wp_create_nonce(\App\Guesstimate\Http\Middleware\VerifyNonce::ACTION) }}">
