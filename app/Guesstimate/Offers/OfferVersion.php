<?php

namespace App\Guesstimate\Offers;

use App\Guesstimate\Offers\Version\SitemapNode;
use App\Guesstimate\Offers\Version\VersionCost;
use App\Guesstimate\Offers\Version\VersionFeature;
use App\Guesstimate\Offers\Version\VersionStage;
use App\Guesstimate\Offers\Version\VersionTemplate;
use App\Guesstimate\Offers\Version\WorkItem;
use App\Guesstimate\Support\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferVersion extends Model
{
	/**
	 * Wszystkie rodzaje projektu (także wycofane — potrzebne do wyświetlania historycznych ofert).
	 */
	public const PROJECT_TYPES = [
		'website' => 'Strona internetowa',
		'catalog' => 'Katalog produktów',
		'woocommerce' => 'Sklep internetowy',
		'landing' => 'Landing page',
		'other' => 'Inny',
		'webapp' => 'Aplikacja webowa',
	];

	/**
	 * Rodzaje do wyboru w kreatorze — dokładnie w tej kolejności. „Aplikacja webowa” jest wycofana
	 * (zostaje tylko w ofertach, które już ją mają). Kolejności nie bierzemy z bazy: MySQL w kolumnach
	 * JSON sortuje klucze obiektów (po długości), więc zapisane ustawienia nie zachowują kolejności.
	 */
	public const SELECTABLE_TYPES = ['website', 'catalog', 'woocommerce', 'landing', 'other'];

	public const DEFAULT_TYPE = 'website';

	/** Rodzaj, dla którego liczbę dodatkowych jednostek wpisuje się w ofercie. */
	public const CUSTOM_UNITS_TYPE = 'other';

	public const MAX_CUSTOM_UNITS = 999;

	protected $table = 'gst_offer_versions';

	protected $casts = [
		'project_types' => 'array',
		'budget' => 'array',
		'content' => 'array',
		'document' => 'array',
		'settings_snapshot' => 'array',
		'result' => 'array',
		'rates_overridden' => 'boolean',
		'locked_at' => 'datetime',
		'rate_low' => 'integer',
		'rate_mid' => 'integer',
		'rate_high' => 'integer',
		'low_min' => 'integer',
		'low_max' => 'integer',
		'mid_min' => 'integer',
		'mid_max' => 'integer',
		'high_min' => 'integer',
		'high_max' => 'integer',
		'recurring_monthly' => 'integer',
		'recurring_yearly' => 'integer',
		'version_no' => 'integer',
		'wizard_step' => 'integer',
	];

	public function offer(): BelongsTo
	{
		return $this->belongsTo(Offer::class, 'offer_id');
	}

	public function stages(): HasMany
	{
		return $this->hasMany(VersionStage::class, 'version_id')->orderBy('sort');
	}

	public function templates(): HasMany
	{
		return $this->hasMany(VersionTemplate::class, 'version_id')->orderBy('id');
	}

	public function nodes(): HasMany
	{
		return $this->hasMany(SitemapNode::class, 'version_id')->orderBy('parent_id')->orderBy('position');
	}

	public function features(): HasMany
	{
		return $this->hasMany(VersionFeature::class, 'version_id')->orderBy('sort');
	}

	public function workItems(): HasMany
	{
		return $this->hasMany(WorkItem::class, 'version_id')->orderBy('sort');
	}

	public function costs(): HasMany
	{
		return $this->hasMany(VersionCost::class, 'version_id')->orderBy('sort');
	}

	/**
	 * Wersja liczona modelem budżetowym (silnik 2.x). Starsze wersje (hourly / percent) liczą się po staremu.
	 */
	public function isBudget(): bool
	{
		return $this->calc_model === BudgetPricing::MODEL;
	}

	public function projectTypeLabel(): string
	{
		return $this->budget['project_type_label'] ?? (self::PROJECT_TYPES[$this->project_type] ?? implode(', ', $this->projectTypeLabels()));
	}

	public function isLocked(): bool
	{
		return $this->locked_at !== null;
	}

	public function rates(): array
	{
		return ['low' => $this->rate_low, 'mid' => $this->rate_mid, 'high' => $this->rate_high];
	}

	/**
	 * @return list<string>
	 */
	public function projectTypeLabels(): array
	{
		return array_values(array_map(
			fn ($type) => self::PROJECT_TYPES[$type] ?? $type,
			$this->project_types ?? []
		));
	}
}
