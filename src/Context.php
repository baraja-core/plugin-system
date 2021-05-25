<?php

declare(strict_types=1);

namespace Baraja\Plugin;


final class Context
{
	private ?PluginLinkGenerator $linkGenerator = null;


	public function getLinkGenerator(): ?PluginLinkGenerator
	{
		return $this->linkGenerator;
	}


	public function setLinkGenerator(PluginLinkGenerator $linkGenerator): void
	{
		if ($this->linkGenerator !== null) {
			throw new \LogicException('Plugin generator "' . $this->linkGenerator::class. '" has been defined.');
		}
		$this->linkGenerator = $linkGenerator;
	}
}
