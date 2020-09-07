<?php

declare(strict_types=1);

namespace Baraja\Plugin\SimpleComponent;


final class ContextMenu implements SimpleComponent
{
	public const ACTION_DIVIDER = 'divider';

	public const ACTION_LINK = 'link';

	public const ACTION_LINK_TAB = 'linkTab';

	public const ACTION_MODAL = 'modal';

	public const ACTION_METHOD = 'method';

	/** @var bool */
	private $active;

	/** @var bool */
	private $disabled;

	/** @var string */
	private $name;

	/** @var string */
	private $action;

	/** @var string|null */
	private $target;


	public function __construct(string $name, string $action, ?string $target = null, bool $active = false, bool $disabled = false)
	{
		$this->name = $name;
		$this->action = $action;
		$this->target = $target;
		$this->active = $active;
		$this->disabled = $disabled;
	}


	/**
	 * @return mixed[]
	 */
	public function toArray(): array
	{
		return [
			'name' => $this->name,
			'action' => $this->action,
			'target' => $this->target,
			'active' => $this->active,
			'disabled' => $this->disabled,
		];
	}


	public function isActive(): bool
	{
		return $this->active;
	}


	public function isDisabled(): bool
	{
		return $this->disabled;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getAction(): string
	{
		return $this->action;
	}


	public function getTarget(): ?string
	{
		return $this->target;
	}
}
