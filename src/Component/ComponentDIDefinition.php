<?php

declare(strict_types=1);

namespace Baraja\Plugin\Component;


final class ComponentDIDefinition
{

	/** @var string */
	private $key;

	/** @var string */
	private $name;

	/** @var string */
	private $implements;

	/** @var string */
	private $componentClass;

	/** @var string */
	private $view;

	/** @var string */
	private $source;

	/** @var int */
	private $position;

	/** @var string */
	private $tab;

	/** @var string[] */
	private $params;


	/**
	 * @param string[] $params
	 */
	public function __construct(string $key, string $name, string $implements, string $componentClass, string $view, string $source, int $position = 1, ?string $tab = null, array $params = [])
	{
		$this->key = $key;
		$this->name = $name;
		$this->implements = $implements;
		$this->componentClass = $componentClass;
		$this->view = $view;
		$this->source = $source;
		$this->position = $position;
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
