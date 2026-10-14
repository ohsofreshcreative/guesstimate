<?php

namespace App\Guesstimate\Integrations\ClickUp;

use App\Guesstimate\Auth\Capabilities;
use App\Guesstimate\Clients\Client;
use App\Guesstimate\Clients\ClientService;
use App\Guesstimate\Clients\Nip;
use App\Guesstimate\Integrations\IntegrationException;
use Illuminate\Support\Facades\DB;

/**
 * Firmy z ClickUp w kreatorze: wyszukiwanie w prywatnym indeksie zalogowanego użytkownika (bez zapytań
 * do ClickUp przy wpisywaniu) i import do WSPÓLNEJ bazy klientów Guesstimate.
 *
 * Import: najpierw klient powiązany z tym zadaniem (ID ClickUp), potem jednoznaczne dopasowanie po NIP;
 * podobna nazwa / domena / e-mail = dopasowanie niepewne → użytkownik wybiera istniejącego klienta albo
 * tworzy nowego. Istniejący rekord jest tylko uzupełniany (puste pola) — ręczne zmiany zostają.
 */
class ClickUpDirectory
{
	private const LEGAL = ['sp z o o', 'sp z oo', 'spolka z ograniczona odpowiedzialnoscia', 'sp j', 'sp k', 's a', 'sa', 's c', 'sc', 'spolka akcyjna', 'spolka jawna'];

	public function __construct(private ClickUpAccount $account, private ClientService $clients)
	{
	}

	/**
	 * Fraza i nazwy do porównania: małe litery, bez polskich znaków i akcentów, pojedyncze spacje.
	 */
	public static function fold(?string $text): string
	{
		$text = mb_strtolower(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		$text = strtr($text, ['ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z']);
		$text = function_exists('remove_accents') ? remove_accents($text) : $text;

		return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
	}

	/** Nazwa firmy bez formy prawnej i interpunkcji (do wykrywania podobnych klientów). */
	public static function companyKey(?string $name): string
	{
		$key = trim((string) preg_replace('/[^a-z0-9]+/', ' ', self::fold($name)));

		foreach (self::LEGAL as $suffix) {
			$key = (string) preg_replace('/\s' . preg_quote($suffix, '/') . '$/', '', $key);
		}

		return trim($key);
	}

	/**
	 * Stan wyszukiwarki ClickUp dla użytkownika: not_connected | reconnect | not_configured | not_synced | ok.
	 */
	public function state(?ClickUpConnection $connection): string
	{
		return match (true) {
			$connection === null => 'not_connected',
			! $connection->isConnected() => 'reconnect',
			$connection->settings()['lists'] === [] || $connection->settings()['team_id'] === '' => 'not_configured',
			$connection->synced_at === null && ! DB::table('gst_clickup_index')->where('user_id', $connection->user_id)->exists() => 'not_synced',
			default => 'ok',
		};
	}

	/**
	 * Wyszukiwanie w indeksie użytkownika. Kolejność: nazwa zaczyna się od frazy, słowo zaczyna się od frazy,
	 * fraza w nazwie, fraza w innych polach (NIP, e-mail, osoba, WWW).
	 *
	 * @return list<array>
	 */
	public function search(int $userId, string $term, int $limit = 10): array
	{
		$needle = self::fold($term);

		if (mb_strlen($needle) < 2) {
			return [];
		}

		$like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $needle) . '%';
		$rows = DB::table('gst_clickup_index')
			->where('user_id', $userId)
			->where(fn ($q) => $q->where('search_name', 'like', $like)->orWhere('search_other', 'like', $like))
			->orderBy('search_name')
			->limit(200)
			->get();

		$ranked = [];

		foreach ($rows as $row) {
			$name = (string) $row->search_name;
			$rank = match (true) {
				str_starts_with($name, $needle) => 0,
				str_contains(' ' . $name, ' ' . $needle) => 1,
				str_contains($name, $needle) => 2,
				default => 3,
			};
			$ranked[$rank][] = $row;
		}

		ksort($ranked);
		$results = array_slice(array_merge(...array_values($ranked ?: [[]])), 0, $limit);
		$linked = Client::whereIn('clickup_id', array_map(fn ($r) => $r->task_id, $results) ?: ['-'])->pluck('id', 'clickup_id')->all();

		return array_map(fn ($r) => [
			'clickup_id' => $r->task_id,
			'local_id' => $linked[$r->task_id] ?? null,
			'name' => $r->company_name,
			'list' => $r->list_name,
			'status' => $r->status,
			'website_url' => $r->website_url,
			'contact_name' => $r->contact_name,
			'email' => $r->email,
			'phone' => $r->phone,
			'nip' => $r->nip,
		], $results);
	}

