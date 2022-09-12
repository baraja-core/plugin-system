<?php

declare(strict_types=1);

namespace Baraja\Plugin\Component;


use Baraja\Plugin\Plugin;
use Nette\Http\Request;

class VueComponent implements PluginComponent
{
	private string $key;

	private string $name;

	private string $tab;

	private string $source;

	private int $position;

	/** @var array<string, string|int|float|bool|null> */
	private array $params;


	/**
	 * @param array{key: string, name: string, implements: class-string, componentClass: class-string, view: string, source: string, position?: int, tab?: string, params?: array<int|string, mixed>} $config
	 */
	public function __construct(array $config)
	{
		$this->key = $config['key'];
		$this->name = $config['name'];
		$this->tab = $config['tab'] ?? $config['key'];
		$this->source = $config['source'];
		$this->position = $config['position'] ?? 50;

		$parameters = [];
		foreach ($config['params'] ?? [] as $parameterName => $parameterValue) {
			if (is_int($parameterName) && is_string($parameterValue)) {
				$parameterName = $parameterValue;
				$parameterValue = null;
			}
			if (is_string($parameterName) === false) {
				throw new \InvalidArgumentException(sprintf(
					'Component "%s": Parameter "%s" must be a string, but "%s" given.',
					$this->key,
					$parameterName,
					get_debug_type($parameterName),
				));
			}
			if ($parameterValue !== null && is_scalar($parameterValue) === false) {
				throw new \InvalidArgumentException(sprintf(
					'Component "%s": Parameter "%s" value must be scalar, but type "%s" given.',
					$this->key,
					$parameterName,
					get_debug_type($parameterValue),
				));
			}
			$parameters[$parameterName] = $parameterValue;
		}
		$this->params = $parameters;
	}


	public function render(Request $request, ?Plugin $plugin = null): string
	{
		$params = [];
		$component = htmlspecialchars($this->name, ENT_QUOTES);
		$url = $request->getUrl();

		foreach ($this->params as $paramName => $paramDefaultValue) {
			$paramValue = $url->getQueryParameter($paramName);
			if ($paramValue === null) {
				if ($paramDefaultValue === '#REQUIRED#') {
					throw new \InvalidArgumentException(
						'Component "' . $this->key . '": Parameter "' . $paramName . '" is required.',
					);
				}
				$paramValue = $paramDefaultValue;
			}

			$params[] = $this->renderHtmlVueAttrType($paramValue)
				. $this->escapeHtmlAttr($paramName)
				. '="' . $this->escapeHtmlAttr($paramValue) . '"';
		}

		return '<' . $component . ($params !== [] ? ' ' . implode(' ', $params) : '') . '>'
			. '</' . $component . '>';
	}


	/**
	 * Real source path to javascript file.
	 */
	public function getSource(): string
	{
		return $this->source;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getKey(): string
	{
		return $this->key;
	}


	public function getTab(): string
	{
		return $this->tab;
	}


	public function getPosition(): int
	{
		return $this->position;
	}


	/**
	 * Escapes string for use inside HTML attribute value.
	 */
	private function escapeHtmlAttr(mixed $s, bool $double = true): string
	{
		if (is_bool($s)) {
			$s = $s ? 'true' : 'false';
		} elseif ($s === null) {
			$s = 'null';
		} elseif (is_scalar($s)) {
			$s = (string) $s;
		} else {
			throw new \InvalidArgumentException('Type "' . get_debug_type($s) . '" is not supported now.');
		}
		if (str_contains($s, '`') === true && strpbrk($s, ' <>"\'') === false) {
			$s .= ' '; // protection against innerHTML mXSS vulnerability nette/nette#1496
		}

		return htmlspecialchars($s, ENT_QUOTES, 'UTF-8', $double);
	}


	private function renderHtmlVueAttrType(mixed $value): string
	{
		return $value === null
			|| is_numeric($value)
			|| is_bool($value) ? ':' : '';
	}
}
