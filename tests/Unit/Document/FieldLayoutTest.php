<?php

namespace Tests\Unit\Document;

use App\Guesstimate\Document\DocumentForm;
use App\Guesstimate\Document\DocumentSettings;
use App\Guesstimate\Document\DocumentView;
use App\Guesstimate\Document\FieldLayout;
use App\Guesstimate\Document\Pages;
use App\Guesstimate\Offers\PublicationService;
use PHPUnit\Framework\TestCase;

/**
 * Układ pól: schemat, trzymanie się strony i fallback dla dokumentów bez układu.
 */
class FieldLayoutTest extends TestCase
{
	private function field(array $overrides = []): array
	{
		return array_replace(['id' => 'heading', 'page_id' => 'situation', 'x' => 10, 'y' => 20, 'width' => 40, 'height' => 15], $overrides);
	}

	private function layout(array ...$items): array
	{
		return ['schema' => FieldLayout::SCHEMA, 'items' => $items];
	}

	public function test_full_schema_is_stored(): void
	{
		$layout = FieldLayout::normalize($this->layout($this->field([
			'z_index' => 3,
			'style' => ['align' => 'center'],
			'constraints' => ['locked' => true, 'min_width' => 20, 'min_height' => 5],
		])), 'situation');

		$this->assertSame(['schema' => 1, 'items' => [[
			'id' => 'heading',
			'page_id' => 'situation',
			'breakpoint' => 'sheet',
			'x' => 10.0,
			'y' => 20.0,
			'width' => 40.0,
			'height' => 15.0,
			'z_index' => 3,
			'style' => ['align' => 'center'],
			'constraints' => ['locked' => true, 'min_width' => 20.0, 'min_height' => 5.0],
		]]], $layout);
	}

	public function test_no_layout_or_unknown_schema_falls_back_to_template(): void
	{
		$this->assertNull(FieldLayout::normalize(null, 'situation'));
		$this->assertNull(FieldLayout::normalize('x', 'situation'));
		$this->assertNull(FieldLayout::normalize(['items' => [$this->field()]], 'situation'));
		$this->assertNull(FieldLayout::normalize(['schema' => FieldLayout::SCHEMA + 1, 'items' => [$this->field()]], 'situation'));
		$this->assertNull(FieldLayout::normalize($this->layout(), 'situation'));
	}

	public function test_field_never_leaves_the_page(): void
	{
		$item = FieldLayout::normalize($this->layout($this->field(['x' => 95, 'y' => -30, 'width' => 40, 'height' => 500])), 'situation')['items'][0];

		$this->assertSame([60.0, 0.0, 40.0, 100.0], [$item['x'], $item['y'], $item['width'], $item['height']]);

		$tiny = FieldLayout::normalize($this->layout($this->field(['x' => 100, 'y' => 100, 'width' => 0, 'height' => 0])), 'situation')['items'][0];

		$this->assertSame([95.0, 98.0, 5.0, 2.0], [$tiny['x'], $tiny['y'], $tiny['width'], $tiny['height']]);
	}

	public function test_constraints_raise_minimum_size(): void
	{
		$item = FieldLayout::normalize($this->layout($this->field(['width' => 5, 'constraints' => ['min_width' => 30]])), 'situation')['items'][0];

		$this->assertSame(30.0, $item['width']);
	}

	public function test_invalid_entries_are_skipped(): void
	{
		$layout = FieldLayout::normalize($this->layout(
			$this->field(['id' => 'price']),
			$this->field(['page_id' => 'cover']),
			$this->field(['breakpoint' => 'fluid']),
			$this->field(['x' => 'abc']),
			$this->field(['y' => INF]),
			$this->field(['id' => 'situation']),
		), 'situation');

		$this->assertSame(['situation'], array_column($layout['items'], 'id'));
	}

	public function test_only_listed_fields_exist_for_each_page(): void
	{
		$this->assertSame(array_keys(Pages::TYPES), array_keys(FieldLayout::FIELDS));
		$this->assertNull(FieldLayout::normalize($this->layout($this->field(['id' => 'situation', 'page_id' => 'pricing'])), 'pricing'));
	}

	public function test_repeated_field_keeps_last_entry(): void
	{
		$layout = FieldLayout::normalize($this->layout($this->field(['x' => 1]), $this->field(['x' => 2])), 'situation');

		$this->assertCount(1, $layout['items']);
		$this->assertSame(2.0, $layout['items'][0]['x']);
	}

	public function test_style_accepts_only_known_values(): void
	{
		$item = FieldLayout::normalize($this->layout($this->field(['style' => ['align' => 'left;background:url(x)', 'color' => 'red']])), 'situation')['items'][0];

		$this->assertSame(['align' => 'left'], $item['style']);
	}

