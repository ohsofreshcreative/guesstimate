<?php

namespace App\Guesstimate\Document;

/**
 * Biblioteka teł do wyboru w edytorze dokumentu.
 */
final class DocumentDesigner
{
	/**
	 * @return list<array{id: int, name: string, thumb: string|null, fit: string, warning: bool}>
	 */
	public static function library(): array
	{
		return Background::orderBy('sort')->orderBy('name')->get()->map(fn (Background $b) => [
			'id' => $b->id,
			'name' => $b->name,
			'thumb' => Media::thumb($b->attachment_id),
			'fit' => $b->fit,
			'warning' => $b->ratioWarning(),
		])->values()->all();
	}
}
