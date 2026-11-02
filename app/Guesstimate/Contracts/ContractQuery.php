<?php

namespace App\Guesstimate\Contracts;

use App\Guesstimate\Clients\Client;
use App\Guesstimate\Offers\Offer;
use App\Guesstimate\Offers\OfferPolicy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista umów z wyszukiwaniem i filtrami. Zawsze ograniczona przez ContractPolicy::scope, także liczniki i lista autorów.
 */
class ContractQuery
{
	public const PER_PAGE = 20;

	public function __construct(private ContractPolicy $policy, private OfferPolicy $offers)
	{
	}

	/**
	 * @return array{rows: \Illuminate\Support\Collection, total: int, page: int, pages: int, filters: array}
	 */
	public function search(array $input): array
	{
		$filters = self::normalize($input);
		$query = $this->policy->scope(Contract::query());

		if ($filters['q'] !== '') {
			$like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $filters['q']) . '%';
			$query->where(fn (Builder $inner) => $inner->where('title', 'like', $like)
				->orWhereIn('offer_id', Offer::withTrashed()->where('number', 'like', $like)->select('id'))
				->orWhereIn('client_id', Client::withTrashed()->where('company_name', 'like', $like)->select('id')));
		}

		if ($filters['status'] !== '') {
			$query->whereHas('currentVersion', fn (Builder $version) => $version->where('status', $filters['status']));
		}

		if ($filters['author'] && $this->offers->viewsAll()) {
			$query->where('author_id', $filters['author']);
		}

		$total = (clone $query)->count();
		$pages = max(1, (int) ceil($total / self::PER_PAGE));
		$page = min($filters['page'], $pages);
		$rows = $query->with(['currentVersion', 'offer', 'client'])
			->orderByDesc('updated_at')
			->orderByDesc('id')
			->offset(($page - 1) * self::PER_PAGE)
			->limit(self::PER_PAGE)
			->get();

		return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'filters' => $filters];
	}

	/** Autorzy umów widocznych dla użytkownika (do filtra i kolumny). @return array<int, string> */
	public function authors(): array
	{
		$ids = $this->policy->scope(Contract::query())->whereNotNull('author_id')->distinct()->pluck('author_id')->all();

		if (! $ids) {
			return [];
		}

		$names = [];

		foreach (get_users(['include' => $ids, 'fields' => ['ID', 'display_name'], 'orderby' => 'display_name']) as $user) {
			$names[(int) $user->ID] = $user->display_name;
		}

		return $names;
	}

	public static function normalize(array $input): array
	{
		return [
			'q' => mb_substr(trim((string) ($input['q'] ?? '')), 0, 100),
			'status' => ContractStatus::tryFrom((string) ($input['status'] ?? ''))?->value ?? '',
			'author' => max(0, (int) ($input['author'] ?? 0)),
			'page' => max(1, (int) ($input['page'] ?? 1)),
		];
	}
}
