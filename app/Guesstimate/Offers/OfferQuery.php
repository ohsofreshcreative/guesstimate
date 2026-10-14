<?php

namespace App\Guesstimate\Offers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lista ofert z wyszukiwaniem, filtrami i sortowaniem — zawsze ograniczona uprawnieniami użytkownika.
 */
class OfferQuery
{
	public const SORTS = [
		'number' => 'o.number',
		'client' => 'c.company_name',
		'project' => 'v.project_name',
		'value' => 'v.high_max',
		'status' => 'o.status',
		'created' => 'o.created_at',
		'updated' => 'o.updated_at',
		'viewed' => 'o.last_viewed_at',
		'views' => 'o.view_count',
	];

	/** Filtr wyświetleń (publikację pokazuje teraz kolumna i filtr „Status”). Stare wartości published/unpublished działają nadal. */
	public const PUBLICATION_FILTERS = [
		'viewed' => 'Wyświetlone przez klienta',
		'not_viewed' => 'Opublikowane, niewyświetlone',
	];

	private const LEGACY_ONLINE = ['published', 'unpublished'];

	public const PER_PAGE = 20;

	public function __construct(private OfferPolicy $policy)
	{
	}

	/**
	 * @param  array{q?: string, status?: string, type?: string, author?: int|string, client?: int|string, from?: string, to?: string, sort?: string, dir?: string, page?: int|string}  $filters
	 * @return array{rows: list<object>, total: int, page: int, pages: int, filters: array}
	 */
	public function search(array $filters, int $perPage = self::PER_PAGE): array
	{
		$filters = $this->normalize($filters);
		$query = $this->base();

		if ($filters['q'] !== '') {
			$like = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['q']) . '%';
			$query->where(function (Builder $q) use ($like) {
				$q->where('o.number', 'like', $like)
					->orWhere('v.project_name', 'like', $like)
					->orWhere('c.company_name', 'like', $like);
			});
		}

		// Status widoczny w tabeli (status oferty + publikacja, zob. OfferDisplayStatus).
		if ($filters['status'] !== '') {
			OfferDisplayStatus::from($filters['status'])->apply($query);
		}

		// Etap oferty (OfferStatus) — linki z kafelków „Oferty według statusu” na pulpicie.
		if ($filters['stage'] !== '') {
			$query->where('o.status', $filters['stage']);
		}

		if ($filters['type'] !== '') {
			$query->whereJsonContains('v.project_types', $filters['type']);
		}

		match ($filters['online']) {
			'published' => $query->where('o.publication_status', PublicationStatus::Published->value),
			'unpublished' => $query->where('o.publication_status', '!=', PublicationStatus::Published->value),
			'viewed' => $query->where('o.view_count', '>', 0),
			'not_viewed' => $query->where('o.publication_status', PublicationStatus::Published->value)->where('o.view_count', 0),
			default => null,
		};

		if ($filters['author'] && $this->policy->viewsAll()) {
			$query->where('o.author_id', $filters['author']);
		}

		if ($filters['client']) {
			$query->where('o.client_id', $filters['client']);
		}

		if ($filters['from'] !== '') {
			$query->where('o.created_at', '>=', get_gmt_from_date($filters['from'] . ' 00:00:00'));
		}

		if ($filters['to'] !== '') {
			$query->where('o.created_at', '<=', get_gmt_from_date($filters['to'] . ' 23:59:59'));
		}

		$total = (clone $query)->count('o.id');
		$pages = max(1, (int) ceil($total / $perPage));
		$page = min($filters['page'], $pages);

		$rows = $query
			->orderBy(self::SORTS[$filters['sort']], $filters['dir'])
			->orderBy('o.id', $filters['dir'])
			->offset(($page - 1) * $perPage)
			->limit($perPage)
			->get()
			->all();

		return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'filters' => $filters];
	}

	/**
	 * @return list<object>
	 */
	public function forClient(int $clientId): array
	{
		return $this->base()->where('o.client_id', $clientId)->orderByDesc('o.created_at')->get()->all();
	}

	/**
	 * Autorzy ofert widocznych dla użytkownika (do filtra).
	 *
	 * @return array<int, string>
	 */
	public function authors(): array
	{
		return $this->policy->scope(DB::table('gst_offers as o')->whereNull('o.deleted_at'))
			->join('users as u', 'u.ID', '=', 'o.author_id')
			->distinct()
			->orderBy('u.display_name')
			->pluck('u.display_name', 'u.ID')
			->all();
	}

	private function base(): Builder
	{
		$query = DB::table('gst_offers as o')
			->join('gst_offer_versions as v', 'v.id', '=', 'o.current_version_id')
			->leftJoin('gst_clients as c', 'c.id', '=', 'o.client_id')
			->leftJoin('users as u', 'u.ID', '=', 'o.author_id')
			->leftJoin('gst_publications as p', 'p.id', '=', 'o.publication_id')
			->whereNull('o.deleted_at')
			->select([
				'o.id', 'o.number', 'o.status', 'o.created_at', 'o.updated_at', 'o.author_id', 'o.client_id',
				'o.publication_status', 'o.public_token', 'o.first_viewed_at', 'o.last_viewed_at', 'o.view_count',
				'v.version_no', 'v.project_name', 'v.project_types', 'v.project_type', 'v.calc_model', 'v.selected_variant', 'v.budget', 'v.currency', 'v.result_error',
				'v.low_min', 'v.low_max', 'v.mid_min', 'v.mid_max', 'v.high_min', 'v.high_max',
				'c.company_name as client_name', 'u.display_name as author_name',
				'o.current_version_id', 'v.updated_at as version_updated_at', 'p.version_id as publication_version_id', 'p.published_at as published_at',
			]);

		return $this->policy->scope($query);
	}

	private function normalize(array $filters): array
	{
		$date = fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
		$sort = $filters['sort'] ?? 'created';

		return [
			'q' => mb_substr(trim((string) ($filters['q'] ?? '')), 0, 100),
			'status' => OfferDisplayStatus::tryFrom((string) ($filters['status'] ?? ''))?->value ?? '',
			'stage' => OfferStatus::tryFrom((string) ($filters['etap'] ?? ''))?->value ?? '',
			'type' => array_key_exists((string) ($filters['type'] ?? ''), OfferVersion::PROJECT_TYPES) ? (string) $filters['type'] : '',
			'online' => array_key_exists((string) ($filters['online'] ?? ''), self::PUBLICATION_FILTERS) || in_array((string) ($filters['online'] ?? ''), self::LEGACY_ONLINE, true) ? (string) $filters['online'] : '',
			'author' => max(0, (int) ($filters['author'] ?? 0)),
			'client' => max(0, (int) ($filters['client'] ?? 0)),
			'from' => $date($filters['from'] ?? ''),
			'to' => $date($filters['to'] ?? ''),
			'sort' => array_key_exists($sort, self::SORTS) ? $sort : 'created',
			'dir' => ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
			'page' => max(1, (int) ($filters['page'] ?? 1)),
		];
	}
}
