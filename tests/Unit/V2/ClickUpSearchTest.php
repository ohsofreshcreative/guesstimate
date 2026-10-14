<?php

namespace Tests\Unit\V2;

use App\Guesstimate\Integrations\ClickUp\ClickUpDirectory;
use PHPUnit\Framework\TestCase;

/**
 * Wyszukiwarka ClickUp (lokalny indeks użytkownika): porównanie bez wielkości liter i polskich znaków,
 * klucz nazwy firmy do wykrywania podobnych klientów przy imporcie.
 */
class ClickUpSearchTest extends TestCase
{
	public function test_fold_ignores_case_and_polish_letters(): void
	{
		$this->assertSame('przykladowa firma sp. z o.o.', ClickUpDirectory::fold('Przykładowa  Firma Sp. z o.o.'));
		$this->assertSame('lodzka spoldzielnia sniezka', ClickUpDirectory::fold('ŁÓDZKA Spółdzielnia Śnieżka'));
		$this->assertSame('zolw zrebak', ClickUpDirectory::fold("Żółw\u{00A0}Źrebak"));
		$this->assertSame('firma & syn', ClickUpDirectory::fold('Firma &amp; Syn'));
		$this->assertStringContainsString(ClickUpDirectory::fold('przykład'), ClickUpDirectory::fold('Przykładowa Firma'));
		$this->assertSame('', ClickUpDirectory::fold(null));
	}

	public function test_company_key_strips_legal_forms_and_punctuation(): void
	{
		$this->assertSame(ClickUpDirectory::companyKey('Przykładowa Firma Sp. z o.o.'), ClickUpDirectory::companyKey('PRZYKLADOWA FIRMA'));
		$this->assertSame(ClickUpDirectory::companyKey('Łódzka S.A.'), ClickUpDirectory::companyKey('lodzka'));
		$this->assertNotSame(ClickUpDirectory::companyKey('Alfa Dom'), ClickUpDirectory::companyKey('Alfa'));
	}
}
