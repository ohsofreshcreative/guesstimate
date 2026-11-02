<?php

namespace App\Guesstimate\Media;

use App\Guesstimate\Document\Media;

/**
 * Biblioteka grafik na bibliotece mediów WordPressa. Pliki istnieją raz: oferty wskazują załącznik po ID.
 *
 * Foldery i tagi to taksonomie załączników, opis to tytuł, alt i meta (MediaAttributes), relacje z ofertami
 * liczy MediaUsage. „Usunięcie” używanej grafiki tylko ją archiwizuje: znika z wyboru, a plik zostaje,
 * więc oferty robocze i opublikowane się nie zmieniają.
 */
final class MediaLibrary
{
	public const FOLDER = 'gst_media_folder';
	public const TAG = 'gst_media_tag';

	public static function register(): void
	{
		$common = [
			'public' => false,
			'rewrite' => false,
			'query_var' => false,
			// Domyślny licznik pomija załączniki bez rodzica (post_status = inherit).
			'update_count_callback' => '_update_generic_term_count',
		];

		register_taxonomy(self::FOLDER, 'attachment', $common + ['hierarchical' => true, 'label' => 'Foldery grafik']);
		register_taxonomy(self::TAG, 'attachment', $common + ['hierarchical' => false, 'label' => 'Tagi grafik']);
	}

	/**
	 * Warunek ukrywający zarchiwizowane grafiki (do meta_query zapytań o załączniki).
	 */
	public static function visible(): array
	{
		return ['key' => MediaAttributes::META_ARCHIVED, 'compare' => 'NOT EXISTS'];
	}

	/**
	 * Argumenty WP_Query dla filtrów biblioteki.
	 *
	 * @param  array{folder?: int|null, tags?: list<int>, archived?: bool}  $filters
	 */
	public static function queryArgs(array $filters = []): array
	{
		$args = ['meta_query' => [! empty($filters['archived']) ? ['key' => MediaAttributes::META_ARCHIVED, 'compare' => 'EXISTS'] : self::visible()]];
		$taxonomies = [];

		if (! empty($filters['folder'])) {
			$taxonomies[] = ['taxonomy' => self::FOLDER, 'field' => 'term_id', 'terms' => [(int) $filters['folder']]];
		}

		if (! empty($filters['tags'])) {
			$taxonomies[] = ['taxonomy' => self::TAG, 'field' => 'term_id', 'terms' => array_map('intval', $filters['tags']), 'operator' => 'AND'];
		}

		if ($taxonomies) {
			$args['tax_query'] = $taxonomies;
		}

		return $args;
	}

	/**
	 * Filtry z adresu zapytania (folder, tag[], archiwum).
	 *
	 * @return array{folder: int|null, tags: list<int>, archived: bool}
	 */
	public static function filters(array $query): array
	{
		$folder = (int) ($query['folder'] ?? 0);

		return [
			'folder' => $folder > 0 ? $folder : null,
			'tags' => array_values(array_filter(array_map('intval', (array) ($query['tag'] ?? [])), fn ($id) => $id > 0)),
			'archived' => ! empty($query['archived']),
		];
	}

	/**
	 * @return array{id: int, title: string, alt: string, author: string, license: string, source_url: string, folder: int|null, tags: list<string>, archived: bool, uploaded_by: int}
	 */
	public static function attributes(int $id): array
	{
		$folders = wp_get_object_terms($id, self::FOLDER, ['fields' => 'ids']);
		$tags = wp_get_object_terms($id, self::TAG, ['fields' => 'names']);

		return [
			'id' => $id,
			'title' => (string) get_post_field('post_title', $id),
			'alt' => (string) get_post_meta($id, MediaAttributes::META_ALT, true),
			'author' => (string) get_post_meta($id, MediaAttributes::META_AUTHOR, true),
			'license' => (string) get_post_meta($id, MediaAttributes::META_LICENSE, true),
			'source_url' => (string) get_post_meta($id, MediaAttributes::META_SOURCE, true),
			'folder' => is_array($folders) && $folders ? (int) $folders[0] : null,
			'tags' => is_array($tags) ? array_values($tags) : [],
			'archived' => self::isArchived($id),
			'uploaded_by' => (int) get_post_field('post_author', $id),
		];
	}

