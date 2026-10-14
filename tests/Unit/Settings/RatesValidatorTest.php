<?php

namespace Tests\Unit\Settings;

use App\Guesstimate\Settings\RatesValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RatesValidatorTest extends TestCase
{
	public function test_accepts_default_rates_and_polish_decimal_comma(): void
	{
		$this->assertSame(['low' => 15000, 'mid' => 18050, 'high' => 20000], RatesValidator::validate(['low' => '150', 'mid' => '180,50', 'high' => '200'])['rates']);
	}

	public function test_accepts_equal_rates_and_technical_limit(): void
	{
		$this->assertNotNull(RatesValidator::validate(['low' => '100000', 'mid' => '100000', 'high' => '100000'])['rates']);
	}

	#[DataProvider('invalid')]
	public function test_rejects_invalid_values(array $input, string $field): void
	{
		$result = RatesValidator::validate($input);

		$this->assertNull($result['rates']);
		$this->assertArrayHasKey($field, $result['errors']);
	}

	public static function invalid(): array
	{
		return [
			'zero' => [['low' => '0', 'mid' => '180', 'high' => '200'], 'low'],
			'ujemna' => [['low' => '-10', 'mid' => '180', 'high' => '200'], 'low'],
			'trzy miejsca po przecinku' => [['low' => '150,555', 'mid' => '180', 'high' => '200'], 'low'],
			'tekst' => [['low' => '150', 'mid' => 'dużo', 'high' => '200'], 'mid'],
			'pusta' => [['low' => '150', 'mid' => '180', 'high' => ''], 'high'],
			'ponad limit' => [['low' => '150', 'mid' => '180', 'high' => '100000,01'], 'high'],
			'mid < low' => [['low' => '190', 'mid' => '180', 'high' => '200'], 'mid'],
			'high < mid' => [['low' => '150', 'mid' => '210', 'high' => '200'], 'high'],
		];
	}
}
