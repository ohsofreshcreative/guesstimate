<?php

namespace Tests\Unit\Contracts;

use App\Guesstimate\Contracts\ContractSnapshot;
use App\Guesstimate\Contracts\ContractValues;
use App\Guesstimate\Contracts\PaymentSchedule;
use PHPUnit\Framework\TestCase;

class PaymentScheduleTest extends TestCase
{
	private function stage(int $net, ?string $percent = null, string $name = 'Etap'): array
	{
		return ['name' => $name, 'milestone' => null, 'net' => $net, 'percent' => $percent, 'due' => null];
	}

	private function payments(int $agreed, array $stages, ?array $deposit = null): array
	{
		return ['agreed_net' => $agreed, 'vat_rate' => '23.00', 'stages' => $stages, 'deposit' => $deposit];
	}

	public function test_vat_and_gross_round_half_up_to_a_grosz(): void
	{
		$summary = PaymentSchedule::summary($this->payments(50, [$this->stage(50)]));

		$this->assertSame(['net' => 50, 'vat' => 12, 'gross' => 62], array_intersect_key($summary['stages'][0], array_flip(['net', 'vat', 'gross'])));
		$this->assertSame(12, PaymentSchedule::summary($this->payments(50, []))['agreed']['vat']);
		$this->assertSame(['net' => 1, 'vat' => 0, 'gross' => 1], PaymentSchedule::summary($this->payments(1, []))['agreed']);
	}

	public function test_separate_deposit_covers_part_of_amount(): void
	{
		$summary = PaymentSchedule::summary($this->payments(1000000, [$this->stage(800000)], ['net' => 200000, 'mode' => 'separate', 'stage' => null, 'due' => null]));

		$this->assertSame([], $summary['blockers']);
		$this->assertSame(1000000, $summary['paid']['net']);
		$this->assertSame(1230000, $summary['paid']['gross']);
		$this->assertSame(984000, $summary['stages'][0]['payable']);
	}

	public function test_credited_deposit_is_not_counted_twice(): void
	{
		$summary = PaymentSchedule::summary($this->payments(1000000, [$this->stage(600000), $this->stage(400000)], ['net' => 100000, 'mode' => 'credited', 'stage' => 1, 'due' => null]));

		$this->assertSame([], $summary['blockers']);
		$this->assertSame(1000000, $summary['paid']['net']);
		$this->assertSame(738000 - 123000, $summary['stages'][0]['payable']);
		$this->assertSame(123000, $summary['stages'][0]['credit']);
		$this->assertSame($summary['agreed']['gross'], $summary['deposit']['gross'] + array_sum(array_column($summary['stages'], 'payable')));
	}

	public function test_separate_deposit_on_top_of_full_stages_is_reported(): void
	{
		$summary = PaymentSchedule::summary($this->payments(1000000, [$this->stage(1000000)], ['net' => 100000, 'mode' => 'separate', 'stage' => null, 'due' => null]));

		$this->assertCount(1, $summary['blockers']);
		$this->assertStringContainsString('różnica', $summary['blockers'][0]);
	}

	public function test_deposit_mode_is_never_assumed(): void
	{
		$payments = PaymentSchedule::parse(['vat_rate' => '23', 'agreed_net' => '100', 'stages' => [['name' => 'A', 'net' => '100']], 'deposit_net' => '10'])['payments'];

		$this->assertNull($payments['deposit']['mode']);
		$this->assertStringContainsString('Wybierz', PaymentSchedule::summary($payments)['blockers'][0]);
	}

	public function test_deposit_higher_than_stage_or_missing_stage_blocks(): void
	{
		$higher = PaymentSchedule::summary($this->payments(10000, [$this->stage(10000)], ['net' => 20000, 'mode' => 'credited', 'stage' => 1, 'due' => null]));
		$gone = PaymentSchedule::summary($this->payments(10000, [$this->stage(10000)], ['net' => 100, 'mode' => 'credited', 'stage' => 5, 'due' => null]));

		$this->assertStringContainsString('wyższy', $higher['blockers'][0]);
		$this->assertStringContainsString('którego nie ma', $gone['blockers'][0]);
	}

	public function test_mismatch_between_stages_and_amount_blocks(): void
	{
		$summary = PaymentSchedule::summary($this->payments(1000000, [$this->stage(999999)]));

		$this->assertCount(1, $summary['blockers']);
		$this->assertStringContainsString('różnica', $summary['blockers'][0]);
		$this->assertStringContainsString('Dodaj', PaymentSchedule::summary($this->payments(100, []))['blockers'][0]);
		$this->assertStringContainsString('uzgodnioną', PaymentSchedule::summary(['vat_rate' => '23', 'stages' => [$this->stage(1)]])['blockers'][0]);
	}

	public function test_rounding_difference_warns_and_changes_nothing(): void
	{
		$summary = PaymentSchedule::summary($this->payments(300, [$this->stage(150), $this->stage(150)]));

		$this->assertSame(369, $summary['agreed']['gross']);
		$this->assertSame(370, $summary['paid']['gross']);
		$this->assertSame([], $summary['blockers']);
		$this->assertStringContainsString('VAT liczony', $summary['warnings'][0]);
	}

