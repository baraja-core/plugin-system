<?php

declare(strict_types=1);

namespace Baraja\Plugin;


use Baraja\Plugin\Exception\PluginRedirectException;
use Baraja\Plugin\Exception\PluginTerminateException;
use Baraja\Plugin\Exception\PluginUserErrorException;
use Baraja\Plugin\SimpleComponent\Breadcrumb;
use Baraja\Plugin\SimpleComponent\Button;
use Baraja\Plugin\SimpleComponent\ContextMenu;

abstract class BasePlugin implements Plugin
{

	/** @var string|null */
	private $title;

	/** @var string|null */
	private $subtitle;

	/** @var Button[] */
	private $buttons = [];

	/** @var Breadcrumb[] */
	private $breadcrumb = [];

	/** @var string|null */
	private $linkBack;

	/** @var bool */
	private $saveAll = false;

	/** @var ContextMenu[] */
	private $contextMenu = [];

	/** @var string|null */
	private $smartControlComponentName;

	/** @var mixed[] */
	private $smartControlComponentParams = [];

	/** @var Context */
	private $context;


	final public function run(): void
	{
	}


	public function beforeRender(): void
	{
	}


	public function afterRender(): void
	{
	}


	public function getPriority(): int
	{
		return 1;
	}


	public function getIcon(): ?string
	{
		return null;
	}


	public function __toString(): string
	{
		return \get_class($this);
	}


	public function getLabel(): string
	{
		return $this->getName();
	}


	public function getBaseEntity(): ?string
	{
		return null;
	}


	/**
	 * @return string[]
	 */
	public function getRoles(): array
	{
		return [];
	}


	/**
	 * @return string[]
	 */
	public function getPrivileges(): array
	{
		return [];
	}


	/**
	 * @return string[]
	 */
	public function getMenuItem(): ?array
	{
		return null;
	}


	public function redirect(string $path): void
	{
		throw new PluginRedirectException($path);
	}


	public function error(?string $message = null): void
	{
		throw new PluginUserErrorException($message ?? $this->getName() . ': Plugin error');
	}


	public function terminate(): void
	{
		throw new PluginTerminateException('');
	}


	/**
	 * Create internal link to specific plugin and view.
	 *
	 * @param mixed[] $params
	 * @return string
	 */
	final public function link(string $route = 'Homepage:default', array $params = []): string
	{
		if (($linkGenerator = $this->context->getLinkGenerator()) === null) {
			throw new \RuntimeException('Link generator failed: Service for "' . PluginLinkGenerator::class . '" does not registered. Did you install baraja-core/cms?');
		}

		return $linkGenerator->link($route, $params);
	}


	public function getTitle(): ?string
	{
		return $this->title;
	}


	public function setTitle(?string $title): void
	{
		$this->title = $title;
	}


	public function getSubtitle(): ?string
	{
		return $this->subtitle;
	}


	public function setSubtitle(?string $subtitle): void
	{
		$this->subtitle = $subtitle;
	}


	/**
	 * @return Button[]
	 */
	public function getButtons(): array
	{
		return $this->buttons;
	}


	public function addButton(Button $button): void
	{
		$this->buttons[] = $button;
	}


	/**
	 * @return Breadcrumb[]
	 */
	public function getBreadcrumb(): array
	{
		return $this->breadcrumb;
	}


	public function addBreadcrumb(Breadcrumb $breadcrumb): void
	{
		$this->breadcrumb[] = $breadcrumb;
	}


	public function getLinkBack(): ?string
	{
		return $this->linkBack;
	}


	public function setLinkBack(?string $linkBack): void
	{
		$this->linkBack = $linkBack ?: null;
	}


	public function isSaveAll(): bool
	{
		return $this->saveAll;
	}


	public function setSaveAll(bool $saveAll = true): void
	{
		$this->saveAll = $saveAll;
	}


	/**
	 * @return ContextMenu[]
	 */
	public function getContextMenu(): array
	{
		return $this->contextMenu;
	}


	public function addContextMenu(ContextMenu $contextMenu): void
	{
		$this->contextMenu[] = $contextMenu;
	}


	public function getSmartControlComponentName(): ?string
	{
		return $this->smartControlComponentName;
	}


	public function setSmartControlComponentName(?string $smartControlComponentName): void
	{
		$this->smartControlComponentName = $smartControlComponentName;
	}


	/**
	 * @return mixed[]
	 */
	public function getSmartControlComponentParams(): array
	{
		return $this->smartControlComponentParams;
	}


	/**
	 * @param mixed[] $params
	 */
	public function setSmartControlComponentParams(array $params): void
	{
		$this->smartControlComponentParams = $params;
	}


	final public function setContext(Context $context): void
	{
		$this->context = $context;
	}
}
