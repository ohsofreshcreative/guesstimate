<?php

namespace App\Guesstimate\Http\Controllers;

use App\Guesstimate\Support\Flash;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

abstract class Controller
{
	/**
	 * Przekierowanie z komunikatem po udanym zapisie (wzorzec POST → redirect → GET).
	 */
	protected function redirectWith(string $url, string $message, string $type = 'success'): RedirectResponse
	{
		$type === 'success' ? Flash::success($message) : Flash::error($message);

		return redirect()->to($url);
	}

	/**
	 * Błędy walidacji: ponowne wyrenderowanie formularza (bez sesji Laravela) ze statusem 422.
	 *
	 * @param  array<string, string>|Validator  $errors
	 */
	protected function invalid(string $view, array $data, array|Validator $errors): Response
	{
		if ($errors instanceof Validator) {
			$errors = collect($errors->errors()->toArray())->map(fn ($messages) => $messages[0])->all();
		}

		return response(view($view, array_merge($data, [
			'errors' => $errors,
			'old' => request()->all(),
		]))->render(), 422);
	}
}
