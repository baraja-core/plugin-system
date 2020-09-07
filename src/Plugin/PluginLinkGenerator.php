<?php

declare(strict_types=1);

namespace Baraja\Plugin;


interface PluginLinkGenerator
{
	/**
	 * @param mixed[] $params
	 * @return string
	 */
	public function link(string $route, array $params): string;
}
