<?php

declare(strict_types=1);

namespace Baraja\Plugin;


final class PluginInfoEntity
{
	private string $service;

	private string $type;

	private string $name;

	private string $realName;

	private ?string $baseEntity;

	private string $label;

	private string $basePath;

	private int $priority;

	private ?string $icon;

	/** @var string[] */
	private array $roles;

	/** @var string[] */
	private array $privileges;

	/** @var mixed[]|null */
	private ?array $menuItem;


	/**
	 * @param string[] $roles
	 * @param string[] $privileges
	 * @param mixed[]|null $menuItem
	 */
	public function __construct(
		string $service,
		string $type,
		string $name,
		string $realName,
		?string $baseEntity,
		string $label,
		string $basePath,
		int $priority,
		?string $icon,
		array $roles,
		array $privileges,
		?array $menuItem
	) {
		$this->service = $service;
		$this->type = $type;
		$this->name = $name;
		$this->realName = $realName;
		$this->baseEntity = $baseEntity;
		$this->label = $label;
		$this->basePath = $basePath;
		$this->priority = $priority;
		$this->icon = $icon;
		$this->roles = $roles;
		$this->privileges = $privileges;
		$this->menuItem = $menuItem;
	}


	public function getService(): string
	{
		return $this->service;
	}


	public function getType(): string
	{
		return $this->type;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getSanitizedName(): string
	{
		return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $this->getName()));
	}


	public function getRealName(): string
	{
		return $this->realName;
	}


	public function getBaseEntity(): ?string
	{
		return $this->baseEntity;
	}


	public function getLabel(): string
	{
		return $this->label;
	}


	public function getBasePath(): string
	{
		return $this->basePath;
	}


	public function getPriority(): int
	{
		return $this->priority;
	}


	public function getIcon(): ?string
	{
		return $this->icon;
	}


	/**
	 * @return string[]
	 */
	public function getRoles(): array
	{
		return $this->roles;
	}


	/**
	 * @return string[]
	 */
	public function getPrivileges(): array
	{
		return $this->privileges;
	}


	/**
	 * @return mixed[]|null
	 */
	public function getMenuItem(): ?array
	{
		return $this->menuItem;
	}
}
