<?php

namespace App\Guesstimate\Integrations;

/**
 * Wywołania HTTP przez WP HTTP API (wp_remote_request) z JSON-em i limitem czasu.
 * Błędy sieci i odpowiedzi ≠ 2xx zamieniane są na IntegrationException z komunikatem bez danych wrażliwych.
 */
final class HttpJson
{
	/**
	 * @return array<mixed>
	 */
	public static function request(string $method, string $url, array $headers, ?array $body = null, int $timeout = 20, string $service = 'API'): array
	{
		$args = [
			'method' => $method,
			'timeout' => $timeout,
			'redirection' => 0,
			'headers' => array_merge(['Accept' => 'application/json'], $headers),
		];

		if ($body !== null) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = wp_json_encode($body);
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			$timeoutHit = str_contains(strtolower($response->get_error_message()), 'timed out');

			throw new IntegrationException($timeoutHit
				? sprintf('%s nie odpowiedziało w %d s. Spróbuj ponownie.', $service, $timeout)
				: sprintf('Nie udało się połączyć z %s.', $service));
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$data = json_decode((string) wp_remote_retrieve_body($response), true);

		if ($code < 200 || $code >= 300) {
			$error = new IntegrationException(match (true) {
				$code === 401 || $code === 403 => sprintf('%s odrzuciło dane dostępowe (HTTP %d). Sprawdź klucz w ustawieniach.', $service, $code),
				$code === 429 => sprintf('%s: przekroczono limit zapytań. Spróbuj za chwilę.', $service),
				$code >= 500 => sprintf('%s ma chwilowe problemy (HTTP %d).', $service, $code),
				default => sprintf('%s zwróciło błąd HTTP %d.', $service, $code),
			}, $code);
			$apiCode = is_array($data) ? ($data['ECODE'] ?? null) : null;
			$error->apiCode = is_string($apiCode) && preg_match('/^[A-Z0-9_]{1,40}$/', $apiCode) ? $apiCode : null;

			throw $error;
		}

		if (! is_array($data)) {
			throw new IntegrationException(sprintf('%s zwróciło nieczytelną odpowiedź.', $service));
		}

		return $data;
	}
}
