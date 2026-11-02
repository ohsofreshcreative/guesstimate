<?php

namespace App\Guesstimate\Media;

use App\Guesstimate\Support\Input;

/**
 * Opis grafiki w bibliotece: nazwa, tekst alternatywny, autorstwo, licencja, źródło, folder i tagi.
 * Wartości trzymamy w załączniku WordPressa (tytuł, alt, meta, taksonomie), bez własnego magazynu.
 */
final class MediaAttributes
{
	public const META_ALT = '_wp_attachment_image_alt';
	public const META_AUTHOR = '_gst_media_author';
	public const META_LICENSE = '_gst_media_license';
	public const META_SOURCE = '_gst_media_source_url';
	public const META_HASH = '_gst_media_sha256';
	public const META_ARCHIVED = '_gst_media_archived_at';

	public const LICENSES = [
		'own' => 'Materiał własny',
		'client' => 'Materiał klienta',
		'stock' => 'Bank zdjęć',
		'creative_commons' => 'Creative Commons',
		'other' => 'Inna',
	];

	public const MAX_TITLE = 190;
	public const MAX_ALT = 255;
	public const MAX_AUTHOR = 190;
	public const MAX_URL = 255;
	public const MAX_TAGS = 20;
	public const MAX_TAG = 60;

	/**
	 * @return array{data: array|null, errors: array<string, string>}
	 */
	public static function validate(array $input): array
	{
		$title = Input::line($input['title'] ?? null);
		$alt = Input::line($input['alt'] ?? null);
		$author = Input::line($input['author'] ?? null);
		$license = Input::line($input['license'] ?? null);
		$source = Input::line($input['source_url'] ?? null);
		$folder = (int) ($input['folder'] ?? 0);
		$tags = self::tags($input['tags'] ?? []);
		$errors = [];

		if ($title === null) {
			$errors['title'] = 'Podaj nazwę grafiki.';
		} elseif (mb_strlen($title) > self::MAX_TITLE) {
			$errors['title'] = sprintf('Nazwa może mieć do %d znaków.', self::MAX_TITLE);
		}

		if ($alt !== null && mb_strlen($alt) > self::MAX_ALT) {
			$errors['alt'] = sprintf('Tekst alternatywny może mieć do %d znaków.', self::MAX_ALT);
		}

		if ($author !== null && mb_strlen($author) > self::MAX_AUTHOR) {
			$errors['author'] = sprintf('Autor może mieć do %d znaków.', self::MAX_AUTHOR);
		}

		if ($license !== null && ! array_key_exists($license, self::LICENSES)) {
			$errors['license'] = 'Wybierz licencję z listy.';
		}

		if ($source !== null && (mb_strlen($source) > self::MAX_URL || ! self::isHttpUrl($source))) {
			$errors['source_url'] = 'Podaj pełny adres źródła (http lub https).';
		}

		if (count($tags) > self::MAX_TAGS) {
			$errors['tags'] = sprintf('Grafika może mieć do %d tagów.', self::MAX_TAGS);
		}

		foreach ($tags as $tag) {
			if (mb_strlen($tag) > self::MAX_TAG) {
				$errors['tags'] = sprintf('Tag może mieć do %d znaków.', self::MAX_TAG);
			}
		}

		$data = [
			'title' => $title,
			'alt' => $alt,
			'author' => $author,
			'license' => $license,
			'source_url' => $source,
			'folder' => $folder > 0 ? $folder : null,
			'tags' => $tags,
		];

		return ['data' => $errors ? null : $data, 'errors' => $errors];
	}

	/**
	 * Tagi z listy albo z tekstu rozdzielonego przecinkami, bez powtórzeń (wielkość liter bez znaczenia).
	 *
	 * @return list<string>
	 */
	private static function tags(mixed $value): array
	{
		$items = is_array($value) ? $value : explode(',', (string) $value);
		$tags = [];

		foreach ($items as $item) {
			$tag = is_scalar($item) ? Input::line($item) : null;

			if ($tag !== null) {
				$tags[mb_strtolower($tag)] ??= $tag;
			}
		}

		return array_values($tags);
	}

	private static function isHttpUrl(string $url): bool
	{
		return filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1;
	}
}