	/**
	 * Import firmy z indeksu użytkownika do wspólnej bazy klientów.
	 *
	 * @param  int|null  $clientId  wybór użytkownika przy niepewnym dopasowaniu (istniejący klient)
	 * @return array{client: Client|null, created: bool, linked: bool, skipped: list<string>, candidates: list<Client>}
	 */
	public function import(int $userId, string $taskId, ?int $clientId = null, bool $createNew = false, bool $restore = false): array
	{
		if (! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $taskId)) {
			throw new IntegrationException('Nieprawidłowy identyfikator zadania ClickUp.');
		}

		$connection = ClickUpConnection::forUser($userId);

		if (! $connection || ! $connection->isConnected()) {
			throw new IntegrationException($connection ? ClickUpAccount::RECONNECT_MESSAGE : 'Połącz swoje konto ClickUp, aby importować klientów.');
		}

		// Tylko zadania z własnego indeksu (własne listy) — nie można zaimportować cudzego zadania po ID.
		$row = DB::table('gst_clickup_index')->where('user_id', $userId)->where('task_id', $taskId)->first();

		if (! $row) {
			throw new IntegrationException('Tego zadania nie ma w Twoich zsynchronizowanych listach ClickUp.');
		}

		$config = $connection->settings();
		[$data, $skipped] = $this->clean($this->fetch($connection, $taskId, $row));

		if ($data['company_name'] === null) {
			throw new IntegrationException('Zadanie ClickUp nie ma nazwy firmy (sprawdź mapowanie pól).');
		}

