<?php

declare(strict_types=1);

namespace Baraja\Plugin\SimpleComponent;


final class Breadcrumb implements SimpleComponent
{
	/** @var string */
	private $label;

	/** @var string */
	private $href;


	public function __construct(string $label, ?string $href = null)
	{
		$this->label = $label;
		$this->href = $href ?? '#';
	}


	/**
	 * @return string[]
	 */
	public function toArray(): array
	{
		return [
			'label' => $this->label,
			'href' => $this->href,
		];
	}


	public function getLabel(): string
	{
		return $this->label;
	}


	public function getHref(): string
	{
		return $this->href;
	}
}