	public function test_attributes_are_empty_without_position(): void
	{
		$layout = FieldLayout::normalize($this->layout($this->field()), 'situation');

		$this->assertSame('', FieldLayout::attributes(null, 'heading'));
		$this->assertSame('', FieldLayout::attributes($layout, 'situation'));
		$this->assertSame(
			'data-field="heading" style="--field-x:10;--field-y:20;--field-w:40;--field-h:15;--field-z:1;--field-align:left"',
			FieldLayout::attributes($layout, 'heading'),
		);
	}

	public function test_copy_keeps_clean_text_and_role(): void
	{
		$item = FieldLayout::normalize($this->layout($this->field(['id' => 'copy_1', 'text' => " <b>Kopia</b> ", 'style' => ['role' => 'heading']])), 'situation')['items'][0];

		$this->assertSame('Kopia', $item['text']);
		$this->assertSame('heading', $item['style']['role']);
		$this->assertSame([$item], FieldLayout::copies(['items' => [$item]]));
	}

	public function test_invalid_copies_are_skipped(): void
	{
		$this->assertNull(FieldLayout::normalize($this->layout($this->field(['id' => 'copy_1', 'text' => '<br>'])), 'situation'));
		$this->assertNull(FieldLayout::normalize($this->layout($this->field(['id' => 'copy_x', 'text' => 'A'])), 'situation'));
		$this->assertNull(FieldLayout::normalize($this->layout($this->field(['id' => 'copy_1'])), 'situation'));
	}

	public function test_number_of_copies_is_limited(): void
	{
		$items = array_map(fn ($n) => $this->field(['id' => 'copy_' . $n, 'text' => 'A']), range(1, FieldLayout::MAX_COPIES + 2));

		$this->assertCount(FieldLayout::MAX_COPIES, FieldLayout::normalize($this->layout(...$items), 'situation')['items']);
	}

	public function test_locked_field_is_marked_in_html(): void
	{
		$layout = FieldLayout::normalize($this->layout($this->field(['constraints' => ['locked' => true]])), 'situation');

		$this->assertStringStartsWith('data-field="heading" data-locked style=', FieldLayout::attributes($layout, 'heading'));
	}

	public function test_form_saves_and_clears_layout_keeping_other_page_data(): void
	{
		$pages = [];

		foreach (array_keys(Pages::TYPES) as $key) {
			$pages[$key] = Pages::blank($key);
		}

		$pages['situation']['heading'] = 'Własny';
		$saved = DocumentForm::design(['situation' => ['fields' => $this->layout($this->field())]], ['pages' => $pages]);

		$this->assertSame([], $saved['errors']);
		$this->assertSame(10.0, $saved['data']['pages']['situation']['fields']['items'][0]['x']);
		$this->assertSame('Własny', $saved['data']['pages']['situation']['heading']);
		$this->assertArrayNotHasKey('fields', $saved['data']['pages']['cover']);

		$cleared = DocumentForm::design(['situation' => ['fields' => null]], $saved['data']);

		$this->assertNull($cleared['data']['pages']['situation']['fields']);
	}

	/**
	 * MySQL sortuje klucze JSON i zapisuje 10 jako 10.0. Pomiar z chwili zapisu musi być ważny po odczycie,
	 * inaczej przepełniona strona przechodzi przez blokadę publikacji.
	 */
	public function test_measurement_hashes_survive_json_column_round_trip(): void
	{
		$saved = ['pages' => ['situation' => ['heading' => null, 'layout' => 'full', 'image_id' => null, 'fields' => FieldLayout::normalize($this->layout($this->field()), 'situation')]]];
		$read = $saved;
		$item = $read['pages']['situation']['fields']['items'][0];
		ksort($item);
		$read['pages']['situation']['fields']['items'][0] = ['x' => 10, 'y' => 20, 'width' => 40, 'height' => 15] + $item;
		$content = ['situation' => '<p>A</p>'];

		$this->assertSame(DocumentSettings::fitHash($saved, $content), DocumentSettings::fitHash($read, $content));
		$this->assertSame(DocumentSettings::layoutHash($saved, $content), DocumentSettings::layoutHash($read, $content));

		$moved = $saved;
		$moved['pages']['situation']['fields']['items'][0]['x'] = 11.0;

		$this->assertNotSame(DocumentSettings::fitHash($saved, $content), DocumentSettings::fitHash($moved, $content));
		$this->assertNotSame(DocumentSettings::layoutHash($saved, $content), DocumentSettings::layoutHash($moved, $content));
	}