	public function test_declared_percent_stays_and_warns_about_conflict(): void
	{
		$summary = PaymentSchedule::summary($this->payments(1000000, [$this->stage(500000, '30.00'), $this->stage(500000, '30.00')]));

		$this->assertSame('30.00', $summary['stages'][0]['percent']);
		$this->assertSame('50.00', $summary['stages'][0]['share']);
		$this->assertSame(500000, $summary['stages'][0]['net']);
		$this->assertSame([], $summary['blockers']);
		$this->assertCount(3, $summary['warnings']);
		$this->assertStringContainsString('sumują się', $summary['warnings'][2]);
	}

	public function test_no_percent_is_imposed_and_thirds_do_not_warn(): void
	{
		$plain = PaymentSchedule::summary($this->payments(900, [$this->stage(300), $this->stage(300), $this->stage(300)]));
		$thirds = PaymentSchedule::summary($this->payments(900, [$this->stage(300, '33.33'), $this->stage(300, '33.33'), $this->stage(300, '33.33')]));

		$this->assertNull($plain['stages'][0]['percent']);
		$this->assertSame([], $plain['warnings']);
		$this->assertSame([], $thirds['warnings']);
	}

	public function test_estimate_only_warns_and_never_fills_the_amount(): void
	{
		$estimate = ['min' => 100000, 'max' => 200000];

		$this->assertNull(PaymentSchedule::summary(['vat_rate' => '23'], $estimate)['agreed']);
		$this->assertSame([], PaymentSchedule::summary($this->payments(150000, [$this->stage(150000)]), $estimate)['warnings']);
		$this->assertStringContainsString('poza ceną', PaymentSchedule::summary($this->payments(250000, [$this->stage(250000)]), $estimate)['warnings'][0]);
	}

	public function test_parse_reads_polish_numbers_and_skips_blank_rows(): void
	{
		$parsed = PaymentSchedule::parse(['agreed_net' => '1 500,50', 'vat_rate' => '23', 'stages' => [['name' => ' A ', 'net' => '1 500,50', 'percent' => '', 'milestone' => '', 'due' => 'do 7 dni'], ['name' => '', 'net' => ' ']]]);

		$this->assertSame([], $parsed['errors']);
		$this->assertSame(150050, $parsed['payments']['agreed_net']);
		$this->assertCount(1, $parsed['payments']['stages']);
		$this->assertSame(['A', 150050, null, 'do 7 dni'], [$parsed['payments']['stages'][0]['name'], $parsed['payments']['stages'][0]['net'], $parsed['payments']['stages'][0]['percent'], $parsed['payments']['stages'][0]['due']]);
	}

	public function test_parse_rejects_bad_values(): void
	{
		$errors = PaymentSchedule::parse(['agreed_net' => '-5', 'vat_rate' => '123', 'deposit_net' => 'x', 'deposit_mode' => 'credited', 'deposit_stage' => '9', 'stages' => [['name' => '', 'net' => '1,234', 'percent' => '101']]])['errors'];

		$this->assertSame(['agreed_net', 'deposit_net', 'deposit_stage', 'stages.0.name', 'stages.0.net', 'stages.0.percent', 'vat_rate'], collect($errors)->keys()->sort()->values()->all());
		$this->assertArrayHasKey('stages', PaymentSchedule::parse(['stages' => array_fill(0, 21, ['name' => 'a'])])['errors']);
	}

	public function test_tokens_follow_schedule_and_ignore_manual_text(): void
	{
		$snapshot = ContractSnapshot::compose([], ['currency' => 'PLN'], [], 'x', [], ['contract.amount' => '1 zł'], $this->payments(100000, [$this->stage(100000)]));
		$values = ContractValues::resolved($snapshot);

		$this->assertSame('1 000 zł', str_replace("\u{00A0}", ' ', $values['contract.amount']));
		$this->assertSame('1 230 zł', str_replace("\u{00A0}", ' ', $values['contract.amount_gross']));
		$this->assertSame([], ContractValues::clean(['contract.amount' => '5 zł'], array_replace($snapshot, ['values' => []])));
	}

	public function test_table_replaces_paragraph_and_escapes_text(): void
	{
		$token = '<span class="ct-var" data-var="payments.table" contenteditable="false">T</span>';
		$stage = $this->stage(100, null, '<img src=x onerror=a> <span data-var="client.nip">n</span>');
		$snapshot = ContractSnapshot::compose([], ['currency' => 'PLN'], [], 'x', [], [], $this->payments(100, [$stage]));
		$html = ContractValues::render("<p>Przed</p><p>{$token}&nbsp;</p><p>Po</p>", $snapshot);

		$this->assertStringStartsWith('<p>Przed</p><table>', $html);
		$this->assertStringEndsWith('</table><p>Po</p>', $html);
		$this->assertStringNotContainsString('<img', $html);
		$this->assertStringNotContainsString('<span', $html);
		$this->assertStringContainsString('&lt;img', $html);
	}

	public function test_table_token_inside_sentence_stays_valid_text(): void
	{
		$token = '<span class="ct-var" data-var="payments.table" contenteditable="false">T</span>';
		$snapshot = ContractSnapshot::compose([], ['currency' => 'PLN'], [], 'x', [], [], $this->payments(100, [$this->stage(100, null, 'A')]));

		$this->assertStringNotContainsString('<table', ContractValues::render("<p>Zob. {$token}</p>", $snapshot));
	}
}
