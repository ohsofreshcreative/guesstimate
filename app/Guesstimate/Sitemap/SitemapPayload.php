<?php

namespace App\Guesstimate\Sitemap;

use App\Guesstimate\Pricing\Decimal;
use App\Guesstimate\Support\Input;

/**
 * Walidacja i normalizacja danych z edytora mapy strony (czysta logika, bez bazy).
 *
 * Węzły przychodzą w kolejności drzewa (pre-order) z głębokością; rodzica wyznaczamy z głębokości.
 * Szablon podstrony indywidualnej nie może być współdzielony — dodatkowe użycia dostają własną kopię.
 */
final class SitemapPayload
{
	public const MAX_NODES = 1000;
	public const MAX_DEPTH = 8;
	public const MAX_INSTANCES = 100000;

	/**
	 * @return array{templates: list<array>, nodes: list<array>, errors: list<string>}
	 */
	public static function parse(array $payload): array
	{
		$errors = [];
		$templates = [];

		foreach ((array) ($payload['templates'] ?? []) as $i => $raw) {
			$key = self::key($raw['key'] ?? null);
			$name = self::text($raw['name'] ?? '', 190);
			$kind = in_array($raw['kind'] ?? null, [SitemapStats::KIND_UNIQUE, SitemapStats::KIND_TEMPLATE], true) ? $raw['kind'] : null;

			if ($key === null || $kind === null) {
				$errors[] = sprintf('Szablon nr %d ma nieprawidłowe dane.', $i + 1);

				continue;
			}

			$label = $name ?? sprintf('Szablon nr %d', $i + 1);

			if ($name === null) {
				$errors[] = sprintf('Podaj nazwę szablonu nr %d.', $i + 1);
			}

			$hours = [];

			foreach (['ui', 'dev', 'content'] as $group) {
				[$min, $max, $error] = self::range($raw[$group . '_min'] ?? '0', $raw[$group . '_max'] ?? '0', sprintf('Szablon „%s”', $label));

				if ($error) {
					$errors[] = $error;
				}

				$hours[$group . '_min'] = $min;
				$hours[$group . '_max'] = $max;
			}

			$templates[$key] = array_merge([
				'key' => $key,
				'name' => $label,
				'kind' => $kind,
				'page_type_id' => isset($raw['page_type_id']) && $raw['page_type_id'] !== '' ? (int) $raw['page_type_id'] : null,
				'page_type_name' => self::text($raw['page_type_name'] ?? '', 150) ?? $label,
			], $hours);
		}

		$nodes = [];
		$stack = [];
		$previousDepth = -1;
		$rawNodes = array_values((array) ($payload['nodes'] ?? []));

		if (count($rawNodes) > self::MAX_NODES) {
			return ['templates' => [], 'nodes' => [], 'errors' => [sprintf('Mapa strony może mieć najwyżej %d pozycji.', self::MAX_NODES)]];
		}

		foreach ($rawNodes as $i => $raw) {
			$position = $i + 1;
			$name = self::text($raw['name'] ?? '', 190);
			$label = $name ?? sprintf('pozycja nr %d', $position);
			$depth = filter_var($raw['depth'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => self::MAX_DEPTH]]);

			if ($name === null) {
				$errors[] = sprintf('Podaj nazwę podstrony (pozycja nr %d).', $position);
			}

			if ($depth === false || $depth > $previousDepth + 1) {
				$errors[] = sprintf('Podstrona „%s” ma nieprawidłowe miejsce w hierarchii.', $label);
				$depth = max(0, min($previousDepth + 1, (int) $depth));
			}

			$templateKey = self::key($raw['template_key'] ?? null);

			if ($templateKey === null || ! isset($templates[$templateKey])) {
				$errors[] = sprintf('Wybierz typ podstrony „%s”.', $label);
				$templateKey = null;
			}

			$instances = filter_var($raw['instances'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_INSTANCES]]);