	public function test_range_errors_reject_instead_of_moving_the_field(): void
	{
		$valid = $this->layout($this->field());

		$this->assertSame([], FieldLayout::rangeErrors(null, 'situation'));
		$this->assertSame([], FieldLayout::rangeErrors($valid, 'situation'));
		$this->assertSame([], FieldLayout::rangeErrors($this->layout($this->field(['x' => 60, 'width' => 40.005])), 'situation'), 'zaokrąglenie to nie błąd');

		foreach ([
			'poza stronę' => ['x' => -1],
			'poza stronę ' => ['x' => 70, 'width' => 40],
			'poza stronę  ' => ['y' => 90, 'height' => 15],
			'liczbami' => ['x' => '1;color:red'],
			'za małe' => ['width' => 3],
			'warstwa' => ['z_index' => 500],
		] as $message => $change) {
			$this->assertStringContainsString(trim($message), FieldLayout::rangeErrors($this->layout($this->field($change)), 'situation')[0], json_encode($change));
		}

		$this->assertSame(['nieznana wersja układu pól.'], FieldLayout::rangeErrors(['schema' => 9, 'items' => []], 'situation'));
		$this->assertSame(['nieznane pole układu.'], FieldLayout::rangeErrors($this->layout($this->field(['id' => 'price'])), 'pricing'));
	}

	public function test_form_refuses_out_of_range_values(): void
	{
		$current = ['pages' => ['situation' => Pages::blank('situation'), 'cover' => Pages::blank('cover')]];

		$fields = DocumentForm::design(['situation' => ['fields' => $this->layout($this->field(['x' => 95]))]], $current);
		$this->assertNull($fields['data']);
		$this->assertStringContainsString('Sytuacja klienta', $fields['errors']['pages.situation.fields']);

		$polish = DocumentForm::design(['cover' => ['polish' => ['heading_lift' => 300]]], $current);
		$this->assertArrayHasKey('pages.cover.polish', $polish['errors']);

		$crop = DocumentForm::design(['situation' => ['image_crop' => ['zoom' => 9]]], $current);
		$this->assertArrayHasKey('pages.situation.image_crop', $crop['errors']);
	}

	public function test_template_mode_ignores_layout_without_removing_it(): void
	{
		$layout = FieldLayout::normalize($this->layout($this->field()), 'situation');
		$doc = ['pages' => ['situation' => ['fields' => $layout]]];

		$this->assertSame($layout, DocumentSettings::activeFields($doc, 'situation'));
		$this->assertNull(DocumentSettings::activeFields($doc + ['layout_mode' => 'template'], 'situation'));
		$this->assertSame($layout, $doc['pages']['situation']['fields']);

		$content = ['situation' => '<p>A</p>'];
		$this->assertNotSame(DocumentSettings::fitHash($doc, $content), DocumentSettings::fitHash($doc + ['layout_mode' => 'template'], $content));
		$this->assertSame(DocumentSettings::fitHash(['pages' => ['situation' => []]], $content), DocumentSettings::fitHash(['layout_mode' => 'template', 'pages' => ['situation' => []]], $content), 'stary pomiar zostaje ważny');
	}

	public function test_heading_space_is_held_only_for_a_sole_heading_field(): void
	{
		$heading = ['schema' => 1, 'items' => [['id' => 'heading']]];

		$this->assertTrue(FieldLayout::holdsHeadingSpace(['key' => 'pricing', 'fields' => $heading]));
		$this->assertFalse(FieldLayout::holdsHeadingSpace(['key' => 'situation', 'fields' => $heading]));
		$this->assertFalse(FieldLayout::holdsHeadingSpace(['key' => 'pricing']));
		$this->assertFalse(FieldLayout::holdsHeadingSpace(['key' => 'pricing', 'fields' => ['schema' => 1, 'items' => [['id' => 'copy_1']]]]));
	}

	public function test_view_reads_every_snapshot_generation_without_changing_it(): void
	{
		$snapshot = ['schema' => 2, 'document' => ['pages' => [['key' => 'situation', 'layout' => 'full']]]];
		$before = PublicationService::fingerprint($snapshot);
		$view = DocumentView::fromSnapshot($snapshot);

		$this->assertArrayNotHasKey('fields', $view['document']['pages'][0]);
		$this->assertSame($before, PublicationService::fingerprint($snapshot));

		$snapshot['document']['pages'][0]['fields'] = ['schema' => 99, 'items' => [$this->field()]];
		$this->assertArrayNotHasKey('fields', DocumentView::fromSnapshot($snapshot)['document']['pages'][0]);
	}
}
