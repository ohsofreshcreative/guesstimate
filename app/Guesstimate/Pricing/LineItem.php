<?php

namespace App\Guesstimate\Pricing;

/**
 * Pozycja godzinowa z widełkami [min, max], przypisana do jednego etapu.
 */
final class LineItem
{
	public const SOURCE_TEMPLATE = 'template';
	public const SOURCE_CONTENT = 'content';
	public const SOURCE_PAGE_EXTRA = 'page_extra';
	public const SOURCE_UX = 'ux';
	public const SOURCE_FEATURE = 'feature';
	public const SOURCE_WORK = 'work';
	public const SOURCE_STAGE = 'stage';

	public function __construct(
		public readonly string $source,
		public readonly string $label,
		public readonly string $stageKey,
		public readonly string $min,
		public readonly string $max,
	) {
	}
}
