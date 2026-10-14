<?php

namespace Tests\Unit\Offers;

use App\Guesstimate\Offers\OfferNumberGenerator;
use App\Guesstimate\Offers\OfferStatus;
use PHPUnit\Framework\TestCase;

class OfferStatusTest extends TestCase
{
	public function test_offer_goes_through_prepared_before_it_is_sent(): void
	{
		$this->assertFalse(OfferStatus::Draft->canTransitionTo(OfferStatus::Sent));
		$this->assertTrue(OfferStatus::Draft->canTransitionTo(OfferStatus::Prepared));
		$this->assertTrue(OfferStatus::Prepared->canTransitionTo(OfferStatus::Sent));
	}

	public function test_client_decision_is_possible_only_after_sending(): void
	{
		$this->assertTrue(OfferStatus::Sent->canTransitionTo(OfferStatus::Accepted));
		$this->assertTrue(OfferStatus::Sent->canTransitionTo(OfferStatus::Rejected));
		$this->assertFalse(OfferStatus::Prepared->canTransitionTo(OfferStatus::Accepted));
	}

	public function test_sending_and_decisions_lock_the_version(): void
	{
		$locking = array_filter(OfferStatus::cases(), fn ($s) => $s->locksVersion());

		$this->assertEqualsCanonicalizing([OfferStatus::Sent, OfferStatus::Accepted, OfferStatus::Rejected], array_values($locking));
	}

	public function test_cancelled_offer_is_not_editable_but_can_be_restored(): void
	{
		$this->assertFalse(OfferStatus::Cancelled->isEditable());
		$this->assertSame([OfferStatus::Draft], OfferStatus::Cancelled->transitions());
	}

	public function test_every_status_can_be_cancelled_except_cancelled(): void
	{
		foreach (OfferStatus::cases() as $status) {
			if ($status !== OfferStatus::Cancelled) {
				$this->assertTrue($status->canTransitionTo(OfferStatus::Cancelled), $status->value);
			}
		}
	}

	public function test_number_format(): void
	{
		$this->assertSame('OSF/2026/0001', OfferNumberGenerator::format('OSF', 2026, 1, 4));
		$this->assertSame('OSF/2026/12345', OfferNumberGenerator::format('OSF', 2026, 12345, 4));
	}
}
