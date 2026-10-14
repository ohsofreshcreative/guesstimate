<?php

namespace App\Guesstimate\Ai;

use App\Guesstimate\Integrations\IntegrationException;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Support\Secrets;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Wywołania AI z limitami i dziennikiem (bez treści promptów i bez danych osobowych w logu).
 * AI niczego nie zapisuje w ofercie — zwraca propozycję, którą użytkownik świadomie stosuje.
 */
class AiService
{
	public const KEY = 'openai_api_key';

	public function __construct(private SettingsRepository $settings, private Secrets $secrets)
	{
	}

	/**
	 * Stan integracji dla widoków (bez klucza).
	 */
	public static function status(SettingsRepository $settings): array
	{
		$key = app(Secrets::class)->get(self::KEY);
		$config = (array) $settings->get('ai');

		if ($key === null || empty($config['model'])) {
			return ['enabled' => false, 'message' => 'Asystent AI nie jest skonfigurowany. Administrator może dodać klucz OpenAI w Ustawieniach → Integracje.'];
		}

		return ['enabled' => true, 'message' => null];
	}

	public function config(): array
	{
		return array_replace(['model' => '', 'instructions' => '', 'daily_limit' => 40, 'timeout' => 45], (array) $this->settings->get('ai'));
	}

	/**
	 * @param  list<array{role: string, content: string}>  $messages
	 */
	public function run(string $kind, ?int $offerId, array $messages, float $temperature = 0.4): array
	{
		$config = $this->config();
		$key = $this->secrets->get(self::KEY);

		if ($key === null || $config['model'] === '') {
			throw new IntegrationException('Asystent AI nie jest skonfigurowany.');
		}

		$userId = get_current_user_id();
		$recent = DB::table('gst_ai_requests')->where('user_id', $userId)->where('created_at', '>=', now()->subSeconds(5))->exists();

		if ($recent) {
			throw new IntegrationException('Poczekaj chwilę przed kolejnym zapytaniem.');
		}

		$used = DB::table('gst_ai_requests')->where('user_id', $userId)->where('created_at', '>=', now()->subDay())->count();

		if ($used >= (int) $config['daily_limit']) {
			throw new IntegrationException(sprintf('Wykorzystano dzienny limit zapytań do AI (%d). Spróbuj jutro albo poproś administratora o zwiększenie limitu.', (int) $config['daily_limit']));
		}

		$client = new OpenAiClient($key, (string) $config['model'], max(10, min(120, (int) $config['timeout'])));
		$log = ['user_id' => $userId, 'offer_id' => $offerId, 'kind' => $kind, 'model' => (string) $config['model'], 'created_at' => now()];

		try {
			$result = $client->json($messages, $temperature);
			DB::table('gst_ai_requests')->insert($log + ['status' => 'ok', 'tokens_in' => $result['tokens_in'], 'tokens_out' => $result['tokens_out']]);

			return $result['data'];
		} catch (IntegrationException $e) {
			DB::table('gst_ai_requests')->insert($log + ['status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 255)]);

			throw $e;
		} catch (Throwable $e) {
			DB::table('gst_ai_requests')->insert($log + ['status' => 'error', 'error' => 'Nieoczekiwany błąd']);

			throw new IntegrationException('Nie udało się uzyskać odpowiedzi AI. Spróbuj ponownie.');
		}
	}

	public function instructions(): string
	{
		return trim((string) $this->config()['instructions']);
	}
}
