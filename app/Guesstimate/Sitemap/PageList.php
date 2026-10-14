<?php

namespace App\Guesstimate\Sitemap;

use App\Guesstimate\Offers\OfferVersion;
use App\Guesstimate\Offers\Version\SitemapNode;
use App\Guesstimate\Pricing\Decimal;
use App\Guesstimate\Support\Format;
use App\Guesstimate\Support\Input;
use Illuminate\Support\Facades\DB;

/**
 * Uproszczona mapa strony (model budżetowy): płaska lista podstron w kolejności.
 * Każda podstrona to jedna jednostka kalkulacyjna; godziny UI / Dev są domyślne dla oferty,
 * a pojedyncze wartości można nadpisać w ustawieniach zaawansowanych (puste = domyślne).
 */
final class PageList
{
	public const MAX_PAGES = 300;

	public const HOURS = ['hours_ui_min', 'hours_ui_max', 'hours_dev_min', 'hours_dev_max'];

	/**
	 * @param  array<string, string>  $defaults  page_hours oferty (ui_min, ui_max, dev_min, dev_max)
	 * @return array{pages: list<array>, errors: array<string, string>}
	 */
	public static function parse(array $rows, array $defaults): array
	{
		$errors = [];
		$pages = [];
		$rows = array_values($rows);

		if (count($rows) > self::MAX_PAGES) {
			return ['pages' => [], 'errors' => ['pages' => sprintf('Mapa strony może mieć najwyżej %d podstron.', self::MAX_PAGES)]];
		}

		foreach ($rows as $i => $row) {
			$row = (array) $row;
			$name = Input::line($row['name'] ?? null);

			if ($name === null || mb_strlen($name) > 190) {
				$errors["pages.{$i}.name"] = sprintf('Podstrona %d: podaj nazwę (do 190 znaków).', $i + 1);
			}

			$page = ['name' => $name];

			foreach (self::HOURS as $field) {
				$raw = $row[$field] ?? null;

				if ($raw === null || trim((string) $raw) === '') {
					$page[$field] = null;

					continue;
				}

				$value = Input::hours($raw);

				if ($value === null) {
					$errors["pages.{$i}.{$field}"] = sprintf('Podstrona %d: nieprawidłowa liczba godzin.', $i + 1);
				}

				$page[$field] = $value;
			}

			foreach (['ui', 'dev'] as $part) {
				$min = $page["hours_{$part}_min"] ?? $defaults["{$part}_min"] ?? '0';
				$max = $page["hours_{$part}_max"] ?? $defaults["{$part}_max"] ?? '0';

				$min = Input::hours($min);
				$max = Input::hours($max);

				if ($min !== null && $max !== null && Decimal::of($min)->isGreaterThan($max)) {
					$errors["pages.{$i}.hours_{$part}_max"] = sprintf('Podstrona %d: maksimum godzin %s jest mniejsze niż minimum.', $i + 1, $part === 'dev' ? 'Dev' : 'UI');
				}
			}

			$pages[] = $page;
		}

		return ['pages' => $errors ? [] : $pages, 'errors' => $errors];
	}

	public static function save(OfferVersion $version, array $pages): void
	{
		DB::transaction(function () use ($version, $pages) {
			SitemapNode::where('version_id', $version->id)->delete();

			foreach ($pages as $position => $page) {
				SitemapNode::create(array_merge($page, [
					'version_id' => $version->id,
					'parent_id' => null,
					'position' => $position,
					'template_id' => null,
					'instances' => 1,
					'content_entry' => false,
				]));
			}
		});
	}

	/**
	 * Stan edytora: płaska lista w kolejności (dla starszych map — kolejność drzewa).
	 */
	public static function editorState(OfferVersion $version): array
	{
		$nodes = $version->nodes()->get();
		$byParent = $nodes->sortBy('position')->groupBy(fn ($n) => $n->parent_id ?? 0);
		$out = [];
		$walk = function ($parent) use (&$walk, &$out, $byParent) {
			foreach ($byParent->get($parent, collect()) as $node) {
				$row = ['name' => $node->name];

				foreach (self::HOURS as $field) {
					$row[$field] = $node->{$field} === null ? '' : Format::hoursInput($node->{$field});
				}

				$out[] = $row;
				$walk($node->id);
			}
		};
		$walk(0);

		return $out;
	}
}
