<?php

namespace App\Guesstimate\Clients;

use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Support\Input;
use App\Guesstimate\Support\Validation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Walidacja i zapis klientów.
 */
class ClientService
{
	public const FIELDS = ['company_name', 'website_url', 'contact_name', 'email', 'phone', 'nip', 'notes'];

	/**
	 * @return array{data: array|null, errors: array<string, string>}
	 */
	public function validate(array $input, string $prefix = ''): array
	{
		$data = [
			'company_name' => Input::line($input[$prefix . 'company_name'] ?? null),
			'website_url' => self::url($input[$prefix . 'website_url'] ?? null),
			'contact_name' => Input::line($input[$prefix . 'contact_name'] ?? null),
			'email' => Input::line($input[$prefix . 'email'] ?? null),
			'phone' => Input::line($input[$prefix . 'phone'] ?? null),
			'nip' => Nip::normalize(Input::line($input[$prefix . 'nip'] ?? null)),
			'notes' => Input::text($input[$prefix . 'notes'] ?? null),
		];

		$validator = Validation::make($data, [
			'company_name' => 'required|string|max:190',
			'website_url' => 'nullable|url|max:255',
			'contact_name' => 'nullable|string|max:190',
			'email' => 'nullable|email|max:190',
			'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\s\-]{6,}$/'],
			'notes' => 'nullable|string|max:5000',
		], [
			'company_name' => 'Nazwa firmy',
			'website_url' => 'Adres strony WWW',
			'contact_name' => 'Osoba kontaktowa',
			'email' => 'E-mail',
			'phone' => 'Telefon',
			'notes' => 'Notatki',
		], [
			'phone.regex' => 'Podaj numer telefonu, np. +48 600 100 200.',
		]);

		$errors = collect($validator->errors()->toArray())->map(fn ($m) => $m[0])->all();

		if ($data['nip'] !== null && ! Nip::isValid($data['nip'])) {
			$errors['nip'] = 'NIP jest nieprawidłowy — sprawdź 10 cyfr numeru.';
		}

		if ($prefix !== '') {
			$errors = collect($errors)->mapWithKeys(fn ($message, $key) => [$prefix . $key => $message])->all();
		}

		return ['data' => $errors ? null : $data, 'errors' => $errors];
	}

	public function create(array $data): Client
	{
		return Client::create(array_merge($data, ['created_by' => get_current_user_id()]));
	}

	/**
	 * Usunięcie klienta z lokalnej bazy (soft delete — można przywrócić). Zablokowane, gdy klient ma jakąkolwiek
	 * ofertę: roboczą, opublikowaną, wycofaną, zarchiwizowaną albo w koszu (oferty i ich historia muszą zachować
	 * klienta). Wiersz klienta jest blokowany (SELECT … FOR UPDATE) na czas sprawdzenia i usunięcia, a tworzenie
	 * oferty blokuje ten sam wiersz (lockActive) — oferta nie zostanie przypisana do klienta w trakcie usuwania.
	 * Nie wykonuje żadnej operacji w ClickUp ani na indeksach ClickUp.
	 *
	 * @return array{result: 'deleted'|'in_use'|'missing'|'already', client: ?Client, offers: int, trashed_offers: int}
	 */
	public function delete(int $clientId, int $userId): array
	{
		return DB::transaction(function () use ($clientId, $userId) {
			$client = Client::withTrashed()->whereKey($clientId)->lockForUpdate()->first();

			if (! $client) {
				return ['result' => 'missing', 'client' => null, 'offers' => 0, 'trashed_offers' => 0];
			}

			if ($client->trashed()) {
				return ['result' => 'already', 'client' => $client, 'offers' => 0, 'trashed_offers' => 0];
			}

			$offers = Offer::withTrashed()->where('client_id', $client->id)->count();
			$trashed = Offer::onlyTrashed()->where('client_id', $client->id)->count();

			if ($offers > 0) {
				return ['result' => 'in_use', 'client' => $client, 'offers' => $offers, 'trashed_offers' => $trashed];
			}

			$client->forceFill(['deleted_by' => $userId])->save();
			$client->delete();

			return ['result' => 'deleted', 'client' => $client, 'offers' => 0, 'trashed_offers' => 0];
		});
	}

	/**
	 * Przywrócenie usuniętego klienta (z danymi i powiązaniem z ClickUp, jeśli było).
	 *
	 * @return array{result: 'restored'|'missing'|'active', client: ?Client}
	 */
	public function restore(int $clientId): array
	{
		return DB::transaction(function () use ($clientId) {
			$client = Client::withTrashed()->whereKey($clientId)->lockForUpdate()->first();

			if (! $client) {
				return ['result' => 'missing', 'client' => null];
			}

			if (! $client->trashed()) {
				return ['result' => 'active', 'client' => $client];
			}

			$client->restore();
			$client->forceFill(['deleted_by' => null])->save();

			return ['result' => 'restored', 'client' => $client];
		});
	}

	/**
	 * W transakcji tworzącej / zmieniającej ofertę: blokuje wiersz aktywnego klienta (współdzielona kolejka
	 * z delete()). Klient usunięty w międzyczasie → wyjątek, oferta nie powstaje.
	 */
	public static function lockActive(int $clientId): void
	{
		if (! Client::whereKey($clientId)->lockForUpdate()->exists()) {
			throw new ClientUnavailable('Wybrany klient został usunięty. Wybierz innego klienta albo dodaj nowego.');
		}
	}

	/**
	 * Usunięci klienci (zakładka „Usunięci”): nazwa, data usunięcia, kto usunął.
	 *
	 * @return list<array{id: int, name: string, deleted_at: string, deleted_by: string, clickup: bool}>
	 */
	public function deleted(string $term = ''): array
	{
		$query = Client::onlyTrashed()->orderByDesc('deleted_at')->limit(500);

		if ($term !== '') {
			$query->where('company_name', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%');
		}

		return $query->get()->map(fn (Client $c) => [
			'id' => $c->id,
			'name' => $c->company_name,
			'deleted_at' => $c->deleted_at ? wp_date('d.m.Y H:i', $c->deleted_at->getTimestamp()) : '—',
			'deleted_by' => $c->deleted_by ? (get_userdata((int) $c->deleted_by)->display_name ?? 'Użytkownik #' . $c->deleted_by) : '—',
			'clickup' => $c->clickup_id !== null,
		])->all();
	}

	public function search(string $term): Builder
	{
		$query = Client::query()
			->withCount(['offers' => fn ($q) => $q->whereNull('deleted_at')])
			->withCount(['offers as offers_all_count' => fn ($q) => $q->withTrashed()])
			->orderBy('company_name');

		if ($term !== '') {
			$like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
			$query->where(fn ($q) => $q->where('company_name', 'like', $like)
				->orWhere('contact_name', 'like', $like)
				->orWhere('email', 'like', $like)
				->orWhere('nip', 'like', $like)
				->orWhere('website_url', 'like', $like));
		}

		return $query;
	}

	/**
	 * Dopisuje https://, gdy użytkownik podał sam adres domeny.
	 */
	public static function url(mixed $value): ?string
	{
		$value = Input::line($value);

		if ($value === null) {
			return null;
		}

		return preg_match('#^https?://#i', $value) ? $value : 'https://' . $value;
	}
}
