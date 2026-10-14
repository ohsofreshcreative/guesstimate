<?php

namespace App\Guesstimate\Support;

/**
 * Buduje pozycje sidebaru dla zalogowanego użytkownika.
 *
 * Zależności od WordPressa i routera są wstrzykiwane jako callable, żeby logikę dało się testować bez WP.
 */
final class Navigation
{
	/**
	 * @param  callable(string): bool  $can  sprawdza capability bieżącego użytkownika
	 * @param  callable(string): string  $url  zamienia nazwę trasy na URL
	 * @return list<array{key: string, label: string, url: string, icon: string, active: bool}>
	 */
	public static function build(callable $can, callable $url, ?string $currentRoute): array
	{
		$items = [];

		foreach (Modules::all() as $key => $module) {
			if (! $can($module['capability'])) {
				continue;
			}

			$items[] = [
				'key' => $key,
				'label' => $module['label'],
				'url' => $url($module['route']),
				'icon' => $module['icon'],
				'active' => self::isActive($module['route'], $currentRoute),
			];
		}

		return $items;
	}

	/**
	 * Moduł jest aktywny również na podstronach (np. gst.offers.show dla gst.offers.index).
	 */
	private static function isActive(string $route, ?string $currentRoute): bool
	{
		if ($currentRoute === null) {
			return false;
		}

		if ($route === $currentRoute) {
			return true;
		}

		$prefix = preg_replace('/\.index$/', '.', $route);

		return $prefix !== $route && str_starts_with($currentRoute, $prefix);
	}
}
