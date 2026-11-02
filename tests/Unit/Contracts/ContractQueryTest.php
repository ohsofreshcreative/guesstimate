<?php

namespace Tests\Unit\Contracts;

use App\Guesstimate\Contracts\ContractQuery;
use PHPUnit\Framework\TestCase;

class ContractQueryTest extends TestCase
{
	public function test_defaults(): void
	{
		$this->assertSame(['q' => '', 'status' => '', 'author' => 0, 'page' => 1], ContractQuery::normalize([]));
	}

	public function test_unknown_status_and_bad_numbers_are_ignored(): void
	{
		$filters = ContractQuery::normalize(['status' => 'signed', 'author' => '-3', 'page' => 'x']);

		$this->assertSame('', $filters['status']);
		$this->assertSame(0, $filters['author']);
		$this->assertSame(1, $filters['page']);
	}

	public function test_valid_values_are_kept_and_search_is_trimmed_to_length(): void
	{
		$filters = ContractQuery::normalize(['q' => '  ' . str_repeat('ż', 150), 'status' => 'approved', 'author' => '7', 'page' => '3']);

		$this->assertSame(100, mb_strlen($filters['q']));
		$this->assertSame('approved', $filters['status']);
		$this->assertSame(7, $filters['author']);
		$this->assertSame(3, $filters['page']);
	}
}
