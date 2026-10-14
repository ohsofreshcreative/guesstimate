<?php

namespace Tests\Unit\Auth;

use App\Guesstimate\Auth\Capabilities;
use App\Guesstimate\Auth\Roles;
use PHPUnit\Framework\TestCase;

class RolesTest extends TestCase
{
	public function test_administrator_has_every_capability(): void
	{
		$this->assertEqualsCanonicalizing(Capabilities::all(), $this->capabilitiesOf(Roles::ADMINISTRATOR));
	}

	public function test_manager_works_on_all_offers_and_clients_but_not_on_configuration(): void
	{
		$caps = $this->capabilitiesOf(Roles::MANAGER);

		$this->assertContains(Capabilities::VIEW_ALL_OFFERS, $caps);
		$this->assertContains(Capabilities::EDIT_ALL_OFFERS, $caps);
		$this->assertContains(Capabilities::MANAGE_CLIENTS, $caps);
		$this->assertNotContains(Capabilities::MANAGE_SETTINGS, $caps);
		$this->assertNotContains(Capabilities::MANAGE_LIBRARY, $caps);
		$this->assertNotContains(Capabilities::MANAGE_USERS, $caps);
	}

	public function test_employee_creates_offers_and_clients_but_sees_only_own_offers(): void
	{
		$this->assertEqualsCanonicalizing(
			[Capabilities::ACCESS_APP, Capabilities::CREATE_OFFERS, Capabilities::CREATE_CLIENTS],
			$this->capabilitiesOf(Roles::EMPLOYEE),
		);
	}

	public function test_every_role_uses_only_known_capabilities_and_can_open_the_app(): void
	{
		foreach (Roles::definitions() as $role => $definition) {
			$this->assertSame([], array_diff($definition['capabilities'], Capabilities::all()), $role);
			$this->assertContains(Capabilities::ACCESS_APP, $definition['capabilities'], $role);
		}
	}

	/**
	 * @return list<string>
	 */
	private function capabilitiesOf(string $role): array
	{
		return Roles::definitions()[$role]['capabilities'];
	}
}
