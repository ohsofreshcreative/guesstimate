<?php

namespace App\Guesstimate\Clients;

/**
 * Karta klienta w kreatorze (dane do autouzupełnienia), bez notatek wewnętrznych.
 */
final class ClientPresenter
{
	public static function card(Client $client): array
	{
		return [
			'id' => $client->id,
			'name' => $client->company_name,
			'website_url' => $client->website_url,
			'contact_name' => $client->contact_name,
			'email' => $client->email,
			'phone' => $client->phone,
			'nip' => $client->nip,
			'clickup' => $client->clickup_id !== null,
		];
	}
}