			if ($instances === false) {
				$errors[] = sprintf('Liczba sztuk podstrony „%s” musi być liczbą od 1 do %d.', $label, self::MAX_INSTANCES);
				$instances = 1;
			}

			if ($templateKey && $templates[$templateKey]['kind'] === SitemapStats::KIND_UNIQUE) {
				$instances = 1;
			}

			$contentEntry = filter_var($raw['content_entry'] ?? false, FILTER_VALIDATE_BOOLEAN);
			[$cMin, $cMax, $cError] = self::range($raw['content_min'] ?? '0', $raw['content_max'] ?? '0', sprintf('Treść podstrony „%s”', $label));
			[$eMin, $eMax, $eError] = self::range($raw['extra_min'] ?? '0', $raw['extra_max'] ?? '0', sprintf('Dodatkowe godziny podstrony „%s”', $label));

			foreach ([$cError, $eError] as $error) {
				if ($error) {
					$errors[] = $error;
				}
			}

			$stack = array_slice($stack, 0, $depth);
			$parentIndex = $depth > 0 ? ($stack[$depth - 1] ?? null) : null;
			$stack[$depth] = count($nodes);
			$previousDepth = $depth;

			$nodes[] = [
				'index' => count($nodes),
				'parent_index' => $parentIndex,
				'depth' => $depth,
				'name' => $label,
				'template_key' => $templateKey,
				'instances' => $instances,
				'content_entry' => $contentEntry,
				'content_min' => $cMin,
				'content_max' => $cMax,
				'extra_min' => $eMin,
				'extra_max' => $eMax,
				'notes' => self::text($raw['notes'] ?? '', 2000),
			];
		}

		[$templates, $nodes] = self::splitSharedUniqueTemplates($templates, $nodes);

		$used = array_flip(array_filter(array_column($nodes, 'template_key')));
		$templates = array_values(array_filter($templates, fn ($template) => isset($used[$template['key']])));

		return ['templates' => $templates, 'nodes' => $nodes, 'errors' => array_values(array_unique($errors))];
	}

	/**
	 * Szablon indywidualny używany przez kilka podstron dostaje kopię dla każdego kolejnego użycia.
	 */
	private static function splitSharedUniqueTemplates(array $templates, array $nodes): array
	{
		$seen = [];

		foreach ($nodes as $i => $node) {
			$key = $node['template_key'];

			if ($key === null || $templates[$key]['kind'] !== SitemapStats::KIND_UNIQUE) {
				continue;
			}

			if (! isset($seen[$key])) {
				$seen[$key] = true;

				continue;
			}

			$copyKey = $key . '-' . $i;
			$templates[$copyKey] = array_merge($templates[$key], ['key' => $copyKey, 'name' => $node['name']]);
			$nodes[$i]['template_key'] = $copyKey;
		}

		return [$templates, $nodes];
	}

	/**
	 * @return array{0: string, 1: string, 2: string|null}
	 */
	private static function range(mixed $min, mixed $max, string $label): array
	{
		$minValue = Input::hours($min === null || $min === '' ? '0' : $min);
		$maxValue = Input::hours($max === null || $max === '' ? '0' : $max);

		if ($minValue === null || $maxValue === null) {
			return ['0.00', '0.00', $label . ': podaj godziny jako liczbę, np. 4 albo 4,5.'];
		}

		if (Decimal::of($minValue)->isGreaterThan($maxValue)) {
			return [$minValue, $maxValue, $label . ': minimum godzin nie może być większe niż maksimum.'];
		}

		return [$minValue, $maxValue, null];
	}

	private static function key(mixed $value): ?string
	{
		$value = is_scalar($value) ? (string) $value : '';

		return preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $value) ? $value : null;
	}

	private static function text(mixed $value, int $max): ?string
	{
		$value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)));

		return $value === '' ? null : mb_substr($value, 0, $max);
	}
}
