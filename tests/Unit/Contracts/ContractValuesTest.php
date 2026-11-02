<?php

namespace Tests\Unit\Contracts;

use App\Guesstimate\Contracts\ContractSnapshot;
use App\Guesstimate\Contracts\ContractValues;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ContractValuesTest extends TestCase
{
	private function snapshot(array $values = []): array
	{
		return ContractSnapshot::compose(
			['company_name' => 'ACME <b>"Sp"</b>', 'nip' => null, 'email' => ''],
			['number' => 'OSF-1', 'pages' => ['Start', '', 'O nas'], 'features' => [], 'currency' => 'PLN'],
			['amounts' => ['variant' => ['min' => 100000, 'max' => 200000], 'range' => ['min' => null, 'max' => null]]],
			'2026-11-02T10:00:00+00:00',
			['company_name' => 'Wykonawca'],
			$values,
		);
	}

	private function token(string $key): string
	{
		return '<span class="ct-var" data-var="' . $key . '" contenteditable="false">Etykieta</span>';
	}

	public function test_values_are_escaped_as_plain_text(): void
	{
		$html = ContractValues::render('<p>' . $this->token('client.company_name') . '</p>', $this->snapshot());

		$this->assertSame('<p>ACME &lt;b&gt;&quot;Sp&quot;&lt;/b&gt;</p>', $html);
	}

	public function test_inserted_value_is_not_processed_again(): void
	{
		$attack = '<span data-var="client.nip">x</span><script>alert(1)</script>';
		$html = ContractValues::render('<p>' . $this->token('terms.place') . '</p>', $this->snapshot(['terms.place' => $attack]));

		$this->assertStringNotContainsString('<script', $html);
		$this->assertStringNotContainsString('<span', $html);
	}

	public function test_empty_values_never_print_as_text(): void
	{
		$body = '<p>' . implode('', array_map(fn ($key) => $this->token($key), ['client.nip', 'client.email', 'offer.features', 'offer.amount_range', 'contract.amount', 'nieznane.pole'])) . '</p>';
		$html = ContractValues::render($body, $this->snapshot());

		$this->assertSame(6, substr_count($html, 'ct-missing'));
		$this->assertDoesNotMatchRegularExpression('/undefined|null|NaN|Array/', $html);
		$this->assertStringContainsString('[brak: Kwota umowy netto]', $html);
	}

	public function test_text_outside_tokens_stays_byte_for_byte(): void
	{
		$before = "<p class=\"ct-par\">Zażółć  „x”&nbsp;\r\n";
		$after = " 😀</p><table><tbody><tr><td>a</td></tr></tbody></table>";
		$html = ContractValues::render($before . $this->token('offer.number') . $after, $this->snapshot());

		$this->assertSame($before . 'OSF-1' . $after, $html);
	}

	public function test_page_tokens_stay_for_pagination(): void
	{
		$html = ContractValues::render($this->token('page.number'), $this->snapshot());

		$this->assertSame('<span data-var="page.number">Numer strony</span>', $html);
		$this->assertSame([], ContractValues::missing($this->token('page.number'), $this->snapshot()));
	}

	public function test_lists_join_and_skip_blanks(): void
	{
		$this->assertSame('Start, O nas', ContractValues::automatic($this->snapshot())['offer.pages']);
	}

	public function test_manual_value_wins_and_clearing_restores_source(): void
	{
		$snapshot = $this->snapshot();
		$values = ContractValues::clean(['offer.number' => 'INNY-1', 'terms.place' => 'Gdańsk'], $snapshot);

		$this->assertSame(['offer.number' => 'INNY-1', 'terms.place' => 'Gdańsk'], $values);

		$edited = array_replace($snapshot, ['values' => $values]);
		$this->assertSame('INNY-1', ContractValues::resolved($edited)['offer.number']);
		$this->assertSame('OSF-1', ContractValues::automatic($edited)['offer.number']);
		$this->assertSame(['terms.place' => 'Gdańsk'], ContractValues::clean(['offer.number' => ' '], $edited));
		$this->assertSame(['terms.place' => 'Gdańsk'], ContractValues::clean(['offer.number' => null], $edited));
		$this->assertSame(['terms.place' => 'Gdańsk'], ContractValues::clean(['offer.number' => 'OSF-1'], $edited));
	}

	public function test_clean_ignores_foreign_keys_and_limits_length(): void
	{
		$this->assertSame([], ContractValues::clean(['page.number' => '7', 'obce.pole' => 'x', 'terms.place' => ['a']], $this->snapshot()));
		$this->assertSame(['terms.place' => 'a b'], ContractValues::clean(['terms.place' => "  a\r\nb "], $this->snapshot()));

		$this->expectException(InvalidArgumentException::class);
		ContractValues::clean(['terms.place' => str_repeat('a', ContractValues::MAX_LENGTH + 1)], $this->snapshot());
	}

	public function test_missing_lists_only_empty_used_fields(): void
	{
		$body = $this->token('offer.number') . $this->token('client.nip') . $this->token('terms.place');

		$this->assertSame(['NIP', 'Miejsce zawarcia umowy'], ContractValues::missing($body, $this->snapshot()));
		$this->assertSame(['NIP'], ContractValues::missing($body, $this->snapshot(['terms.place' => 'Gdańsk'])));
	}

	public function test_diff_covers_used_source_data_and_marks_manual_values(): void
	{
		$old = $this->snapshot(['offer.number' => 'RĘCZNY']);
		$new = $this->snapshot();
		$new['offer']['number'] = 'OSF-2';
		$new['client']['company_name'] = 'Inna';

		$rows = ContractValues::diff($this->token('offer.number'), $old, $new);

		$this->assertCount(1, $rows);
		$this->assertSame(['offer.number', 'OSF-1', 'OSF-2', true], [$rows[0]['key'], $rows[0]['old'], $rows[0]['new'], $rows[0]['overridden']]);
	}

	public function test_old_snapshot_schema_is_readable(): void
	{
		$old = ['schema' => 1, 'client' => ['company_name' => 'A'], 'offer' => ['number' => 'N'], 'pricing' => []];

		$this->assertNull(ContractValues::automatic($old)['offer.amount_variant']);
		$this->assertSame('A', ContractValues::render($this->token('client.company_name'), $old));
	}
}
