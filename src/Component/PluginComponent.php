<?php

declare(strict_types=1);

namespace Baraja\Plugin\Component;


use Baraja\Plugin\Plugin;
use Nette\Http\Request;

interface PluginComponent
{
	/**
	 * @param mixed[] $config
	 */
	public function __construct(array $config);

	public function render(Request $request, ?Plugin $plugin = null): string;

	/**
	 * Return real source path to javascript file.
	 */
	public function getSource(): string;

	public function getName(): string;

	public function getKey(): string;

	/**
	 * Tab name.
	 */
	public function getTab(): string;

	public function getPosition(): int;
}
