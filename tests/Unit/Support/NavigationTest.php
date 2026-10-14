<?php

namespace Tests\Unit\Support;

use App\Guesstimate\Auth\Roles;
use App\Guesstimate\Support\Navigation;
use PHPUnit\Framework\TestCase;

class NavigationTest extends TestCase
{
	public function test_employee_sees_dashboard_offers_and_own_integrations(): void
	{
		$this->assertSame(['dashboard', 'offers', 'integrations'], $this->keysFor(Roles::EMPLOYEE));
	}

	public function test_manager_also_sees_clients(): void
	{
		$this->assertSame(['dashboard', 'offers', 'clients', 'integrations'], $this->keysFor(Roles::MANAGER));
	}

	public function test_administrator_sees_every_module(): void
	{
		$this->assertSame(['dashboard', 'offers', 'clients', 'features', 'integrations', 'settings'], $this->keysFor(Roles::ADMINISTRATOR));
	}

	public function test_module_stays_active_on_its_subpages(): void
	{
		$items = $this->build(Roles::ADMINISTRATOR, 'gst.offers.show');
		$active = array_column(array_filter($items, fn ($item) => $item['active']), 'key');

		$this->assertSame(['offers'], array_values($active));
	}

	public function test_dashboard_is_not_active_on_other_modules(): void
	{
		$items = $this->build(Roles::ADMINISTRATOR, 'gst.settings.index');

		$this->assertFalse($items[0]['active']);
	}

	/**
	 * @return list<string>
	 */
	private function keysFor(string $role): array
	{
		return array_column($this->build($role, 'gst.dashboard'), 'key');
	}

	private function build(string $role, ?string $currentRoute): array
	{
		$caps = Roles::definitions()[$role]['capabilities'];

		return Navigation::build(
			fn (string $capability) => in_array($capability, $caps, true),
			fn (string $route) => '/' . $route,
			$currentRoute,
		);
	}
}
