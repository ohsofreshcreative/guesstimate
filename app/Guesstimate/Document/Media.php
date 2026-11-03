<?php

namespace App\Guesstimate\Document;

use App\Guesstimate\Media\MediaAttributes;
use App\Guesstimate\Media\MediaLibrary;

/**
 * Obsługa grafik przez bibliotekę mediów WordPressa (bez własnego magazynu plików).
 */
final class Media
{
	public const MIMES = ['image/jpeg', 'image/png', 'image/webp'];
	public const MAX_BYTES = 15 * 1024 * 1024;
	/** Limit rozdzielczości: większe obrazy mogą wyczerpać pamięć przy generowaniu rozmiarów. */
	public const MAX_PIXELS = 50_000_000;

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
	 * Najwyższy dopuszczalny rozmiar pliku: limit aplikacji albo niższy limit serwera (PHP).
	 */
	public static function maxBytes(): int
	{
		$server = (int) wp_max_upload_size();

		return $server > 0 ? min(self::MAX_BYTES, $server) : self::MAX_BYTES;
	}

	/**
	 * Przesyła plik z pola formularza do biblioteki mediów. Zwraca [ID załącznika | null, błąd | null, czy to duplikat].
	 *
	 * @return array{0: int|null, 1: string|null, 2?: bool}
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

		if ($file['size'] > self::maxBytes()) {
			return [null, sprintf('Plik może mieć najwyżej %d MB.', floor(self::maxBytes() / 1024 / 1024))];
		}

		$check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);

		if (! in_array($check['type'] ?? '', self::MIMES, true)) {
			return [null, 'Obsługiwane formaty: JPG, PNG i WebP.'];
		}

		$dimensions = @getimagesize($file['tmp_name']);

		if ($dimensions && $dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
			return [null, 'Obraz ma zbyt dużą rozdzielczość (limit 50 megapikseli).'];
		}

		// Ten sam plik przesłany ponownie wskazuje istniejący załącznik (także zarchiwizowany) zamiast tworzyć kopię.
		$hash = hash_file('sha256', $file['tmp_name']);

		if ($existing = MediaLibrary::findDuplicate($hash)) {
			MediaLibrary::restore($existing);

			return [$existing, null, true];
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$id = media_handle_upload($field, 0, [], ['test_form' => false, 'mimes' => ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']]);

		if (is_wp_error($id)) {
			return [null, 'Biblioteka mediów odrzuciła plik: ' . $id->get_error_message()];
		}

		update_post_meta((int) $id, MediaAttributes::META_HASH, $hash);

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
	 * @param  array{folder?: int|null, tags?: list<int>, archived?: bool}  $filters  MediaLibrary::queryArgs()
	 * @return array{items: list<array{id: int, name: string, thumb: string|null, width: int|null, height: int|null, mime: string, date: string, alt: string}>, more: bool}
	 */
	public static function browse(string $search = '', int $page = 1, int $perPage = 30, array $filters = []): array
	{
		$query = new \WP_Query(MediaLibrary::queryArgs($filters) + [
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

			return [
				'id' => $p->ID,
				'name' => $p->post_title ?: basename((string) get_attached_file($p->ID)),
				'thumb' => self::thumb($p->ID),
				'url' => wp_get_attachment_image_url($p->ID, 'large') ?: null,
				'width' => $width,
				'height' => $height,
				'mime' => $p->post_mime_type,
				'date' => wp_date('d.m.Y', strtotime($p->post_date_gmt . ' UTC')),
				'alt' => (string) get_post_meta($p->ID, MediaAttributes::META_ALT, true),
			];
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
			'meta_query' => [MediaLibrary::visible()],
		]);

		return array_map(fn ($p) => ['id' => $p->ID, 'name' => $p->post_title ?: basename((string) get_attached_file($p->ID)), 'thumb' => self::thumb($p->ID)], $posts);
	}
}
