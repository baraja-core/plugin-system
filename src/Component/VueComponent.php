<?php

declare(strict_types=1);

namespace Baraja\Plugin\Component;


use Baraja\Plugin\Plugin;
use Nette\Http\Request;

class VueComponent implements PluginComponent
{

	/** @var string */
	private $key;

	/** @var string */
	private $name;

	/** @var string */
	private $tab;

	/** @var string */
	private $source;

	/** @var string[] */
	private $params;

	/** @var int */
	private $position;


	/**
	 * @param mixed[] $config
	 */
	public function __construct(array $config)
	{
		$this->key = (string) $config['key'];
		$this->name = (string) $config['name'];
		$this->tab = (string) $config['tab'];
		$this->source = (string) $config['source'];
		$this->position = (int) $config['position'];

		$parameters = [];
		foreach ($config['params'] ?? [] as $parameter) {
			if (\is_string($parameter) === false) {
				throw new \RuntimeException('Component "' . $this->key . '": Parameter "' . $parameter . '" must be string, but "' . \gettype($parameter) . '" given.');
			}
			if (\in_array($parameter, $parameters, true) === true) {
				throw new \RuntimeException('Component "' . $this->key . '": Parameter "' . $parameter . '" already was defined.');
			}
			$parameters[] = $parameter;
		}
		$this->params = $parameters;
	}


	public function render(Request $request, ?Plugin $plugin = null): string
	{
		$params = [];
		$component = htmlspecialchars($this->name, ENT_QUOTES);
		$url = $request->getUrl();

		foreach ($this->params as $param) {
			if (($paramValue = $url->getQueryParameter($param)) === null) {
				throw new \RuntimeException('Component "' . $this->key . '": Parameter "' . $param . '" is undefined.');
			}

			$params[] = $this->escapeHtmlAttr($param) . '="' . $this->escapeHtmlAttr($paramValue) . '"';
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
	protected function escapeHtmlAttr(string $s, bool $double = true): string
	{
		if (strpos($s, '`') !== false && strpbrk($s, ' <>"\'') === false) {
			$s .= ' '; // protection against innerHTML mXSS vulnerability nette/nette#1496
		}

		return htmlspecialchars($s, ENT_QUOTES, 'UTF-8', $double);
	}
}
