<?php

declare(strict_types=1);

namespace Baraja\Plugin;


use Baraja\Plugin\Exception\PluginRedirectException;
use Baraja\Service;

interface Plugin extends Service
{
	/**
	 * Official plugin name.
	 */
	public function getName(): string;

	/**
	 * Name for menu and navigation.
	 */
	public function getLabel(): string;

	/**
	 * Base related entity for this plugin (for ex. Article, Product, ...).
	 */
	public function getBaseEntity(): ?string;

	public function beforeRender(): void;

	/**
	 * Process all internal actions and render requested component.
	 */
	public function run(): void;

	public function afterRender(): void;

	public function getPriority(): int;

	public function getIcon(): ?string;

	/**
	 * @return string[]
	 */
	public function getRoles(): array;

	/**
	 * @return string[]
	 */
	public function getPrivileges(): array;

	/**
	 * @return mixed[]|null
	 */
	public function getMenuItem(): ?array;

	/**
	 * @param string $path
	 * @throws PluginRedirectException
	 */
	public function redirect(string $path): void;
}