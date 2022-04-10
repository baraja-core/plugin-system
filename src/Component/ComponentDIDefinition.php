<?php

declare(strict_types=1);

namespace Baraja\Plugin\Component;


final class ComponentDIDefinition
{
	private string $tab;

	/** @var string[] */
	private array $params;


	/**
	 * @param string[] $params
	 */
	public function __construct(
		private string $key,
		private string $name,
		private string $implements,
		private string $componentClass,
		private string $view,
		private string $source,
		private int $position = 1,
		?string $tab = null,
		array $params = [],
	) {
		$this->tab = $tab ?? $key;
		$this->params = $params;
	}


	/**
	 * @return mixed[]
	 */
	public function toArray(): array
	{
		return [
			'key' => $this->key,
			'name' => $this->name,
			'implements' => $this->implements,
			'componentClass' => $this->componentClass,
			'view' => $this->view,
			'source' => $this->source,
			'position' => $this->position,
			'tab' => $this->tab,
			'params' => $this->params,
		];
	}
}
