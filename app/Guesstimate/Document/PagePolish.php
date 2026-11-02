<?php

namespace App\Guesstimate\Document;

/**
 * Opcjonalne dopracowanie wyglądu strony: `pages.<klucz>.polish`. Wszystko opt-in: brak klucza oznacza dotychczasowy
 * wygląd, a domyślne wartości nie są zapisywane, więc starsze dokumenty i snapshoty renderują się bez zmian.
 */
final class PagePolish
{
	/** Położenie nagłówka okładki: dodatnie = wyżej (do logo), ujemne = niżej, 0 = środek strony. */
	public const MAX_LIFT = 100;

	/** Strony z bocznym napisem. */
	public const EDGE_LABELS = ['pricing', 'references'];

	/** Strony bez zdjęcia: tu dopasowanie grafiki nie ma do czego się odnosić. */
	private const NO_IMAGES = ['pricing', 'extras'];

	/** Wartości spoza domyślnych; domyślne to mały tytuł i zdjęcie 16:9. */
	public const TITLE_SIZES = ['large' => 'osf-t-xl', 'xlarge' => 'osf-t-2xl'];

	/** Bez proporcji pionowych: kafelek przestaje się mieścić na A4 i cała treść spada na stronę „cd.”. */
	public const RATIOS = ['standard' => 'aspect-4/3', 'square' => 'aspect-square'];

	/**
	 * @param  list<int>|null  $referenceIds  wybrane realizacje; kadry pozostałych są odrzucane
	 * @return array<string, mixed>|null null = brak zmian względem szablonu
	 */
	public static function normalize(mixed $polish, string $key, ?array $referenceIds = null): ?array
	{
		if (! is_array($polish)) {
			return null;
		}

		$out = [];

		if ($key === 'cover' && is_numeric($polish['heading_lift'] ?? null)) {
			$lift = max(-self::MAX_LIFT, min(self::MAX_LIFT, (int) $polish['heading_lift']));

			if ($lift !== 0) {
				$out['heading_lift'] = $lift;
			}
		}

		if (in_array($key, self::EDGE_LABELS, true) && ! empty($polish['hide_edge_label'])) {
			$out['hide_edge_label'] = true;
		}

		if (! in_array($key, self::NO_IMAGES, true) && ($polish['image_fit'] ?? null) === 'contain') {
			$out['image_fit'] = 'contain';
		}

		if ($key === 'references') {
			$out += self::references($polish, $referenceIds);
		}

		return $out ?: null;
	}

	/**
	 * Błąd zakresu z formularza. Nieznane opcje są pomijane przez normalize(), a tu liczą się tylko wartości liczbowe.
	 */
	public static function rangeError(mixed $polish): ?string
	{
		if (! is_array($polish)) {
			return null;
		}

		if (isset($polish['heading_lift']) && (! is_numeric($polish['heading_lift']) || abs((float) $polish['heading_lift']) > self::MAX_LIFT)) {
			return sprintf('położenie nagłówka poza zakresem od -%1$d do %1$d.', self::MAX_LIFT);
		}

		foreach ((array) ($polish['image_crops'] ?? []) as $crop) {
			if ($error = ImageCrop::rangeError($crop)) {
				return $error;
			}
		}

		return null;
	}

	private static function references(array $polish, ?array $referenceIds): array
	{
		$out = [];

		if (isset(self::TITLE_SIZES[$polish['title_size'] ?? ''])) {
			$out['title_size'] = $polish['title_size'];
		}

		if (isset(self::RATIOS[$polish['image_ratio'] ?? ''])) {
			$out['image_ratio'] = $polish['image_ratio'];
		}

		$crops = [];

		foreach ((array) ($polish['image_crops'] ?? []) as $id => $crop) {
			$crop = ImageCrop::normalize($crop);

			if ($crop && (int) $id > 0 && ($referenceIds === null || in_array((int) $id, $referenceIds, true))) {
				$crops[(int) $id] = $crop;
			}
		}

		return $crops ? $out + ['image_crops' => $crops] : $out;
	}

	public static function titleClass(array $polish): string
	{
		return self::TITLE_SIZES[$polish['title_size'] ?? ''] ?? 'osf-t-sm';
	}

	public static function ratioClass(array $polish): string
	{
		return self::RATIOS[$polish['image_ratio'] ?? ''] ?? 'aspect-video';
	}
}
