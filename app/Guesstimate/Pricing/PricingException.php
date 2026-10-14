<?php

namespace App\Guesstimate\Pricing;

use RuntimeException;

/**
 * Konfiguracja, której nie da się policzyć bez zgadywania (np. procenty ≠ 100%).
 * Komunikat jest przeznaczony dla użytkownika.
 */
class PricingException extends RuntimeException
{
}
