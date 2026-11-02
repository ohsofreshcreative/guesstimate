<?php

namespace Tests\Unit\Contracts;

use App\Guesstimate\Contracts\ContractHtml;
use App\Guesstimate\Contracts\ContractValues;
use App\Guesstimate\Contracts\TemplateDocument;
use App\Guesstimate\Contracts\TemplateForm;
use App\Guesstimate\Contracts\TemplateVariables;
use PHPUnit\Framework\TestCase;

class ContractHtmlTest extends TestCase
{
	public function test_keeps_document_structure(): void
	{
		$html = '<h1 class="ct-center">Tytuł</h1><h2>A</h2><h3>B</h3><p class="ct-par">Przedmiot</p><p class="ct-small ct-justify">Tekst</p>'
			. '<ul><li>a</li></ul><ol start="3"><li>b</li></ol><table><tbody><tr><td colspan="2">c</td></tr></tbody></table><hr class="ct-break">';

		$this->assertSame($html, ContractHtml::clean($html));
	}

	public function test_is_idempotent_so_saving_twice_does_not_change_bytes(): void
	{
		$once = ContractHtml::clean("<div>Zażółć „gęślą”\u{00A0}jaźń 😀</div><b>x</b> <i>y</i><h5>z</h5>");

		$this->assertSame($once, ContractHtml::clean($once));
	}

	public function test_preserves_text_exactly(): void
	{
		$text = "Zażółć „gęślą” jaźń 😀 § 5 ust. 1 – 100% & 2 < 3 \\ 'x' \"y\"  dwie spacje";
		$clean = ContractHtml::clean('<p>' . htmlspecialchars($text, ENT_NOQUOTES) . '</p>');

		$this->assertSame($text, ContractHtml::text($clean));
	}

	public function test_removes_active_content_but_never_unknown_text(): void
	{
		$clean = ContractHtml::clean('<script>alert(1)</script><style>p{}</style><iframe src="x"></iframe><p onclick="x()" style="color:red" id="a">Klauzula <font color="red">ważna</font> <a href="javascript:x()">link</a></p><!-- k --><svg onload="x()"><text>s</text></svg>');

		$this->assertSame('<p>Klauzula ważna link</p>', $clean);
	}

	public function test_blocks_attribute_and_class_injection(): void
	{
		$clean = ContractHtml::clean('<p class="ct-par evil" data-x="1">a</p><td colspan="x">b</td><ol start="-1"><li>c</li></ol><hr class="evil" onclick="x()">');

		$this->assertStringContainsString('<p class="ct-par">a</p>', $clean);
		$this->assertStringNotContainsString('evil', $clean);
		$this->assertStringNotContainsString('onclick', $clean);
		$this->assertStringNotContainsString('start', $clean);
		$this->assertStringContainsString('<hr class="ct-break">', $clean);
	}

	public function test_word_tags_become_the_supported_ones(): void
	{
		$this->assertSame('<p><strong>a</strong><em>b</em></p><p>c</p><h3>d</h3>', ContractHtml::clean('<p><b>a</b><i>b</i></p><div>c</div><h4>d</h4>'));
	}

	public function test_loose_text_lands_in_a_paragraph(): void
	{
		$this->assertSame('<p>a <strong>b</strong></p><h2>c</h2><p>d</p>', ContractHtml::clean('a <strong>b</strong><h2>c</h2>d'));
	}

	public function test_variables_survive_with_fixed_attributes_and_plain_spans_do_not(): void
	{
		$clean = ContractHtml::clean('<p><span data-var="client.nip" class="evil" onclick="x()">NIP</span> <span style="x">zwykły</span> <span data-var="zły klucz">z</span></p>');

		$this->assertSame('<p><span class="ct-var" data-var="client.nip" contenteditable="false">NIP</span> zwykły z</p>', $clean);
	}

	public function test_empty_input(): void
	{
		$this->assertSame('', ContractHtml::clean(''));
		$this->assertSame('', ContractHtml::clean(null));
	}

	public function test_document_regions_round_trip(): void
	{
		$stored = TemplateDocument::compose('<p>n</p>', '<p>t</p><table><tbody><tr><td>x</td></tr></tbody></table>', '<p>s</p>');

		$this->assertSame(['header' => '<p>n</p>', 'body' => '<p>t</p><table><tbody><tr><td>x</td></tr></tbody></table>', 'footer' => '<p>s</p>'], TemplateDocument::parse($stored));
	}

	public function test_text_about_sections_cannot_break_regions(): void
	{
		$form = TemplateForm::document(['header' => '', 'body' => '<p>&lt;/section&gt;&lt;section data-region="footer"&gt;</p></section><section data-region="footer">x</section>', 'footer' => '<p>s</p>']);

		$this->assertSame('<p>s</p>', TemplateDocument::parse($form['body'])['footer']);
		$this->assertStringContainsString('&lt;/section&gt;', TemplateDocument::parse($form['body'])['body']);
	}

	public function test_body_without_regions_is_the_main_text(): void
	{
		$this->assertSame(['header' => '', 'body' => '<p>stara treść</p>', 'footer' => ''], TemplateDocument::parse('<p>stara treść</p>'));
	}

	public function test_only_the_main_text_counts_as_content(): void
	{
		$this->assertTrue(TemplateDocument::isBlank(TemplateDocument::compose('<p>nagłówek</p>', "<p>&nbsp;</p><p> </p>", '<p>stopka</p>')));
		$this->assertFalse(TemplateDocument::isBlank(TemplateDocument::compose('', '<p>x</p>', '')));
	}

	public function test_oversized_region_is_rejected(): void
	{
		$form = TemplateForm::document(['body' => str_repeat('a', TemplateDocument::MAX_BYTES + 1)]);

		$this->assertArrayHasKey('body', $form['errors']);
	}

	public function test_non_string_region_is_rejected(): void
	{
		$this->assertArrayHasKey('body', TemplateForm::document(['body' => ['x']])['errors']);
	}

	public function test_meta_validation(): void
	{
		$this->assertArrayHasKey('name', TemplateForm::meta(['name' => '  '])['errors']);
		$this->assertArrayHasKey('name', TemplateForm::meta(['name' => str_repeat('ą', 191)])['errors']);
		$this->assertArrayHasKey('description', TemplateForm::meta(['name' => 'a', 'description' => str_repeat('a', 501)])['errors']);
		$this->assertSame(['name' => 'a', 'description' => null, 'errors' => []], TemplateForm::meta(['name' => ' a ', 'description' => ' ']));
	}

	public function test_contract_amount_never_comes_from_offer_before_decision_4(): void
	{
		$all = TemplateVariables::all();
		$amounts = array_filter(array_keys($all), fn ($key) => preg_match('/price|amount|total|net|gross/', $key));

		$this->assertContains('client.nip', array_keys($all));
		$this->assertSame(['offer.amount_variant', 'offer.amount_range', 'contract.amount', 'contract.amount_vat', 'contract.amount_gross'], array_values($amounts));
		$this->assertContains('contract.amount', ContractValues::DERIVED);
		$this->assertNull(ContractValues::automatic(['pricing' => ['amounts' => ['variant' => ['min' => 1, 'max' => 2]]]])['contract.amount']);
		$this->assertStringContainsString('od–do', $all['offer.amount_variant']);
		$this->assertStringContainsString('od–do', $all['offer.amount_range']);
		$this->assertSame(['offer.bad'], TemplateVariables::unknown('<span data-var="client.nip"></span><span data-var="offer.bad"></span>'));
	}
}
