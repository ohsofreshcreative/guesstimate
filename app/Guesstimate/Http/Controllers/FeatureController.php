<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Library\Feature;
use App\Guesstimate\Library\FeatureCategory;
use App\Guesstimate\Library\FeatureService;
use App\Guesstimate\Settings\SettingsRepository;
use App\Guesstimate\Support\Input;
use Illuminate\Http\Request;

/**
 * Biblioteka funkcjonalności (tylko administrator). Zmiany nie wpływają na zapisane oferty.
 */
class FeatureController extends Controller
{
	public function __construct(private FeatureService $features, private SettingsRepository $settings)
	{
	}

	public function index()
	{
		return view('guesstimate.features.index', $this->data());
	}

	public function store(Request $request)
	{
		['data' => $data, 'errors' => $errors] = $this->features->validate($request->all());

		if ($errors) {
			return $this->invalid('guesstimate.features.index', array_merge($this->data(), ['openModal' => 'feature-create']), $errors);
		}

		$feature = Feature::create(array_merge($data, [
			'created_by' => get_current_user_id(),
			'sort' => (int) Feature::where('category_id', $data['category_id'])->max('sort') + 1,
		]));

		return $this->redirectWith(route('gst.features.index') . '#kategoria-' . $feature->category_id, sprintf('Dodano funkcjonalność „%s”.', $feature->name));
	}

	public function update(Request $request, Feature $feature)
	{
		['data' => $data, 'errors' => $errors] = $this->features->validate($request->all());

		if ($errors) {
			return $this->invalid('guesstimate.features.index', array_merge($this->data(), ['openModal' => 'feature-' . $feature->id]), $errors);
		}

		$feature->update($data);

		return $this->redirectWith(route('gst.features.index') . '#kategoria-' . $feature->category_id, sprintf('Zapisano funkcjonalność „%s”.', $feature->name));
	}

	public function storeCategory(Request $request)
	{
		$name = Input::line($request->input('name'));

		if ($name === null || mb_strlen($name) > 150) {
			return $this->invalid('guesstimate.features.index', array_merge($this->data(), ['openModal' => 'category-create']), ['name' => 'Podaj nazwę kategorii (do 150 znaków).']);
		}

		FeatureCategory::create(['name' => $name, 'sort' => (int) FeatureCategory::max('sort') + 1]);

		return $this->redirectWith(route('gst.features.index'), sprintf('Dodano kategorię „%s”.', $name));
	}

	public function updateCategory(Request $request, FeatureCategory $category)
	{
		$name = Input::line($request->input('name'));

		if ($name === null || mb_strlen($name) > 150) {
			return $this->invalid('guesstimate.features.index', array_merge($this->data(), ['openModal' => 'category-' . $category->id]), ['name' => 'Podaj nazwę kategorii (do 150 znaków).']);
		}

		$category->update(['name' => $name]);

		return $this->redirectWith(route('gst.features.index') . '#kategoria-' . $category->id, 'Zapisano nazwę kategorii.');
	}

	private function data(): array
	{
		return [
			'categories' => FeatureCategory::with('features')->orderBy('sort')->orderBy('name')->get(),
			'stages' => $this->settings->stageNames(),
		];
	}
}
