<?php

namespace App\Guesstimate\Document;

/**
 * Kadr zdjęcia strony. Niedestrukcyjny: plik pozostaje bez zmian, a kadr należy do strony oferty, więc ta sama
 * grafika może mieć w każdej ofercie inny kadr. Punkt skupienia w % i powiększenie, niezależne od proporcji ramki.
 */
final class ImageCrop
{
	public const MAX_ZOOM = 4;

	/**
	 * @return array{x: float, y: float, zoom: float}|null null = kadr domyślny (środek, bez powiększenia)
	 */
	public static function normalize(mixed $crop): ?array
	{
		if (! is_array($crop)) {
			return null;
		}

		$x = self::number($crop['x'] ?? 50, 0, 100);
		$y = self::number($crop['y'] ?? 50, 0, 100);
		$zoom = self::number($crop['zoom'] ?? 1, 1, self::MAX_ZOOM);

		return $x === 50.0 && $y === 50.0 && $zoom === 1.0 ? null : ['x' => $x, 'y' => $y, 'zoom' => $zoom];
	}

	/** Błąd zakresu z formularza; normalize() przycina wartości przy odczycie. */
	public static function rangeError(mixed $crop): ?string
	{
		if ($crop === null) {
			return null;
		}

		$valid = is_array($crop)
			&& self::within($crop['x'] ?? 50, 0, 100)
			&& self::within($crop['y'] ?? 50, 0, 100)
			&& self::within($crop['zoom'] ?? 1, 1, self::MAX_ZOOM);

		return $valid ? null : sprintf('kadr poza zakresem (położenie 0–100, powiększenie 1–%d).', self::MAX_ZOOM);
	}

	private static function within(mixed $value, float $min, float $max): bool
	{
		return is_numeric($value) && $value >= $min && $value <= $max;
	}

	public static function style(?array $crop): string
	{
		if (! $crop) {
			return '';
		}

		$position = sprintf('%s%% %s%%', self::css($crop['x']), self::css($crop['y']));

		return sprintf('object-position:%1$s;transform-origin:%1$s;transform:scale(%2$s)', $position, self::css($crop['zoom']));
	}

	private static function number(mixed $value, float $min, float $max): float
	{
		return is_numeric($value) ? round(max($min, min($max, (float) $value)), 2) : ($min === 0.0 ? 50.0 : 1.0);
	}

	/** Liczba do CSS zawsze z kropką, niezależnie od ustawień locale. */
	public static function css(float|int $value): string
	{
		return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
	}
}
