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
	 *
	 * @return class-string|null
	 */
	public function getBaseEntity(): ?string;

	public function beforeRender(): void;

	/**
	 * Process all internal actions and render requested component.
	 */
	public function run(): void;

	public function afterRender(): void;

	public function getPriority(): int;

	/**
	 * Source: https://bootstrap-vue.org/docs/icons#icons
	 */
	public function getIcon(): ?string;

	/**
	 * @return array<int, string>
	 */
	public function getRoles(): array;

	/**
	 * @return array<int, string>
	 */
	public function getPrivileges(): array;

	/**
	 * @return array<string, string|null>|null
	 */
	public function getMenuItem(): ?array;

	/**
	 * @phpstan-return never-return
	 * @throws PluginRedirectException
	 */
	public function redirect(string $path): void;
}
