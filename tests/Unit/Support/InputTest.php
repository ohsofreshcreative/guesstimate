<?php

namespace Tests\Unit\Support;

use App\Guesstimate\Support\Input;
use PHPUnit\Framework\TestCase;

class InputTest extends TestCase
{
	public function test_money_accepts_polish_format_and_rejects_ambiguous_values(): void
	{
		$this->assertSame(15050, Input::money('150,50'));
		$this->assertSame(1234500, Input::money('12 345'));
		$this->assertSame(10, Input::money('0,1'));
		$this->assertNull(Input::money('150,555'));
		$this->assertNull(Input::money('-5'));
		$this->assertNull(Input::money('abc'));
		$this->assertNull(Input::money(''));
	}

	public function test_hours_are_normalized_to_two_decimals(): void
	{
		$this->assertSame('4.50', Input::hours('4,5'));
		$this->assertSame('0.25', Input::hours('0.25'));
		$this->assertSame('12.00', Input::hours('12'));
		$this->assertNull(Input::hours('1,234'));
		$this->assertNull(Input::hours('-1'));
	}
}
