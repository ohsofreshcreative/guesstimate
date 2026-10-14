<?php

namespace Tests\Unit\Clients;

use App\Guesstimate\Clients\Nip;
use PHPUnit\Framework\TestCase;

class NipTest extends TestCase
{
	public function test_valid_nip_with_separators_and_prefix(): void
	{
		$this->assertTrue(Nip::isValid('526-104-08-28'));
		$this->assertTrue(Nip::isValid('PL 5261040828'));
		$this->assertSame('5261040828', Nip::normalize('526 104 08 28'));
	}

	public function test_invalid_checksum_and_length(): void
	{
		$this->assertFalse(Nip::isValid('5261040827'));
		$this->assertFalse(Nip::isValid('123'));
		$this->assertFalse(Nip::isValid(''));
	}
}
