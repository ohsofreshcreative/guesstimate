<?php

namespace Tests\Unit\Support;

use App\Guesstimate\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
	private const NBSP = "\u{00A0}";

	public function test_money_uses_polish_separators_and_hides_zero_grosze(): void
	{
		$this->assertSame('12' . self::NBSP . '800' . self::NBSP . 'zł', Format::money(1280000));
		$this->assertSame('1' . self::NBSP . '994,13' . self::NBSP . 'zł', Format::money(199413));
		$this->assertSame('7' . self::NBSP . '200–12' . self::NBSP . '800' . self::NBSP . 'zł', Format::moneyRange(720000, 1280000));
		$this->assertSame('—', Format::moneyRange(null, 100));
	}

	public function test_hours_drop_trailing_zeros(): void
	{
		$this->assertSame('48' . self::NBSP . 'h', Format::hours('48.00'));
		$this->assertSame('12,5' . self::NBSP . 'h', Format::hours('12.50'));
		$this->assertSame('48–64' . self::NBSP . 'h', Format::hoursRange(['min' => '48.00', 'max' => '64.00']));
		$this->assertSame('16' . self::NBSP . 'h', Format::hoursRange(['min' => '16.00', 'max' => '16']));
	}

	public function test_form_values(): void
	{
		$this->assertSame('150', Format::moneyInput(15000));
		$this->assertSame('180,50', Format::moneyInput(18050));
		$this->assertSame('4,5', Format::hoursInput('4.50'));
	}
}
