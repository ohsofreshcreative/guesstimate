<?php

namespace App\Guesstimate\Contracts;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Czyszczenie HTML szablonów. Usuwa znaczniki, nigdy tekst: to treść prawna, więc nieznany element jest
 * rozpakowywany, a znika tylko to, co nie jest treścią (skrypty, style, osadzone obiekty).
 * Czysty PHP bez WordPressa. OfferContent::clean się nie nadaje: zamienia nagłówki na akapity.
 */
final class ContractHtml
{
	public const ALIGN = ['ct-center', 'ct-right', 'ct-justify'];

	/** Styl akapitu: numerowany paragraf (§) i drobny druk. */
	public const PARAGRAPH = ['ct-par', 'ct-small'];

	private const TAGS = ['p', 'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'strong', 'em', 'u', 'br', 'hr', 'span'];

	private const RENAME = ['b' => 'strong', 'i' => 'em', 'div' => 'p', 'h4' => 'h3', 'h5' => 'h3', 'h6' => 'h3'];

	private const REMOVE = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'head', 'title', 'meta', 'link', 'base', 'textarea', 'select', 'input', 'button'];

	private const INLINE = ['strong', 'em', 'u', 'span', 'br'];

	private const CLASSES = [
		'p' => [...self::PARAGRAPH, ...self::ALIGN],
		'h1' => self::ALIGN,
		'h2' => self::ALIGN,
		'h3' => self::ALIGN,
		'li' => self::ALIGN,
		'td' => self::ALIGN,
		'th' => self::ALIGN,
	];

	public static function clean(mixed $html): string
	{
		$html = (string) $html;

		if (trim($html) === '') {
			return '';
		}

		$document = new DOMDocument('1.0', 'UTF-8');
		$previous = libxml_use_internal_errors(true);
		$document->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$body = $document->getElementsByTagName('body')->item(0);

		if (! $body) {
			return '';
		}

		self::walk($body);
		self::wrapLooseText($body);

		$out = '';

		foreach ($body->childNodes as $child) {
			$out .= $document->saveHTML($child);
		}

		return $out;
	}

	/** Sam tekst, do porównań i sprawdzania, czy dokument nie jest pusty. */
	public static function text(string $html): string
	{
		$html = preg_replace('#<(br|/p|/h[1-3]|/li|/td|/th|/tr)\b[^>]*>#i', "$0\n", $html);

		return trim(str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
	}

	private static function walk(DOMNode $parent): void
	{
		foreach (iterator_to_array($parent->childNodes) as $node) {
			if ($node->nodeType === XML_TEXT_NODE) {
				continue;
			}

			if (! $node instanceof DOMElement) {
				$parent->removeChild($node);

				continue;
			}

			$tag = strtolower($node->tagName);

			if (in_array($tag, self::REMOVE, true)) {
				$parent->removeChild($node);

				continue;
			}

			self::walk($node);
			$tag = self::RENAME[$tag] ?? $tag;

			if (! in_array($tag, self::TAGS, true) || ($tag === 'span' && ! self::isVariable($node))) {
				self::unwrap($node);

				continue;
			}

			self::rebuild($node, $tag);
		}
	}

	private static function isVariable(DOMElement $node): bool
	{
		return preg_match(TemplateVariables::KEY, $node->getAttribute('data-var')) === 1;
	}

	private static function unwrap(DOMElement $node): void
	{
		while ($node->firstChild) {
			$node->parentNode->insertBefore($node->firstChild, $node);
		}

		$node->parentNode->removeChild($node);
	}

	/** Nowy element z dozwolonymi atrybutami: lista dozwolonych jest pewniejsza niż usuwanie niebezpiecznych. */
	private static function rebuild(DOMElement $node, string $tag): void
	{
		$clean = $node->ownerDocument->createElement($tag);

		foreach (self::attributes($node, $tag) as $name => $value) {
			$clean->setAttribute($name, $value);
		}

		while ($node->firstChild) {
			$clean->appendChild($node->firstChild);
		}

		$node->parentNode->replaceChild($clean, $node);
	}

	/**
	 * @return array<string, string>
	 */
	private static function attributes(DOMElement $node, string $tag): array
	{
		if ($tag === 'hr') {
			return ['class' => 'ct-break'];
		}

		if ($tag === 'span') {
			return ['class' => 'ct-var', 'data-var' => $node->getAttribute('data-var'), 'contenteditable' => 'false'];
		}

		$attributes = [];
		$classes = array_values(array_intersect(preg_split('/\s+/', $node->getAttribute('class')) ?: [], self::CLASSES[$tag] ?? []));

		if ($classes) {
			$attributes['class'] = implode(' ', $classes);
		}

		$numbers = ['ol' => ['start'], 'td' => ['colspan', 'rowspan'], 'th' => ['colspan', 'rowspan']];

		foreach ($numbers[$tag] ?? [] as $name) {
			$value = $node->getAttribute($name);

			if (ctype_digit($value) && (int) $value >= 1 && (int) $value <= 999) {
				$attributes[$name] = $value;
			}
		}

		return $attributes;
	}

	/** Luźny tekst na najwyższym poziomie trafia do akapitu, żeby dokument składał się z bloków. */
	private static function wrapLooseText(DOMNode $body): void
	{
		$group = null;

		foreach (iterator_to_array($body->childNodes) as $node) {
			$inline = $node->nodeType === XML_TEXT_NODE || ($node instanceof DOMElement && in_array($node->tagName, self::INLINE, true));

			if (! $inline) {
				$group = null;

				continue;
			}

			if ($group === null) {
				if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) === '') {
					$body->removeChild($node);

					continue;
				}

				$group = $body->ownerDocument->createElement('p');
				$body->insertBefore($group, $node);
			}

			$group->appendChild($node);
		}
	}
}
