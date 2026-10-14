<?php

namespace App\Guesstimate\Document;

use App\Guesstimate\Support\Model;

/**
 * Tło strony dokumentu — wpis w bibliotece teł wskazujący załącznik z biblioteki mediów WordPressa.
 */
class Background extends Model
{
	public const FITS = ['cover' => 'Wypełnij (cover)', 'contain' => 'Dopasuj (contain)'];

	protected $table = 'gst_backgrounds';

	protected $casts = ['attachment_id' => 'integer', 'width' => 'integer', 'height' => 'integer'];

	/**
	 * Odchylenie od proporcji A4 poziomo większe niż 2% — ostrzeżenie (nie blokada).
	 */
	public function ratioWarning(): bool
	{
		return Media::ratioWarning($this->width, $this->height);
	}
}
