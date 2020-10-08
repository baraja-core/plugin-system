<?php

declare(strict_types=1);

namespace Baraja\Plugin;


final class Context
{

	/** @var PluginLinkGenerator|null */
	private $linkGenerator;


	public function getLinkGenerator(): ?PluginLinkGenerator
	{
		return $this->linkGenerator;
	}


	public function setLinkGenerator(PluginLinkGenerator $linkGenerator): void
	{
		if ($this->linkGenerator !== null) {
			throw new \LogicException('Plugin generator "' . \get_class($this->linkGenerator) . '" has been defined.');
		}
		$this->linkGenerator = $linkGenerator;
	}
}
