<?php

namespace Tests\Unit\Media;

use App\Guesstimate\Auth\Capabilities;
use App\Guesstimate\Auth\Roles;
use App\Guesstimate\Document\DocumentForm;
use App\Guesstimate\Document\ImageCrop;
use App\Guesstimate\Document\Pages;
use App\Guesstimate\Media\MediaLibrary;
use App\Guesstimate\Media\MediaPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Moduł „Biblioteka”: uprawnienia, filtry z adresu, drzewo folderów i niedestrukcyjny kadr.
 */
class MediaUiTest extends TestCase
{
	public function test_employee_edits_only_own_files_manager_all(): void
	{
		$this->assertTrue(MediaPolicy::mayEdit(true, false, 5, 5));
		$this->assertFalse(MediaPolicy::mayEdit(true, false, 5, 6));
		$this->assertTrue(MediaPolicy::mayEdit(true, true, 5, 6));
		$this->assertFalse(MediaPolicy::mayEdit(false, true, 5, 5));
		$this->assertFalse(MediaPolicy::mayEdit(true, false, 0, 0));
	}

	public function test_media_management_is_for_manager_and_administrator(): void
	{
		$definitions = Roles::definitions();

		$this->assertContains(Capabilities::MANAGE_MEDIA, $definitions[Roles::MANAGER]['capabilities']);
		$this->assertContains(Capabilities::MANAGE_MEDIA, $definitions[Roles::ADMINISTRATOR]['capabilities']);
		$this->assertNotContains(Capabilities::MANAGE_MEDIA, $definitions[Roles::EMPLOYEE]['capabilities']);
	}

	public function test_filters_from_query(): void
	{
		$this->assertSame(['folder' => null, 'tags' => [], 'archived' => false], MediaLibrary::filters([]));
		$this->assertSame(['folder' => 3, 'tags' => [4, 9], 'archived' => true], MediaLibrary::filters(['folder' => '3', 'tag' => ['4', 'x', '9'], 'archived' => '1']));
		$this->assertSame(['folder' => null, 'tags' => [7], 'archived' => false], MediaLibrary::filters(['folder' => '-1', 'tag' => '7']));
	}

	public function test_folder_tree_keeps_children_under_parent(): void
	{
		$tree = MediaLibrary::tree([
			['id' => 1, 'name' => 'A', 'parent' => 0, 'count' => 0],
			['id' => 2, 'name' => 'B', 'parent' => 0, 'count' => 0],
			['id' => 3, 'name' => 'C', 'parent' => 2, 'count' => 0],
			['id' => 4, 'name' => 'D', 'parent' => 1, 'count' => 0],
		]);

		$this->assertSame([1, 4, 2, 3], array_column($tree, 'id'));
		$this->assertSame([0, 1, 0, 1], array_column($tree, 'depth'));
	}

	public function test_crop_is_normalized_and_default_is_null(): void
	{
		$this->assertNull(ImageCrop::normalize(null));
		$this->assertNull(ImageCrop::normalize('x'));
		$this->assertNull(ImageCrop::normalize(['x' => 50, 'y' => 50, 'zoom' => 1]));
		$this->assertSame(['x' => 0.0, 'y' => 100.0, 'zoom' => 4.0], ImageCrop::normalize(['x' => -5, 'y' => 140, 'zoom' => 9]));
		$this->assertSame(['x' => 50.0, 'y' => 20.5, 'zoom' => 1.0], ImageCrop::normalize(['x' => 'abc', 'y' => '20.5']));
	}

	public function test_crop_style_uses_dot_and_contains_no_input(): void
	{
		$this->assertSame('', ImageCrop::style(null));
		$this->assertSame('object-position:12.5% 80%;transform-origin:12.5% 80%;transform:scale(1.25)', ImageCrop::style(['x' => 12.5, 'y' => 80, 'zoom' => 1.25]));
	}

	public function test_crop_belongs_to_the_page_image(): void
	{
		$pages = [];

		foreach (array_keys(Pages::TYPES) as $key) {
			$pages[$key] = Pages::blank($key);
		}

		$pages['situation']['image_id'] = 7;
		$current = ['pages' => $pages];

		$saved = DocumentForm::design(['situation' => ['image_crop' => ['x' => 10, 'y' => 20, 'zoom' => 2]]], $current);
		$this->assertSame(['x' => 10.0, 'y' => 20.0, 'zoom' => 2.0], $saved['data']['pages']['situation']['image_crop']);

		$removed = DocumentForm::design(['situation' => ['image_id' => null, 'image_crop' => ['x' => 10]]], $saved['data']);
		$this->assertNull($removed['data']['pages']['situation']['image_crop']);

		$noImage = DocumentForm::design(['cover' => ['image_crop' => ['x' => 10]]], $current);
		$this->assertNull($noImage['data']['pages']['cover']['image_crop']);
	}
}