		return DB::transaction(function () use ($taskId, $data, $skipped, $clientId, $createNew, $restore, $config, $userId) {
			$context = ['clickup_team_id' => $config['team_id'] ?: null, 'clickup_imported_by' => $userId];

			// 1. Klient już powiązany z tym zadaniem.
			if ($client = Client::withTrashed()->where('clickup_id', $taskId)->lockForUpdate()->first()) {
				if (! $client->trashed()) {
					return $this->result($this->fill($client, $data), false, false, $skipped);
				}

				// Klient usunięty w Guesstimate: nigdy nie przywracamy go automatycznie — decyzja użytkownika.
				if ($restore) {
					if (! current_user_can(Capabilities::MANAGE_CLIENTS)) {
						throw new IntegrationException('Przywracać klientów mogą Administrator i Manager.');
					}

					$client->restore();
					$client->forceFill(['deleted_by' => null])->save();

					return $this->result($this->fill($client, $data), false, true, $skipped) + ['restored' => true];
				}

				if (! $createNew) {
					return ['client' => null, 'created' => false, 'linked' => false, 'skipped' => $skipped, 'candidates' => [], 'deleted' => $client];
				}

				// „Zaimportuj jako nowego”: powiązanie z zadaniem przechodzi na nowy rekord (bez dwóch klientów
				// z tym samym ID ClickUp); usunięty klient zostaje w zakładce „Usunięci” i można go przywrócić.
				$client->forceFill(['clickup_id' => null])->save();
			}

			// 2. Wybór użytkownika: istniejący klient.
			if ($clientId) {
				$client = Client::find($clientId) ?? throw new IntegrationException('Wybrany klient nie istnieje.');

				return $this->result($this->fill($client, $data, $client->clickup_id === null ? ['clickup_id' => $taskId] + $context : []), false, true, $skipped);
			}

			if (! $createNew) {
				// 3. Jednoznaczne dopasowanie po NIP (jedna firma, bez innego powiązania z ClickUp).
				if ($data['nip']) {
					$byNip = Client::where('nip', $data['nip'])->get();

					if ($byNip->count() === 1 && $byNip->first()->clickup_id === null) {
						return $this->result($this->fill($byNip->first(), $data, ['clickup_id' => $taskId] + $context), false, true, $skipped);
					}
				}

				// 4. Dopasowanie niepewne: podobna nazwa, ta sama domena WWW lub e-mail → wybór użytkownika.
				if ($candidates = $this->similar($data)) {
					return ['client' => null, 'created' => false, 'linked' => false, 'skipped' => $skipped, 'candidates' => $candidates];
				}
			}

			$client = $this->clients->create(array_merge(array_fill_keys(ClientService::FIELDS, null), array_filter($data, fn ($v) => $v !== null)));
			$client->update(['clickup_id' => $taskId, 'clickup_synced_at' => now()] + $context);

			return $this->result($client->fresh(), true, false, $skipped);
		});
	}

	/**
	 * Aktualne dane zadania z ClickUp (gdy API chwilowo nie odpowiada — dane z indeksu).
	 */
	private function fetch(ClickUpConnection $connection, string $taskId, object $row): array
	{
		$config = $connection->settings();

		try {
			$task = $this->account->call($connection, fn (ClickUpClient $api) => $api->task($taskId));
			$taskLists = array_merge([(string) ($task['list']['id'] ?? '')], array_map(fn ($l) => (string) ($l['id'] ?? ''), (array) ($task['locations'] ?? [])));

			if (! array_intersect($taskLists, $connection->listIds())) {
				throw new IntegrationException('To zadanie nie należy już do Twoich list ClickUp. Zsynchronizuj firmy ponownie.');
			}

			return ClickUpMapper::map($task, $config['mapping']);
		} catch (IntegrationException $e) {
			if ($e->getCode() === 401 || str_contains($e->getMessage(), 'nie należy')) {
				throw $e;
			}

			return ['company_name' => $row->company_name, 'website_url' => $row->website_url, 'contact_name' => $row->contact_name, 'email' => $row->email, 'phone' => $row->phone, 'nip' => $row->nip];
		}
	}

	/**
	 * @return list<Client>
	 */
	private function similar(array $data): array
	{
		$key = self::companyKey($data['company_name']);
		$domain = fn (?string $url) => $url ? preg_replace('/^www\./', '', (string) parse_url(ClientService::url($url) ?? '', PHP_URL_HOST)) : null;
		$site = $domain($data['website_url']);
		$email = $data['email'] ? mb_strtolower($data['email']) : null;
		$words = array_values(array_filter(explode(' ', $key), fn ($w) => mb_strlen($w) >= 3));
		$query = Client::query()->limit(50);

		$query->where(function ($q) use ($words, $site, $email) {
			foreach (array_slice($words, 0, 2) as $word) {
				$q->orWhere('company_name', 'like', '%' . $word . '%');
			}

			if ($site) {
				$q->orWhere('website_url', 'like', '%' . $site . '%');
			}

			if ($email) {
				$q->orWhere('email', $email);
			}
		});

		return $query->get()->filter(function (Client $client) use ($key, $domain, $site, $email) {
			return ($key !== '' && self::companyKey($client->company_name) === $key)
				|| ($site && $domain($client->website_url) === $site)
				|| ($email && mb_strtolower((string) $client->email) === $email);
		})->take(5)->values()->all();
	}

	/** Uzupełnia tylko puste pola istniejącego klienta. */
	private function fill(Client $client, array $data, array $extra = []): Client
	{
		$fill = array_filter($data, fn ($v, $field) => $v !== null && ($client->{$field} === null || $client->{$field} === ''), ARRAY_FILTER_USE_BOTH);

		if (isset($extra['clickup_id']) && Client::withTrashed()->where('clickup_id', $extra['clickup_id'])->exists()) {
			unset($extra['clickup_id']);
		}

		$client->update($fill + $extra + ($client->clickup_id || isset($extra['clickup_id']) ? ['clickup_synced_at' => now()] : []));

		return $client->fresh();
	}

	private function result(Client $client, bool $created, bool $linked, array $skipped): array
	{
		return ['client' => $client, 'created' => $created, 'linked' => $linked, 'skipped' => $skipped, 'candidates' => []];
	}

	/**
	 * Pola, które nie przechodzą walidacji klienta, są pomijane (zamiast blokować import).
	 *
	 * @return array{0: array, 1: list<string>}
	 */
	private function clean(array $data): array
	{
		$skipped = [];
		$data['website_url'] = ClientService::url($data['website_url']);
		$data['nip'] = Nip::normalize($data['nip']);
		$rules = [
			'website_url' => fn ($v) => filter_var($v, FILTER_VALIDATE_URL) !== false && mb_strlen($v) <= 255,
			'email' => fn ($v) => is_email($v) !== false,
			'phone' => fn ($v) => (bool) preg_match('/^[0-9+()\s\-]{6,50}$/', $v),
			'nip' => fn ($v) => Nip::isValid($v),
		];

		foreach ($rules as $field => $valid) {
			if ($data[$field] !== null && ! $valid($data[$field])) {
				$skipped[] = ClickUpMapper::TARGETS[$field];
				$data[$field] = null;
			}
		}

		return [$data, $skipped];
	}
}
