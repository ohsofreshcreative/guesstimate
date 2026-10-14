<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Offers\Version\SitemapNode;
use App\Guesstimate\Offers\Version\VersionTemplate;

/**
 * Kopiuje wersję oferty z całym drzewem danych (etapy, szablony, mapa strony, funkcjonalności, prace, koszty).
 * Kopia zachowuje stawki i parametry źródła — nie pobiera niczego z ustawień globalnych.
 * Wywoływać w transakcji.
 */
final class VersionCopier
{
	public static function copy(OfferVersion $source, int $offerId, int $versionNo, int $userId): OfferVersion
	{
		$source->loadMissing(['stages', 'templates', 'nodes', 'features', 'workItems', 'costs']);

		$attributes = collect($source->getAttributes())
			->except(['id', 'offer_id', 'version_no', 'locked_at', 'created_by', 'updated_by', 'created_at', 'updated_at', 'change_note'])
			->all();

		$copy = new OfferVersion;
		$copy->setRawAttributes($attributes);
		$copy->fill(['offer_id' => $offerId, 'version_no' => $versionNo, 'created_by' => $userId, 'updated_by' => $userId]);
		$copy->save();

		foreach (['stages', 'features', 'workItems', 'costs'] as $relation) {
			foreach ($source->{$relation} as $row) {
				$clone = $row->replicate();
				$clone->version_id = $copy->id;
				$clone->save();
			}
		}

		$templateIds = [];

		foreach ($source->templates as $template) {
			/** @var VersionTemplate $clone */
			$clone = $template->replicate();
			$clone->version_id = $copy->id;
			$clone->save();
			$templateIds[$template->id] = $clone->id;
		}

		$nodeIds = [];
		$pending = [];

		foreach ($source->nodes as $node) {
			/** @var SitemapNode $clone */
			$clone = $node->replicate();
			$clone->version_id = $copy->id;
			$clone->template_id = $node->template_id ? ($templateIds[$node->template_id] ?? null) : null;
			$clone->parent_id = null;
			$clone->save();
			$nodeIds[$node->id] = $clone->id;
			$pending[$clone->id] = $node->parent_id;
		}

		foreach ($pending as $cloneId => $parentId) {
			if ($parentId && isset($nodeIds[$parentId])) {
				SitemapNode::whereKey($cloneId)->update(['parent_id' => $nodeIds[$parentId]]);
			}
		}

		return $copy->fresh();
	}
}
