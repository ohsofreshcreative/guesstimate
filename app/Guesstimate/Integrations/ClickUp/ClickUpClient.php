<?php

namespace App\Guesstimate\Integrations\ClickUp;

use App\Guesstimate\Integrations\HttpJson;
use App\Guesstimate\Integrations\IntegrationException;

/**
 * Klient API ClickUp v2 dla tokenu OAuth użytkownika — wyłącznie odczyt (GET) + wymiana kodu OAuth.
 * Aplikacja nigdy nie zapisuje danych w ClickUp. Token jest tylko w nagłówku (nigdy w adresie URL).
 */
class ClickUpClient
{
	public const BASE = 'https://api.clickup.com/api/v2';

	/** ClickUp zwraca zadania listy stronami po 100. */
	public const PAGE_SIZE = 100;

	public function __construct(private string $token)
	{
	}

	/**
	 * Wymiana kodu autoryzacyjnego na token (POST /oauth/token, JSON w treści).
	 */
	public static function exchangeCode(string $clientId, string $clientSecret, string $code): string
	{
		$data = HttpJson::request('POST', self::BASE . '/oauth/token', [], ['client_id' => $clientId, 'client_secret' => $clientSecret, 'code' => $code], 20, 'ClickUp');
		$token = is_string($data['access_token'] ?? null) ? trim($data['access_token']) : '';

		if ($token === '') {
			throw new IntegrationException('ClickUp nie zwrócił tokenu dostępu.');
		}

		return $token;
	}

	/** Właściciel tokenu: {id, username, email}. */
	public function user(): array
	{
		return $this->get('/user')['user'] ?? [];
	}

	/** Workspace’y autoryzowane przez użytkownika dla tej aplikacji. */
	public function teams(): array
	{
		return $this->get('/team')['teams'] ?? [];
	}

	public function spaces(string $teamId): array
	{
		return $this->get('/team/' . rawurlencode($teamId) . '/space', ['archived' => 'false'])['spaces'] ?? [];
	}

	/**
	 * Listy w przestrzeni: z folderów i bez folderu, jako [id, name, folder].
	 */
	public function lists(string $spaceId): array
	{
		$lists = [];

		foreach ($this->get('/space/' . rawurlencode($spaceId) . '/folder', ['archived' => 'false'])['folders'] ?? [] as $folder) {
			foreach ($folder['lists'] ?? [] as $list) {
				$lists[] = ['id' => (string) $list['id'], 'name' => (string) $list['name'], 'folder' => (string) ($folder['name'] ?? '')];
			}
		}

		foreach ($this->get('/space/' . rawurlencode($spaceId) . '/list', ['archived' => 'false'])['lists'] ?? [] as $list) {
			$lists[] = ['id' => (string) $list['id'], 'name' => (string) $list['name'], 'folder' => ''];
		}

		return $lists;
	}

	/** Jedna lista: nazwa, folder, przestrzeń (sprawdzenie dostępu). */
	public function list(string $listId): array
	{
		return $this->get('/list/' . rawurlencode($listId));
	}

	public function fields(string $listId): array
	{
		return $this->get('/list/' . rawurlencode($listId) . '/field')['fields'] ?? [];
	}

	/**
	 * Jedna strona zadań listy (synchronizacja partiami). Wszystkie statusy (także zamknięte), podzadania
	 * i zadania dodane do kilku list; bez zarchiwizowanych.
	 *
	 * Synchronizacja przyrostowa: `date_updated_gt` (Unix ms, dokumentacja „Get Tasks”) — tylko zadania
	 * utworzone lub zmienione po podanym czasie. Zadań usuniętych i przeniesionych poza listę ten filtr nie
	 * zwraca — wykrywa je pełna synchronizacja.
	 *
	 * @return array{tasks: list<array>, last: bool}
	 */
	public function tasksPage(string $listId, int $page, ?int $updatedAfterMs = null): array
	{
		$query = [
			'page' => $page,
			'include_closed' => 'true',
			'subtasks' => 'true',
			'include_timl' => 'true',
			'archived' => 'false',
		];

		if ($updatedAfterMs !== null) {
			$query['date_updated_gt'] = $updatedAfterMs;
		}

		$data = $this->get('/list/' . rawurlencode($listId) . '/task', $query);
		$tasks = $data['tasks'] ?? [];
		$last = array_key_exists('last_page', $data) ? (bool) $data['last_page'] : count($tasks) < self::PAGE_SIZE;

		return ['tasks' => $tasks, 'last' => $last || $tasks === []];
	}

	public function task(string $taskId): array
	{
		return $this->get('/task/' . rawurlencode($taskId));
	}

	private function get(string $path, array $query = []): array
	{
		$url = self::BASE . $path . ($query ? '?' . http_build_query($query) : '');

		return HttpJson::request('GET', $url, ['Authorization' => 'Bearer ' . $this->token], null, 20, 'ClickUp');
	}
}
