<?php

declare(strict_types=1);

namespace Baraja\Plugin\Exception;


final class PluginRedirectException extends \RuntimeException
{
	public function __construct(
		private string $path,
	) {
		parent::__construct('Redirect to "' . $path . '"', 301);
	}


	public function getPath(): string
	{
		return $this->path;
	}
}
