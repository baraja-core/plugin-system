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
		if ($this->linkGenerator !== null && $this->linkGenerator !== $linkGenerator) {
			throw new \LogicException(sprintf('Plugin generator "%s" has been defined.', $this->linkGenerator::class));
		}
		$this->linkGenerator = $linkGenerator;
	}
}
