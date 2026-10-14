<?php

namespace App\Guesstimate\Integrations;

use RuntimeException;

/**
 * Błąd integracji zewnętrznej (ClickUp, OpenAI). Komunikat jest bezpieczny do pokazania użytkownikowi.
 */
class IntegrationException extends RuntimeException
{
	/** Kod błędu z treści odpowiedzi API (np. ClickUp „ECODE”: OAUTH_025), bez innych danych odpowiedzi. */
	public ?string $apiCode = null;
}
