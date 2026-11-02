<?php

namespace App\Guesstimate\Media;

use App\Guesstimate\Auth\Capabilities;

/**
 * Uprawnienia do biblioteki grafik. Biblioteka jest wspólna: każdy, kto tworzy oferty, widzi i wybiera grafiki.
 * Pracownik zmienia i usuwa tylko własne pliki; manager i administrator — wszystkie oraz foldery.
 */
class MediaPolicy
{
	public function canView(): bool
	{
		return current_user_can(Capabilities::CREATE_OFFERS);
	}

	public function canUpload(): bool
	{
		return $this->canView() && current_user_can('upload_files');
	}

	public function canManage(): bool
	{
		return current_user_can(Capabilities::MANAGE_MEDIA);
	}

	public function canEdit(int $attachmentId): bool
	{
		return self::mayEdit($this->canView(), $this->canManage(), (int) get_post_field('post_author', $attachmentId), get_current_user_id());
	}

	public static function mayEdit(bool $canView, bool $canManage, int $authorId, int $userId): bool
	{
		return $canView && ($canManage || ($userId > 0 && $authorId === $userId));
	}
}
