<?php

namespace {
	if (! function_exists('sanitize_text_field')) {
		function sanitize_text_field($text)
		{
			return trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
		}
	}

	if (! function_exists('wp_strip_all_tags')) {
		function wp_strip_all_tags($text)
		{
			return trim(strip_tags((string) $text));
		}
	}
}

namespace Tests\Unit\V2 {

	use App\Guesstimate\Ai\FeatureEstimator;
	use App\Guesstimate\Integrations\ClickUp\ClickUpMapper;
	use App\Guesstimate\Offers\ViewTracker;
	use App\Guesstimate\Sitemap\PageList;
	use PHPUnit\Framework\TestCase;

	class V2HelpersTest extends TestCase
	{
		private const DEFAULTS = ['ui_min' => '2', 'ui_max' => '3', 'dev_min' => '2', 'dev_max' => '3'];

		public function test_page_list_keeps_order_and_empty_overrides(): void
		{
			$result = PageList::parse([['name' => 'Strona główna', 'hours_ui_min' => '4,5', 'hours_ui_max' => '6'], ['name' => ' O nas ']], self::DEFAULTS);

			$this->assertSame([], $result['errors']);
			$this->assertSame('Strona główna', $result['pages'][0]['name']);
			$this->assertSame('4.50', $result['pages'][0]['hours_ui_min']);
			$this->assertNull($result['pages'][0]['hours_dev_max']);
			$this->assertSame('O nas', $result['pages'][1]['name']);
		}

		public function test_page_list_validates_names_and_min_max_against_defaults(): void
		{
			$result = PageList::parse([['name' => ''], ['name' => 'X', 'hours_dev_min' => '5']], self::DEFAULTS);

			$this->assertArrayHasKey('pages.0.name', $result['errors']);
			// 5 h min przy domyślnym max 3 h → błąd.
			$this->assertArrayHasKey('pages.1.hours_dev_max', $result['errors']);
		}

		public function test_page_list_limit(): void
		{
			$result = PageList::parse(array_fill(0, PageList::MAX_PAGES + 1, ['name' => 'a']), self::DEFAULTS);

			$this->assertArrayHasKey('pages', $result['errors']);
		}

		public function test_ip_is_truncated(): void
		{
			$this->assertSame('83.12.44.0', ViewTracker::truncateIp('83.12.44.171'));
			$this->assertSame('2001:db8:85a3::', ViewTracker::truncateIp('2001:db8:85a3:8d3:1319:8a2e:370:7348'));
			$this->assertSame('', ViewTracker::truncateIp('nie-ip'));
		}

		public function test_estimator_normalizes_ai_output(): void
		{
			$result = FeatureEstimator::normalize(['hours_min' => 30, 'hours_max' => '12.333', 'risks' => ['API', ['x'], ''], 'reply' => 'ok']);

			$this->assertSame('12.33', $result['hours_min']);
			$this->assertSame('30.00', $result['hours_max']);
			$this->assertSame(['API'], $result['risks']);
		}

		public function test_estimator_without_hours(): void
		{
			$result = FeatureEstimator::normalize(['reply' => 'Za mało danych', 'hours_min' => null]);

			$this->assertNull($result['hours_min']);
			$this->assertNull($result['hours_max']);
		}

		public function test_estimator_conversation_is_limited(): void
		{
			$messages = array_fill(0, 30, ['role' => 'system', 'content' => str_repeat('a', 5000)]);
			$out = FeatureEstimator::conversation($messages);

			$this->assertCount(FeatureEstimator::MAX_MESSAGES, $out);
			$this->assertSame('user', $out[0]['role']);
			$this->assertSame(FeatureEstimator::MAX_CHARS, mb_strlen($out[0]['content']));
		}

		public function test_clickup_mapping_of_custom_fields(): void
		{
			$task = ['name' => ' Show Club ', 'custom_fields' => [
				['id' => 'n', 'type' => 'short_text', 'value' => '526-025-09-95'],
				['id' => 'd', 'type' => 'drop_down', 'value' => 1, 'type_config' => ['options' => [['id' => 'a', 'name' => 'Mała', 'orderindex' => 0], ['id' => 'b', 'name' => 'Duża', 'orderindex' => 1]]]],
				['id' => 'l', 'type' => 'labels', 'value' => ['x']],
				['id' => 'e', 'type' => 'email', 'value' => null],
			]];

			$mapped = ClickUpMapper::map($task, ['company_name' => 'name', 'nip' => 'cf:n', 'contact_name' => 'cf:d', 'phone' => 'cf:l', 'email' => 'cf:e', 'website_url' => '']);

			$this->assertSame('Show Club', $mapped['company_name']);
			$this->assertSame('526-025-09-95', $mapped['nip']);
			$this->assertSame('Duża', $mapped['contact_name']);
			$this->assertNull($mapped['phone']);
			$this->assertNull($mapped['email']);
			$this->assertNull($mapped['website_url']);
		}
	}
}
