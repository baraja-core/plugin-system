<?php

declare(strict_types=1);

namespace Baraja\Plugin;


use Baraja\Plugin\Component\PluginComponent;
use Tracy\IBarPanel;

final class CmsPluginPanel implements IBarPanel
{

	/** @var PluginManager */
	private $pluginManager;

	/** @var string|null */
	private $plugin;

	/** @var string|null */
	private $view;

	/** @var Plugin|null */
	private $pluginService;

	/** @var true[] (componentName => true) */
	private $renderedComponents = [];


	public function __construct(PluginManager $pluginManager)
	{
		$this->pluginManager = $pluginManager;
	}


	/**
	 * @internal
	 */
	public function setPlugin(string $plugin): void
	{
		$this->plugin = $plugin;
	}


	/**
	 * @internal
	 */
	public function setView(string $view): void
	{
		$this->view = $view;
	}


	/**
	 * @internal
	 */
	public function setPluginService(Plugin $plugin): void
	{
		$this->pluginService = $plugin;
	}


	/**
	 * @internal
	 * @param PluginComponent[] $renderedComponents
	 */
	public function setRenderedComponents(array $renderedComponents): void
	{
		$return = [];
		foreach ($renderedComponents as $component) {
			$return[$component->getKey()] = true;
		}

		$this->renderedComponents = $return;
	}


	public function getTab(): string
	{
		return 'CMS' . ($this->plugin !== null && $this->view !== null ? '&nbsp;' . htmlspecialchars($this->plugin) . ':' . htmlspecialchars($this->view) : '');
	}


	public function getPanel(): string
	{
		$plugins = '';
		$pluginServiceType = $this->pluginService === null ? '' : \get_class($this->pluginService);
		foreach ($this->pluginManager->getPluginInfo() as $plugin) {
			$plugins .= '<tr' . ($pluginServiceType === $plugin['type'] ? ' style="background:#BDE678"' : '') . '>'
				. '<td style="text-align:center">' . ($pluginServiceType === $plugin['type'] ? '✓' : '') . '</td>'
				. '<td><span title="' . $plugin['basePath'] . '">' . htmlspecialchars($plugin['name']) . '</span></td>'
				. '<td>' . htmlspecialchars($plugin['realName']) . '</td>'
				. '<td>' . htmlspecialchars($plugin['label']) . '</td>'
				. '<td>' . htmlspecialchars($plugin['type']) . '</td>'
				. '</tr>';
		}

		$components = '';
		$componentList = $this->pluginManager->getComponentsInfo();
		usort($componentList, static function (array $a, array $b): int {
			if (($a['position'] ?? 50) === ($b['position'] ?? 50)) {
				return $a['implements'] === $b['implements'] ? 1 : -1;
			}

			return ($a['position'] ?? 50) < ($b['position'] ?? 50) ? 1 : -1;
		});
		foreach ($componentList as $component) {
			$matchPlugin = $pluginServiceType === $component['implements'];
			$matchView = $this->view === $component['view'];
			$partialMatching = ($matchPlugin === true || $matchView === true) && ($matchPlugin === true && $matchView === true) === false;
			$matchingSymbol = '';
			if (isset($this->renderedComponents[$component['key']]) === true) {
				$matchingSymbol = '✓';
			} elseif ($partialMatching === true) {
				$matchingSymbol = '~';
			}
			$components .= '<tr' . (isset($this->renderedComponents[$component['key']]) ? ' style="background:#BDE678"' : '') . '>'
				. '<td style="text-align:center">' . htmlspecialchars($matchingSymbol) . '</td>'
				. '<td>' . htmlspecialchars($component['key']) . '</td>'
				. '<td>' . htmlspecialchars($component['name']) . '</td>'
				. ($matchPlugin
					? '<td style="background:#BDE678">' . htmlspecialchars($component['implements']) . '</td>'
					: '<td>' . htmlspecialchars($component['implements']) . '</td>'
				)
				. ($matchView
					? '<td style="background:#BDE678">' . htmlspecialchars($component['view']) . '</td>'
					: '<td>' . htmlspecialchars($component['view']) . '</td>'
				)
				. '<td>' . htmlspecialchars((string) ($component['position'] ?? '???'), ENT_QUOTES) . '</td>'
				. '<td>' . htmlspecialchars(implode(', ', $component['params'] ?? [])) . '</td>'
				. '</tr>';
		}

		return '<h1>CMS components'
			. ($this->plugin !== null && $this->view !== null ? ' [' . htmlspecialchars($this->plugin) . ':' . htmlspecialchars($this->view) . ']' : '')
			. '</h1>'
			. '<div class="tracy-inner baraja-cms">'
			. '<div class="tracy-inner-container">'
			. '<table>'
			. '<tr><th style="width:24px"></th><th>Plugin</th><th>Name</th><th>Label</th><th>Type</th></tr>'
			. $plugins
			. '</table><br>'
			. '<table>'
			. '<tr><th style="width:24px"></th><th>Component</th><th>Call&nbsp;name</th><th>Implements</th><th>View</th><th>Position</th><th>Params</th></tr>'
			. $components
			. '</table>'
			. '</div></div>';
	}
}
