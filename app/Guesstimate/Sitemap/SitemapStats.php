<?php

namespace App\Guesstimate\Sitemap;

/**
 * Liczniki mapy strony. Rozróżniamy podstrony, szablony, podstrony indywidualne i elementy z treścią.
 */
final class SitemapStats
{
	public const KIND_UNIQUE = 'unique';
	public const KIND_TEMPLATE = 'template';

	/**
	 * @param  list<array{id: int|string, kind: string}>  $templates
	 * @param  list<array{template_id: int|string|null, instances: int|string, content_entry: bool|int}>  $nodes
	 * @return array{pages: int, templates: int, unique_pages: int, content_items: int}
	 */
	public static function compute(array $templates, array $nodes): array
	{
		$kinds = array_column($templates, 'kind', 'id');
		$used = [];
		$pages = 0;
		$unique = 0;
		$content = 0;

		foreach ($nodes as $node) {
			$instances = max(1, (int) $node['instances']);
			$pages += $instances;

			if (! empty($node['template_id'])) {
				$used[$node['template_id']] = true;

				if (($kinds[$node['template_id']] ?? null) === self::KIND_UNIQUE) {
					$unique++;
				}
			}

			if (! empty($node['content_entry'])) {
				$content += $instances;
			}
		}

		return [
			'pages' => $pages,
			'templates' => count($used),
			'unique_pages' => $unique,
			'content_items' => $content,
		];
	}
}
