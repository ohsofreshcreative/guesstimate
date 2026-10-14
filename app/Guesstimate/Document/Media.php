<?php

namespace App\Guesstimate\Document;

/**
 * Obsługa grafik przez bibliotekę mediów WordPressa (bez własnego magazynu plików).
 */
final class Media
{
	public const MIMES = ['image/jpeg', 'image/png', 'image/webp'];
	public const MAX_BYTES = 15 * 1024 * 1024;

	/** Proporcje strony dokumentu: A4 poziomo (297 × 210 mm). */
	public const RATIO = 297 / 210;

	public static function ratioWarning(?int $width, ?int $height): bool
	{
		if (! $width || ! $height) {
			return false;
		}

		return abs(($width / $height) / self::RATIO - 1) > 0.02;
	}

	/**
	 * Przesyła plik z pola formularza do biblioteki mediów. Zwraca [ID załącznika | null, błąd | null].
	 *
	 * @return array{0: int|null, 1: string|null}
	 */
	public static function upload(string $field): array
	{
		$file = $_FILES[$field] ?? null;

		if (! is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
			return [null, 'Wybierz plik graficzny.'];
		}

		if ($file['error'] !== UPLOAD_ERR_OK) {
			return [null, $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
				? 'Plik jest za duży dla ustawień serwera (upload_max_filesize).'
				: 'Nie udało się przesłać pliku.'];
		}

		if ($file['size'] > self::MAX_BYTES) {
			return [null, 'Plik może mieć najwyżej 15 MB.'];
		}

		$check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);

		if (! in_array($check['type'] ?? '', self::MIMES, true)) {
			return [null, 'Obsługiwane formaty: JPG, PNG i WebP.'];
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$id = media_handle_upload($field, 0, [], ['test_form' => false, 'mimes' => ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']]);

		if (is_wp_error($id)) {
			return [null, 'Biblioteka mediów odrzuciła plik: ' . $id->get_error_message()];
		}

		return [(int) $id, null];
	}

	public static function isImage(int $id): bool
	{
		return $id > 0 && get_post_type($id) === 'attachment' && in_array(get_post_mime_type($id), self::MIMES, true);
	}

	/**
	 * @return array{0: int|null, 1: int|null}
	 */
	public static function size(int $id): array
	{
		$meta = wp_get_attachment_metadata($id);

		return [isset($meta['width']) ? (int) $meta['width'] : null, isset($meta['height']) ? (int) $meta['height'] : null];
	}

	/**
	 * Dane obrazu do snapshotu: adres pełny, srcset i wymiary (z rozmiarów wygenerowanych przez WordPress).
	 */
	public static function image(?int $id, string $size = 'full'): ?array
	{
		if (! $id || ! self::isImage($id)) {
			return null;
		}

		$src = wp_get_attachment_image_src($id, $size);

		if (! $src) {
			return null;
		}

		return [
			'id' => $id,
			'url' => $src[0],
			'width' => (int) $src[1],
			'height' => (int) $src[2],
			'srcset' => wp_get_attachment_image_srcset($id, $size) ?: null,
			'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
		];
	}

	public static function thumb(?int $id): ?string
	{
		if (! $id) {
			return null;
		}

		$src = wp_get_attachment_image_src($id, 'medium');

		return $src ? $src[0] : null;
	}

	/**
	 * Obrazy z biblioteki mediów do wyboru w edytorze (najnowsze, z wyszukiwaniem i stronicowaniem).
	 *
	 * @return array{items: list<array{id: int, name: string, thumb: string|null, width: int|null, height: int|null}>, more: bool}
	 */
	public static function browse(string $search = '', int $page = 1, int $perPage = 30): array
	{
		$query = new \WP_Query([
			'post_type' => 'attachment',
			'post_mime_type' => self::MIMES,
			'post_status' => 'inherit',
			'posts_per_page' => $perPage,
			'paged' => max(1, $page),
			'orderby' => 'date',
			'order' => 'DESC',
			's' => mb_substr(trim($search), 0, 100),
			'no_found_rows' => false,
		]);

		$items = array_map(function ($p) {
			[$width, $height] = self::size($p->ID);

			return ['id' => $p->ID, 'name' => $p->post_title ?: basename((string) get_attached_file($p->ID)), 'thumb' => self::thumb($p->ID), 'width' => $width, 'height' => $height];
		}, $query->posts);

		return ['items' => $items, 'more' => $page < (int) $query->max_num_pages];
	}

	/**
	 * Ostatnie obrazy z biblioteki mediów (do wyboru zamiast przesyłania).
	 *
	 * @return list<array{id: int, name: string, thumb: string|null}>
	 */
	public static function recent(int $limit = 48, array $exclude = []): array
	{
		$posts = get_posts([
			'post_type' => 'attachment',
			'post_mime_type' => self::MIMES,
			'post_status' => 'inherit',
			'numberposts' => $limit,
			'orderby' => 'date',
			'order' => 'DESC',
			'exclude' => $exclude,
		]);

		return array_map(fn ($p) => ['id' => $p->ID, 'name' => $p->post_title ?: basename((string) get_attached_file($p->ID)), 'thumb' => self::thumb($p->ID)], $posts);
	}
}
