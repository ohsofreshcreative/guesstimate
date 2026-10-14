<?php

namespace App\Guesstimate\Offers;

enum OfferStatus: string
{
	case Draft = 'draft';
	case Prepared = 'prepared';
	case Sent = 'sent';
	case Accepted = 'accepted';
	case Rejected = 'rejected';
	case Cancelled = 'cancelled';

	public function label(): string
	{
		return match ($this) {
			self::Draft => 'Robocza',
			self::Prepared => 'Przygotowana',
			self::Sent => 'Wysłana',
			self::Accepted => 'Zaakceptowana',
			self::Rejected => 'Odrzucona',
			self::Cancelled => 'Anulowana',
		};
	}

	/**
	 * Nazwa akcji przejścia do tego statusu (przyciski, historia).
	 */
	public function actionLabel(): string
	{
		return match ($this) {
			self::Draft => 'Przywróć do roboczej',
			self::Prepared => 'Oznacz jako przygotowaną',
			self::Sent => 'Oznacz jako wysłaną',
			self::Accepted => 'Oznacz jako zaakceptowaną',
			self::Rejected => 'Oznacz jako odrzuconą',
			self::Cancelled => 'Anuluj ofertę',
		};
	}

	/**
	 * @return list<self>
	 */
	public function transitions(): array
	{
		return match ($this) {
			self::Draft => [self::Prepared, self::Cancelled],
			self::Prepared => [self::Draft, self::Sent, self::Cancelled],
			self::Sent => [self::Accepted, self::Rejected, self::Cancelled],
			self::Accepted => [self::Cancelled],
			self::Rejected => [self::Cancelled],
			self::Cancelled => [self::Draft],
		};
	}

	public function canTransitionTo(self $status): bool
	{
		return in_array($status, $this->transitions(), true);
	}

	/**
	 * Od wysłania wersja oferty jest zamrożona; zmiany tworzą nową wersję.
	 */
	public function locksVersion(): bool
	{
		return in_array($this, [self::Sent, self::Accepted, self::Rejected], true);
	}

	/**
	 * Anulowanej oferty nie edytujemy — najpierw trzeba ją przywrócić.
	 */
	public function isEditable(): bool
	{
		return $this !== self::Cancelled;
	}

	/**
	 * @return array<string, string>
	 */
	public static function options(): array
	{
		$options = [];

		foreach (self::cases() as $case) {
			$options[$case->value] = $case->label();
		}

		return $options;
	}
}
