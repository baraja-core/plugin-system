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
		return '<span title="CMS components">'
			. '<img alt="CMS components" src="'
			. 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiA/Pjxzdmcgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5k'
			. 'Om5ldyAwIDAgNTAgNTA7IiB2ZXJzaW9uPSIxLjEiIHZpZXdCb3g9IjAgMCA1MCA1MCIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgeG1sbnM9'
			. 'Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayI+PGcgaWQ9'
			. 'IkxheWVyXzEiPjxwYXRoIGQ9Ik01LDMydjE3aDMwLjM0M2wyLjE2Ny0xLjY4NkM0Mi4yNyw0My42MTIsNDUsMzguMDMsNDUsMzJ2LTAu'
			. 'NjQ5bC04Ljg0MS0zLjkzMWMtMS41OS00LjIyNS01LjIyOS03LjI2Mi05LjU1LTguMTQ3ICAgQzI5LjI1NywxNy40NzIsMzEsMTQuNDM2'
			. 'LDMxLDExYzAtNS41MTQtNC40ODYtMTAtMTAtMTBTMTEsNS40ODYsMTEsMTFjMCwzLjQzMSwxLjczOSw2LjQ2NCw0LjM4LDguMjY1Qzku'
			. 'NDY0LDIwLjQ4MSw1LDI1LjcyOSw1LDMyICAgeiBNNDIuOTg4LDMyLjY0NWMtMC4xODcsNS4xNi0yLjYwNyw5LjkwMy02LjcwNywxMy4w'
			. 'OTJMMzUsNDYuNzMzbC0xLjI4MS0wLjk5N2MtNC4xLTMuMTg4LTYuNTIxLTcuOTMyLTYuNzA3LTEzLjA5Mmw3Ljk5LTMuNTUgICBMNDIu'
			. 'OTg4LDMyLjY0NXogTTEzLDExYzAtNC40MTEsMy41ODktOCw4LThzOCwzLjU4OSw4LDhzLTMuNTg5LDgtOCw4UzEzLDE1LjQxMSwxMywx'
			. 'MXogTTE4LDIxaDZjNC4zMTIsMCw4LjE3MywyLjUxNyw5Ljk2MSw2LjM2NyAgIEwyNSwzMS4zNTFWMzJjMCw1Ljg2NSwyLjU4OSwxMS4z'
			. 'LDcuMTA5LDE1SDdWMzJDNywyNS45MzUsMTEuOTM1LDIxLDE4LDIxeiIvPjxwb2x5Z29uIHBvaW50cz0iMzYsMzMgMzQsMzMgMzQsMzYg'
			. 'MzEsMzYgMzEsMzggMzQsMzggMzQsNDEgMzYsNDEgMzYsMzggMzksMzggMzksMzYgMzYsMzYgICIvPjwvZz48Zy8+PC9zdmc+" '
			. 'style="height:16px"></span>';
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
			if ($a['position'] === $b['position']) {
				return $a['implements'] === $b['implements'] ? 1 : -1;
			}

			return $a['position'] < $b['position'] ? 1 : -1;
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
				. '<td>' . ((int) $component['position']) . '</td>'
				. '<td>' . htmlspecialchars(implode(', ', $component['params'])) . '</td>'
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
			. '<tr><th style="width:24px"></th><th>Component</th><th>Name</th><th>Implements</th><th>View</th><th>Position</th><th>Params</th></tr>'
			. $components
			. '</table>'
			. '</div></div>';
	}
}
