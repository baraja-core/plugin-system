<?php

declare(strict_types=1);

namespace Baraja\Plugin\SimpleComponent;


final class Button implements SimpleComponent
{
	public const
		VARIANT_PRIMARY = 'primary',
		VARIANT_SECONDARY = 'secondary',
		VARIANT_SUCCESS = 'success',
		VARIANT_DANGER = 'danger',
		VARIANT_WARNING = 'warning',
		VARIANT_INFO = 'info';

	public const
		ACTION_DIVIDER = 'divider',
		ACTION_LINK = 'link',
		ACTION_LINK_TARGET = 'linkTarget',
		ACTION_LINK_TAB = 'linkTab',
		ACTION_MODAL = 'modal',
		ACTION_METHOD = 'method';

	private string $variant;

	private string $label;

	private ?string $icon;

	private string $action;

	private string $target;


	public function __construct(string $variant, string $label, string $action, string $target, ?string $icon = null)
	{
		$this->variant = $variant;
		$this->label = $label;
		$this->icon = $icon;
		$this->action = $action;
		$this->target = $target;
	}


	/**
	 * @return mixed[]
	 */
	public function toArray(): array
	{
		return [
			'variant' => $this->variant,
			'label' => $this->label,
			'icon' => $this->icon,
			'action' => $this->action,
			'target' => $this->target,
		];
	}


	public function getVariant(): string
	{
		return $this->variant;
	}


	public function getLabel(): string
	{
		return $this->label;
	}


	public function getIcon(): ?string
	{
		return $this->icon;
	}


	public function getAction(): string
	{
		return $this->action;
	}


	public function getTarget(): string
	{
		return $this->target;
	}
}
