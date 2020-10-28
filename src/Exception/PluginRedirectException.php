<?php

declare(strict_types=1);

namespace Baraja\Plugin\Exception;


final class PluginRedirectException extends \RuntimeException
{
	private string $path;


	public function __construct(string $path)
	{
		$this->path = $path;
		parent::__construct('Redirect to "' . $path . '"', 301);
	}


	public function getPath(): string
	{
		return $this->path;
	}
}
