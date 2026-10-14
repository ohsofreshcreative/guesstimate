<?php

namespace App\Guesstimate\Document;

/**
 * Dane widoku dokumentu z opublikowanego snapshotu. Obsługuje też snapshoty sprzed układu stron
 * (schema 1): pokazujemy wtedy okładkę, treści, wycenę — bez stron, których dane nie zostały zapisane.
 */
final class DocumentView
{
	public static function fromSnapshot(array $snapshot): array
	{
		$doc = $snapshot['document'] ?? null;

		if (! is_array($doc)) {
			$doc = self::legacy($snapshot);
		}

		$doc['pages'] = array_map(fn ($page) => self::page((array) $page, $snapshot), array_values((array) ($doc['pages'] ?? [])));

		return array_merge($snapshot, ['document' => $doc]);
	}

	/**
	 * Strony ze snapshotów sprzed edytora wizualnego nie mają układu, zdjęcia ani nagłówka — wartości domyślne.
	 */
	private static function page(array $page, array $snapshot): array
	{
		$key = (string) ($page['key'] ?? 'cover');
		$page['layout'] = Pages::layout($key, $page['layout'] ?? null);
		$page['image'] = Pages::hasMedia($page['layout']) ? ($page['image'] ?? null) : null;
		$page['hidden'] ??= null;

		if (! isset($page['heading'])) {
			$page['heading'] = Markup::parts(Pages::HEADINGS[$key] ?? '');

			if ($key === 'pricing' && ! empty($snapshot['project_type'])) {
				$page['heading'][] = [' ' . mb_strtolower((string) $snapshot['project_type']), false];
			}
		}

		return $page;
	}

	private static function legacy(array $snapshot): array
	{
		$content = $snapshot['content'] ?? ['situation' => '', 'blocks' => []];
		$pages = [['key' => 'cover', 'label' => Pages::TYPES['cover'], 'background' => null, 'fit' => 'cover']];

		if (trim(strip_tags((string) ($content['situation'] ?? ''))) !== '') {
			$pages[] = ['key' => 'situation', 'label' => Pages::TYPES['situation'], 'background' => null, 'fit' => 'cover'];
		}

		if (array_filter($content['blocks'] ?? [], fn ($b) => ($b['title'] ?? '') !== '')) {
			$pages[] = ['key' => 'solution', 'label' => Pages::TYPES['solution'], 'background' => null, 'fit' => 'cover'];
		}

		$pages[] = ['key' => 'pricing', 'label' => Pages::TYPES['pricing'], 'background' => null, 'fit' => 'cover'];

		return [
			'pages' => $pages,
			'logo' => null,
			'website' => '',
			'validity_days' => 0,
			'cover' => ['heading' => Markup::parts($snapshot['title'] ?? ''), 'lead' => '', 'date' => null],
			'includes' => array_map(fn ($f) => $f['name'], $snapshot['summary']['features'] ?? []),
			'extras' => ['intro' => '', 'items' => []],
			'references' => ['intro' => [], 'items' => []],
			'next_steps' => ['items' => [], 'note' => []],
			'contact' => null,
		];
	}
}
