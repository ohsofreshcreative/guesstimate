<?php

namespace App\Guesstimate\Contracts {
	function current_user_can(string $capability): bool
	{
		return in_array($capability, $GLOBALS['gst_test_caps'] ?? [], true);
	}

	function get_current_user_id(): int
	{
		return $GLOBALS['gst_test_user'] ?? 0;
	}
}

namespace App\Guesstimate\Offers {
	function current_user_can(string $capability): bool
	{
		return in_array($capability, $GLOBALS['gst_test_caps'] ?? [], true);
	}

	function get_current_user_id(): int
	{
		return $GLOBALS['gst_test_user'] ?? 0;
	}
}

namespace Tests\Unit\Contracts {
	use App\Guesstimate\Auth\Capabilities;
	use App\Guesstimate\Auth\Roles;
	use App\Guesstimate\Contracts\Contract;
	use App\Guesstimate\Contracts\ContractEvent;
	use App\Guesstimate\Contracts\ContractPolicy;
	use App\Guesstimate\Contracts\ContractSnapshot;
	use App\Guesstimate\Contracts\ContractStatus;
	use App\Guesstimate\Contracts\ContractTemplateVersion;
	use App\Guesstimate\Contracts\ContractVersion;
	use App\Guesstimate\Offers\Offer;
	use App\Guesstimate\Offers\OfferPolicy;
	use LogicException;
	use PHPUnit\Framework\TestCase;

	class ContractModelTest extends TestCase
	{
		protected function tearDown(): void
		{
			unset($GLOBALS['gst_test_caps'], $GLOBALS['gst_test_user']);
		}

		private function as(string $role, int $user): ContractPolicy
		{
			$GLOBALS['gst_test_caps'] = Roles::definitions()[$role]['capabilities'];
			$GLOBALS['gst_test_user'] = $user;

			return new ContractPolicy(new OfferPolicy());
		}

		private function contract(int $author, int $offerAuthor = 0): Contract
		{
			$contract = (new Contract())->forceFill(['author_id' => $author]);
			$contract->setRelation('offer', (new Offer())->forceFill(['author_id' => $offerAuthor]));

			return $contract;
		}

		public function test_statuses(): void
		{
			$this->assertSame(['draft', 'approved'], array_column(ContractStatus::cases(), 'value'));
			$this->assertSame('Szkic', ContractStatus::Draft->label());
			$this->assertSame('Zatwierdzona', ContractStatus::Approved->label());
			$this->assertTrue(ContractStatus::Draft->isEditable());
			$this->assertFalse(ContractStatus::Approved->isEditable());
		}

		public function test_employee_works_only_on_own_contracts_and_cannot_approve(): void
		{
			$policy = $this->as(Roles::EMPLOYEE, 7);

			$this->assertTrue($policy->canView($this->contract(7)));
			$this->assertTrue($policy->canView($this->contract(9, 7)), 'umowa do własnej oferty');
			$this->assertFalse($policy->canView($this->contract(9, 8)));
			$this->assertTrue($policy->canEdit($this->contract(7)));
			$this->assertFalse($policy->canEdit($this->contract(9, 7)));
			$this->assertFalse($policy->canApprove());
			$this->assertFalse($policy->canManageTemplates());
		}

		public function test_manager_and_administrator_see_all_approve_and_manage_templates(): void
		{
			foreach ([Roles::MANAGER, Roles::ADMINISTRATOR] as $role) {
				$policy = $this->as($role, 1);

				$this->assertTrue($policy->canView($this->contract(9, 8)), $role);
				$this->assertTrue($policy->canEdit($this->contract(9, 8)), $role);
				$this->assertTrue($policy->canApprove(), $role);
				$this->assertTrue($policy->canManageTemplates(), $role);
			}
		}

		public function test_guest_has_no_access(): void
		{
			$GLOBALS['gst_test_caps'] = [];
			$GLOBALS['gst_test_user'] = 0;
			$policy = new ContractPolicy(new OfferPolicy());

			$this->assertFalse($policy->canEdit($this->contract(1)));
			$this->assertFalse($policy->canApprove());
			$this->assertFalse($policy->canManageTemplates());
		}

		public function test_new_capabilities_are_registered_and_roles_will_sync(): void
		{
			$this->assertContains(Capabilities::APPROVE_CONTRACTS, Capabilities::all());
			$this->assertContains(Capabilities::MANAGE_CONTRACT_TEMPLATES, Capabilities::all());
			$this->assertSame(5, Roles::VERSION);
			$this->assertNotContains(Capabilities::APPROVE_CONTRACTS, Roles::definitions()[Roles::EMPLOYEE]['capabilities']);
		}

		public function test_hash_covers_body_and_snapshot_bytes(): void
		{
			$hash = ContractVersion::hash('§1', '{"a":1}');

			$this->assertSame($hash, ContractVersion::hash('§1', '{"a":1}'));
			$this->assertNotSame($hash, ContractVersion::hash('§2', '{"a":1}'));
			$this->assertNotSame($hash, ContractVersion::hash('§1', '{"a":1.0}'), 'zmiana zapisu liczby to inne bajty');
			$this->assertNotSame(ContractVersion::hash('ab', 'c'), ContractVersion::hash('a', 'bc'), 'granica treści i danych');
		}

		public function test_snapshot_keeps_every_variant_and_does_not_pick_a_contract_amount(): void
		{
			$pricing = ['calc_model' => 'budget', 'selected_variant' => 'mid', 'result' => ['levels' => ['low' => ['total' => ['min' => 1]], 'high' => ['total' => ['max' => 9]]]]];
			$snapshot = ContractSnapshot::compose(['id' => 2, 'nip' => '123'], ['id' => 3], $pricing, '2026-11-02T10:00:00+00:00');

			$this->assertSame(ContractSnapshot::SCHEMA, $snapshot['schema']);
			$this->assertSame($pricing, $snapshot['pricing']);
			$this->assertArrayNotHasKey('amount', $snapshot);
			$this->assertArrayNotHasKey('amount', $snapshot['pricing']);
		}

		public function test_approved_record_refuses_changes_but_draft_does_not(): void
		{
			foreach ([ContractVersion::class, ContractTemplateVersion::class] as $class) {
				$draft = (new $class())->setRawAttributes(['status' => 'draft'], true);
				$draft->assertNotFrozen();

				$approved = (new $class())->setRawAttributes(['status' => 'approved'], true);
				$this->assertTrue($approved->isApproved());

				try {
					$approved->assertNotFrozen();
					$this->fail($class);
				} catch (LogicException $e) {
					$this->assertStringContainsString('nową wersję', $e->getMessage());
				}
			}
		}

		public function test_integrity_fails_when_content_changes_after_approval(): void
		{
			$version = (new ContractVersion())->setRawAttributes(['body' => '§1', 'data_snapshot' => '{"a":1}', 'content_hash' => ContractVersion::hash('§1', '{"a":1}')]);
			$this->assertTrue($version->isIntact());

			$version->setRawAttributes(['body' => '§1 zmieniony', 'data_snapshot' => '{"a":1}', 'content_hash' => ContractVersion::hash('§1', '{"a":1}')]);
			$this->assertFalse($version->isIntact());

			$this->assertFalse((new ContractVersion())->setRawAttributes(['body' => '', 'data_snapshot' => '{}', 'content_hash' => null])->isIntact());
		}

		public function test_audit_log_has_no_update_timestamp(): void
		{
			$this->assertNull(ContractEvent::UPDATED_AT);
		}
	}
}