	/**
	 * Zapisuje opis grafiki (dane po MediaAttributes::validate). Plik i rozmiary zostają bez zmian.
	 * Grafika ma najwyżej jeden folder. Zapisane publikacje mają własną kopię alt — nie zmieniają się.
	 */
	public static function update(int $id, array $data): void
	{
		wp_update_post(wp_slash(['ID' => $id, 'post_title' => $data['title']]));

		foreach ([
			MediaAttributes::META_ALT => $data['alt'],
			MediaAttributes::META_AUTHOR => $data['author'],
			MediaAttributes::META_LICENSE => $data['license'],
			MediaAttributes::META_SOURCE => $data['source_url'],
		] as $key => $value) {
			if ($value === null) {
				delete_post_meta($id, $key);
			} else {
				update_post_meta($id, $key, wp_slash($value));
			}
		}

		wp_set_object_terms($id, $data['folder'] ? [(int) $data['folder']] : [], self::FOLDER);
		wp_set_object_terms($id, $data['tags'], self::TAG);
	}

	/**
	 * @return list<array{id: int, name: string, parent: int, count: int}>
	 */
	public static function folders(): array
	{
		$terms = get_terms(['taxonomy' => self::FOLDER, 'hide_empty' => false, 'orderby' => 'name']);

		return is_array($terms)
			? array_map(fn ($t) => ['id' => $t->term_id, 'name' => $t->name, 'parent' => $t->parent, 'count' => $t->count], $terms)
			: [];
	}

	/**
	 * Foldery w kolejności drzewa, z głębokością do wcięcia na liście.
	 *
	 * @param  list<array{id: int, name: string, parent: int, count: int}>  $folders
	 * @return list<array{id: int, name: string, parent: int, count: int, depth: int}>
	 */
	public static function tree(array $folders, int $parent = 0, int $depth = 0): array
	{
		$out = [];

		foreach ($folders as $folder) {
			if ($folder['parent'] === $parent) {
				$out[] = $folder + ['depth' => $depth];
				$out = array_merge($out, self::tree($folders, $folder['id'], $depth + 1));
			}
		}

		return $out;
	}

	/**
	 * @return list<array{id: int, name: string, count: int}>
	 */
	public static function tags(): array
	{
		$terms = get_terms(['taxonomy' => self::TAG, 'hide_empty' => true, 'orderby' => 'name']);

		return is_array($terms) ? array_map(fn ($t) => ['id' => $t->term_id, 'name' => $t->name, 'count' => $t->count], $terms) : [];
	}

	/**
	 * @return array{0: int|null, 1: string|null} [ID folderu | null, błąd | null]
	 */
	public static function createFolder(string $name, int $parent = 0): array
	{
		$result = wp_insert_term(wp_slash($name), self::FOLDER, ['parent' => $parent]);

		return is_wp_error($result) ? [null, self::folderError($result)] : [(int) $result['term_id'], null];
	}

	public static function renameFolder(int $id, string $name): ?string
	{
		$result = wp_update_term($id, self::FOLDER, ['name' => wp_slash($name)]);

		return is_wp_error($result) ? self::folderError($result) : null;
	}

	public static function deleteFolder(int $id): void
	{
		wp_delete_term($id, self::FOLDER);
	}

	private static function folderError(\WP_Error $error): string
	{
		return $error->get_error_code() === 'term_exists' ? 'Folder o tej nazwie już istnieje.' : 'Nie udało się zapisać folderu.';
	}

	/**
	 * Istniejąca grafika o tej samej zawartości, jeśli jej plik nadal jest na dysku.
	 */
	public static function findDuplicate(string $hash): ?int
	{
		$ids = get_posts([
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'fields' => 'ids',
			'numberposts' => 5,
			'orderby' => 'ID',
			'order' => 'ASC',
			'meta_key' => MediaAttributes::META_HASH,
			'meta_value' => $hash,
		]);

		foreach ($ids as $id) {
			$file = get_attached_file($id);

			if ($file && is_file($file) && Media::isImage($id)) {
				return (int) $id;
			}
		}

		return null;
	}

	public static function isArchived(int $id): bool
	{
		return metadata_exists('post', $id, MediaAttributes::META_ARCHIVED);
	}

	/**
	 * Usuwa grafikę z biblioteki. Używana (albo chroniona przez filtr) trafia do archiwum, nieużywana znika z plikiem.
	 *
	 * @return array{result: 'deleted'|'archived'|'missing', usage: array}
	 */
	public static function remove(int $id): array
	{
		if (! Media::isImage($id)) {
			return ['result' => 'missing', 'usage' => MediaUsage::NONE];
		}

		$usage = MediaUsage::forAttachment($id);

		if (! MediaUsage::inUse($usage) && wp_delete_attachment($id, true)) {
			return ['result' => 'deleted', 'usage' => $usage];
		}

		update_post_meta($id, MediaAttributes::META_ARCHIVED, time());

		return ['result' => 'archived', 'usage' => $usage];
	}

	public static function restore(int $id): void
	{
		delete_post_meta($id, MediaAttributes::META_ARCHIVED);
	}
}
