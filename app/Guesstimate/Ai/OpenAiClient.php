<?php

namespace App\Guesstimate\Ai;

use App\Guesstimate\Integrations\HttpJson;
use App\Guesstimate\Integrations\IntegrationException;

/**
 * Minimalny klient OpenAI Chat Completions (wyłącznie po stronie serwera, odpowiedź w JSON).
 */
class OpenAiClient
{
	public const URL = 'https://api.openai.com/v1/chat/completions';

	public function __construct(private string $apiKey, private string $model, private int $timeout = 45)
	{
	}

	/**
	 * @param  list<array{role: string, content: string}>  $messages
	 * @return array{data: array, tokens_in: ?int, tokens_out: ?int}
	 */
	public function json(array $messages, float $temperature = 0.4, int $maxTokens = 2500): array
	{
		$response = HttpJson::request('POST', self::URL, ['Authorization' => 'Bearer ' . $this->apiKey], [
			'model' => $this->model,
			'messages' => $messages,
			'temperature' => $temperature,
			'max_completion_tokens' => $maxTokens,
			'response_format' => ['type' => 'json_object'],
		], $this->timeout, 'OpenAI');

		$content = $response['choices'][0]['message']['content'] ?? null;
		$data = is_string($content) ? json_decode($content, true) : null;

		if (! is_array($data)) {
			throw new IntegrationException('OpenAI zwróciło odpowiedź w nieoczekiwanym formacie. Spróbuj ponownie.');
		}

		return [
			'data' => $data,
			'tokens_in' => isset($response['usage']['prompt_tokens']) ? (int) $response['usage']['prompt_tokens'] : null,
			'tokens_out' => isset($response['usage']['completion_tokens']) ? (int) $response['usage']['completion_tokens'] : null,
		];
	}
}
