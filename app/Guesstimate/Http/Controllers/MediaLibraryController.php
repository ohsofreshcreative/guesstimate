<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Document\Media;
use App\Guesstimate\Media\MediaAttributes;
use App\Guesstimate\Media\MediaLibrary;
use App\Guesstimate\Media\MediaPolicy;
use App\Guesstimate\Media\MediaUsage;
use App\Guesstimate\Support\Input;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Moduł „Biblioteka”: przeglądanie, przesyłanie, opis i usuwanie grafik oraz foldery.
 * Pliki zawsze w bibliotece mediów WordPressa; dostęp do modułu pilnuje `gst.can`, do pliku — MediaPolicy.
 */
class MediaLibraryController extends Controller
{
	private const PER_PAGE = 40;

	public function __construct(private MediaPolicy $policy)
	{
	}

	public function index()
	{
		return view('guesstimate.library.index', ['config' => [
			'canUpload' => $this->policy->canUpload(),
			'canManage' => $this->policy->canManage(),
			'folders' => MediaLibrary::tree(MediaLibrary::folders()),
			'tags' => MediaLibrary::tags(),
			'licenses' => MediaAttributes::LICENSES,
			'mimes' => Media::MIMES,
			'maxBytes' => Media::maxBytes(),
			'urls' => [
				'items' => route('gst.library.items'),
				'folders' => route('gst.library.folders.store'),
			],
		]]);
	}

	public function items(Request $request): JsonResponse
	{
		$query = (array) $request->query();

		return response()->json(Media::browse((string) ($query['q'] ?? ''), (int) ($query['page'] ?? 1), self::PER_PAGE, MediaLibrary::filters($query)));
	}

	public function show(int $image): JsonResponse
	{
		$this->find($image);

		return response()->json($this->detail($image));
	}

	/**
	 * Jeden plik na żądanie: przeglądarka wysyła kolejno, więc błąd jednego pliku nie przerywa pozostałych.
	 */
	public function store(Request $request): JsonResponse
	{
		abort_unless($this->policy->canUpload(), 403, 'Nie masz uprawnień do przesyłania plików.');
		[$id, $error, $duplicate] = Media::upload('file') + [2 => false];

		if ($error) {
			return response()->json(['error' => $error], 422);
		}

		$folder = (int) $request->input('folder');

		if (! $duplicate && $folder && term_exists($folder, MediaLibrary::FOLDER)) {
			wp_set_object_terms($id, [$folder], MediaLibrary::FOLDER);
		}

		return response()->json(['id' => $id, 'duplicate' => $duplicate]);
	}

	public function update(Request $request, int $image): JsonResponse
	{
		$this->findEditable($image);
		$result = MediaAttributes::validate($request->all());
		$errors = $result['errors'];

		if (! $errors && $result['data']['folder'] && ! term_exists($result['data']['folder'], MediaLibrary::FOLDER)) {
			$errors['folder'] = 'Ten folder już nie istnieje.';
		}

		if ($errors) {
			return response()->json(['error' => 'Nie zapisano zmian — popraw zaznaczone pola.', 'errors' => $errors], 422);
		}

		MediaLibrary::update($image, $result['data']);

		return response()->json($this->detail($image) + ['tags_all' => MediaLibrary::tags(), 'folders' => MediaLibrary::tree(MediaLibrary::folders())]);
	}

	/**
	 * Używana grafika trafia do archiwum, nieużywana znika razem z plikiem (MediaLibrary::remove).
	 */
	public function destroy(int $image): JsonResponse
	{
		$this->findEditable($image);
		$result = MediaLibrary::remove($image);
		$summary = MediaUsage::summary($result['usage']);

		return response()->json([
			'result' => $result['result'],
			'message' => $result['result'] === 'archived'
				? 'Grafika trafiła do archiwum' . ($summary !== '' ? ' — ' . $summary : '') . '. Istniejące oferty się nie zmieniają.'
				: 'Grafika została usunięta z biblioteki.',
		]);
	}

	public function restore(int $image): JsonResponse
	{
		$this->findEditable($image);
		MediaLibrary::restore($image);

		return response()->json($this->detail($image));
	}

	public function storeFolder(Request $request): JsonResponse
	{
		$name = $this->folderName($request);
		$parent = (int) $request->input('parent');

		if ($name === null || ($parent && ! term_exists($parent, MediaLibrary::FOLDER))) {
			return response()->json(['error' => 'Podaj nazwę folderu (do 100 znaków).'], 422);
		}

		[, $error] = MediaLibrary::createFolder($name, $parent);

		return $error ? response()->json(['error' => $error], 422) : response()->json(['folders' => MediaLibrary::tree(MediaLibrary::folders())]);
	}

	public function updateFolder(Request $request, int $folder): JsonResponse
	{
		$name = $this->folderName($request);
		abort_unless(term_exists($folder, MediaLibrary::FOLDER), 404);

		if ($name === null) {
			return response()->json(['error' => 'Podaj nazwę folderu (do 100 znaków).'], 422);
		}

		$error = MediaLibrary::renameFolder($folder, $name);

		return $error ? response()->json(['error' => $error], 422) : response()->json(['folders' => MediaLibrary::tree(MediaLibrary::folders())]);
	}

	/**
	 * Grafiki z usuniętego folderu zostają w bibliotece, tylko bez folderu.
	 */
	public function destroyFolder(int $folder): JsonResponse
	{
		abort_unless(term_exists($folder, MediaLibrary::FOLDER), 404);
		MediaLibrary::deleteFolder($folder);

		return response()->json(['folders' => MediaLibrary::tree(MediaLibrary::folders())]);
	}

	private function folderName(Request $request): ?string
	{
		$name = Input::line($request->input('name'));

		return $name !== null && mb_strlen($name) <= 100 ? $name : null;
	}

	private function find(int $image): void
	{
		abort_unless(Media::isImage($image), 404, 'Nie ma takiej grafiki.');
	}

	private function findEditable(int $image): void
	{
		$this->find($image);
		abort_unless($this->policy->canEdit($image), 403, 'Możesz zmieniać tylko własne grafiki.');
	}

	private function detail(int $image): array
	{
		$large = Media::image($image, 'large');
		$meta = wp_get_attachment_metadata($image);
		[$width, $height] = Media::size($image);
		$usage = MediaUsage::forAttachment($image);

		return MediaLibrary::attributes($image) + [
			'url' => $large['url'] ?? null,
			'width' => $width,
			'height' => $height,
			'mime' => get_post_mime_type($image),
			'size' => isset($meta['filesize']) ? (int) $meta['filesize'] : null,
			'usage' => $usage,
			'used' => MediaUsage::inUse($usage),
			'summary' => MediaUsage::summary($usage),
			'can_edit' => $this->policy->canEdit($image),
			'urls' => [
				'update' => route('gst.library.update', $image),
				'destroy' => route('gst.library.destroy', $image),
				'restore' => route('gst.library.restore', $image),
				'show' => route('gst.library.show', $image),
			],
		];
	}
}
