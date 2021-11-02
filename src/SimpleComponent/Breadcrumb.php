<?php

declare(strict_types=1);

namespace Baraja\Plugin\SimpleComponent;


final class Breadcrumb implements SimpleComponent
{
	public function __construct(
		private string $label,
		private ?string $href = null
	) {
	}


	/**
	 * @return array{label: string, href: string}
	 */
	public function toArray(): array
	{
		return [
			'label' => $this->getLabel(),
			'href' => $this->getHref(),
		];
	}


	public function getLabel(): string
	{
		return $this->label;
	}


	public function getHref(): string
	{
		return $this->href ?? '#';
	}
}
