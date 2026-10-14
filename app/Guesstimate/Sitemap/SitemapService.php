<?php

namespace App\Guesstimate\Sitemap;

use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Offers\Version\SitemapNode;
use App\Guesstimate\Offers\Version\VersionTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Zapis mapy strony wersji oferty oraz przygotowanie danych dla edytora.
 */
class SitemapService
{
	/**
	 * Zastępuje mapę strony i szablony edytowalnej wersji danymi z edytora (w transakcji).
	 *
	 * @param  array{templates: list<array>, nodes: list<array>}  $parsed  wynik SitemapPayload::parse()
	 */
	public function save(OfferVersion $version, array $parsed): void
	{
		DB::transaction(function () use ($version, $parsed) {
			SitemapNode::where('version_id', $version->id)->delete();
			VersionTemplate::where('version_id', $version->id)->delete();

			$templateIds = [];

			foreach ($parsed['templates'] as $template) {
				$templateIds[$template['key']] = VersionTemplate::create([
					'version_id' => $version->id,
					'page_type_id' => $template['page_type_id'],
					'page_type_name' => $template['page_type_name'],
					'kind' => $template['kind'],
					'name' => $template['name'],
					'ui_min' => $template['ui_min'],
					'ui_max' => $template['ui_max'],
					'dev_min' => $template['dev_min'],
					'dev_max' => $template['dev_max'],
					'content_min' => $template['content_min'],
					'content_max' => $template['content_max'],
					'client_key' => $template['key'],
				])->id;
			}

			$nodeIds = [];
			$positions = [];

			foreach ($parsed['nodes'] as $node) {
				$parentId = $node['parent_index'] !== null ? $nodeIds[$node['parent_index']] : null;
				$siblingKey = $parentId ?? 0;
				$positions[$siblingKey] = ($positions[$siblingKey] ?? -1) + 1;

				$nodeIds[$node['index']] = SitemapNode::create([
					'version_id' => $version->id,
					'parent_id' => $parentId,
					'position' => $positions[$siblingKey],
					'name' => $node['name'],
					'template_id' => $node['template_key'] ? $templateIds[$node['template_key']] : null,
					'instances' => $node['instances'],
					'content_entry' => $node['content_entry'],
					'content_min' => $node['content_min'],
					'content_max' => $node['content_max'],
					'extra_min' => $node['extra_min'],
					'extra_max' => $node['extra_max'],
					'notes' => $node['notes'],
				])->id;
			}
		});
	}

	/**
	 * Stan edytora: szablony i węzły w kolejności drzewa (pre-order) z głębokością.
	 */
	public function editorState(OfferVersion $version): array
	{
		$templates = VersionTemplate::where('version_id', $version->id)->orderBy('id')->get();
		$nodes = SitemapNode::where('version_id', $version->id)->orderBy('position')->get()->groupBy(fn ($node) => $node->parent_id ?? 0);
		$templateKeys = [];

		$templatesState = $templates->map(function (VersionTemplate $template) use (&$templateKeys) {
			$key = $template->client_key ?: 't' . $template->id;
			$templateKeys[$template->id] = $key;

			return [
				'key' => $key,
				'page_type_id' => $template->page_type_id,
				'page_type_name' => $template->page_type_name,
				'kind' => $template->kind,
				'name' => $template->name,
				'ui_min' => self::h($template->ui_min), 'ui_max' => self::h($template->ui_max),
				'dev_min' => self::h($template->dev_min), 'dev_max' => self::h($template->dev_max),
				'content_min' => self::h($template->content_min), 'content_max' => self::h($template->content_max),
			];
		})->values()->all();

		$flat = [];
		$walk = function ($parentId, int $depth) use (&$walk, &$flat, $nodes, $templateKeys) {
			foreach ($nodes->get($parentId ?? 0, collect()) as $node) {
				$flat[] = [
					'key' => 'n' . $node->id,
					'depth' => $depth,
					'name' => $node->name,
					'template_key' => $node->template_id ? ($templateKeys[$node->template_id] ?? null) : null,
					'instances' => $node->instances,
					'content_entry' => $node->content_entry,
					'content_min' => self::h($node->content_min),
					'content_max' => self::h($node->content_max),
					'extra_min' => self::h($node->extra_min),
					'extra_max' => self::h($node->extra_max),
					'notes' => (string) $node->notes,
				];
				$walk($node->id, $depth + 1);
			}
		};
		$walk(null, 0);

		return ['templates' => $templatesState, 'nodes' => $flat];
	}

	private static function h(mixed $value): string
	{
		$value = (string) $value;

		if (str_contains($value, '.')) {
			$value = rtrim(rtrim($value, '0'), '.');
		}

		return $value === '' ? '0' : $value;
	}
}
