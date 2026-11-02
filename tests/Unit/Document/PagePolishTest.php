<?php

namespace Tests\Unit\Document;

use App\Guesstimate\Document\DocumentForm;
use App\Guesstimate\Document\PagePolish;
use App\Guesstimate\Document\Pages;
use PHPUnit\Framework\TestCase;

class PagePolishTest extends TestCase
{
	public function test_defaults_are_not_stored(): void
	{
		foreach (array_keys(Pages::TYPES) as $key) {
			$this->assertNull(PagePolish::normalize(null, $key));
			$this->assertNull(PagePolish::normalize([], $key));
			$this->assertNull(PagePolish::normalize([
				'heading_lift' => 0,
				'hide_edge_label' => false,
				'image_fit' => 'cover',
				'title_size' => '',
				'image_ratio' => '',
				'image_crops' => [3 => ['x' => 50, 'y' => 50, 'zoom' => 1]],
			], $key));
		}
	}

	public function test_options_belong_to_their_pages(): void
	{
		$all = ['heading_lift' => 40, 'hide_edge_label' => true, 'image_fit' => 'contain', 'title_size' => 'large', 'image_ratio' => 'square', 'image_crops' => [3 => ['x' => 10]]];

		$this->assertSame(['heading_lift' => 40, 'image_fit' => 'contain'], PagePolish::normalize($all, 'cover'));
		$this->assertSame(['hide_edge_label' => true], PagePolish::normalize($all, 'pricing'));
		$this->assertNull(PagePolish::normalize($all, 'extras'));
		$this->assertSame(['image_fit' => 'contain'], PagePolish::normalize($all, 'next_steps'));
		$this->assertSame([
			'hide_edge_label' => true,
			'image_fit' => 'contain',
			'title_size' => 'large',
			'image_ratio' => 'square',
			'image_crops' => [3 => ['x' => 10.0, 'y' => 50.0, 'zoom' => 1.0]],
		], PagePolish::normalize($all, 'references'));
	}

	public function test_values_are_bounded(): void
	{
		$this->assertSame(['heading_lift' => 100], PagePolish::normalize(['heading_lift' => 900], 'cover'));
		$this->assertNull(PagePolish::normalize(['heading_lift' => -5], 'cover'));
		$this->assertNull(PagePolish::normalize(['heading_lift' => '1;color:red'], 'cover'));
		$this->assertNull(PagePolish::normalize(['title_size' => 'osf-t-display', 'image_ratio' => 'x', 'image_fit' => 'fill'], 'references'));
		$this->assertSame(4.0, PagePolish::normalize(['image_crops' => [3 => ['zoom' => 90]]], 'references')['image_crops'][3]['zoom']);
		$this->assertNull(PagePolish::normalize(['image_crops' => ['x' => ['zoom' => 2]]], 'references'));
	}

	public function test_classes_keep_the_template_by_default(): void
	{
		$this->assertSame('osf-t-sm', PagePolish::titleClass([]));
		$this->assertSame('aspect-video', PagePolish::ratioClass([]));
		$this->assertSame('osf-t-xl', PagePolish::titleClass(['title_size' => 'large']));
		$this->assertSame('aspect-square', PagePolish::ratioClass(['image_ratio' => 'square']));
	}

	public function test_form_keeps_absent_polish_and_drops_crops_of_removed_references(): void
	{
		$current = ['reference_ids' => [3], 'pages' => ['references' => Pages::blank('references') + ['polish' => ['title_size' => 'large']]]];

		$this->assertSame(['title_size' => 'large'], DocumentForm::design(['references' => ['layout' => 'tiles']], $current)['data']['pages']['references']['polish']);

		$saved = DocumentForm::design(['references' => ['polish' => ['image_crops' => [3 => ['x' => 10], 8 => ['x' => 20]]]]], $current);
		$this->assertSame([3], array_keys($saved['data']['pages']['references']['polish']['image_crops']));

		$this->assertNull(DocumentForm::design(['references' => ['polish' => null]], $current)['data']['pages']['references']['polish']);
	}
}
