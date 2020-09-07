<?php

declare(strict_types=1);

namespace Baraja\Plugin;


use Baraja\Plugin\Component\PluginComponent;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\DI\Container;
use Nette\DI\Extensions\InjectExtension;
use Nette\Utils\Strings;

final class PluginManager
{

	/** @var mixed[] */
	private $components = [];

	/** @var mixed[]|null */
	private $pluginInfo;

	/** @var string[] */
	private $baseEntityToPlugin;

	/** @var string[] */
	private $baseEntitySimpleToPlugin;

	/** @var string[] (name => type) */
	private $pluginNameToType;

	/** @var Cache */
	private $cache;

	/** @var Container */
	private $container;


	public function __construct(Container $container, IStorage $storage)
	{
		$this->container = $container;
		$this->cache = new Cache($storage, 'baraja-plugin-manager');
	}


	/**
	 * Effective all Plugin services setter.
	 *
	 * @internal use in DIC
	 * @param string[] $pluginServices
	 */
	public function setPluginServices(array $pluginServices): void
	{
		$cache = $this->cache->load('plugin-info');

		if ($cache === null || ((string) ($cache['hash'] ?? '')) !== $this->getPluginServicesHash($pluginServices)) {
			$info = $this->processPluginInfo($pluginServices);
			$nameToType = [];

			foreach ($info['plugins'] as $plugin) {
				$nameToType[$plugin['name']] = $plugin['type'];
			}

			$this->cache->save('plugin-info', $cache = [
				'hash' => $this->getPluginServicesHash($pluginServices),
				'plugins' => $info['plugins'],
				'baseEntityToPlugin' => $info['baseEntityToPlugin'],
				'baseEntitySimpleToPlugin' => $info['baseEntitySimpleToPlugin'],
				'nameToType' => $nameToType,
			]);
		}

		$this->pluginInfo = $cache['plugins'];
		$this->baseEntityToPlugin = $cache['baseEntityToPlugin'];
		$this->baseEntitySimpleToPlugin = $cache['baseEntitySimpleToPlugin'];
		$this->pluginNameToType = $cache['nameToType'];
	}


	/**
	 * @param string|null $view (string => filter by specific view, null => return all components for plugin)
	 * @return PluginComponent[]
	 */
	public function getComponents(Plugin $plugin, ?string $view): array
	{
		$implements = [];
		$implements[\get_class($plugin)] = true;
		if (($baseEntity = $plugin->getBaseEntity()) !== null) {
			$implements[$baseEntity] = true;
			try {
				foreach ((new \ReflectionClass($baseEntity))->getInterfaces() as $interface) {
					$implements[$interface->getName()] = true;
				}
			} catch (\ReflectionException $e) {
			}
		}

		$return = [];
		foreach ($this->components as $component) {
			if (isset($implements[$component['implements']]) === true && ($view === null || $component['view'] === $view)) {
				/** @var PluginComponent $componentService */
				$componentService = new $component['componentClass']($component);

				foreach (InjectExtension::getInjectProperties($component['componentClass']) as $property => $service) {
					$componentService->{$property} = $this->container->getByType($service);
				}

				$return[] = $componentService;
			}
		}

		usort($return, static function (PluginComponent $a, PluginComponent $b): int {
			return $a->getPosition() < $b->getPosition() ? 1 : -1;
		});

		return $return;
	}


	/**
	 * @internal use in DIC
	 * @param mixed[] $components
	 */
	public function setComponents(array $components): void
	{
		$this->components = $components;
	}


	/**
	 * @return mixed[]
	 */
	public function getComponentsInfo(): array
	{
		return $this->components;
	}


	/**
	 * @return string[]
	 */
	public function getBaseEntityToPlugin(): array
	{
		return $this->baseEntityToPlugin;
	}


	/**
	 * @return string[]
	 */
	public function getBaseEntitySimpleToPlugin(): array
	{
		return $this->baseEntitySimpleToPlugin;
	}


	public function getPluginByName(string $name): Plugin
	{
		if (isset($this->pluginNameToType[$name]) === false) {
			throw new \RuntimeException('Plugin "' . $name . '" does not exist.', 404);
		}

		return $this->getPluginByType($this->pluginNameToType[$name]);
	}


	public function getPluginNameByType(Plugin $type): string
	{
		$typeClass = \get_class($type);
		foreach ($this->pluginNameToType as $pluginName => $pluginType) {
			if ($pluginType === $typeClass) {
				return $pluginName;
			}
		}

		return Strings::firstUpper((string) preg_replace('/^.+\\\\(\w+)Plugin$/', '$1', $typeClass));
	}


	public function getPluginByType(string $type): Plugin
	{
		$service = $this->container->getByType($type);

		if ($service instanceof Plugin) {
			return $service;
		}

		throw new \RuntimeException('Plugin must be instance of "' . Plugin::class . '", but "' . \get_class($service) . '" given.');
	}


	/**
	 * @return mixed[]
	 */
	public function getPluginInfo(): array
	{
		return $this->pluginInfo ?? [];
	}


	/**
	 * @param string[] $pluginServices
	 * @return string
	 */
	private function getPluginServicesHash(array $pluginServices): string
	{
		return md5(implode('|', $pluginServices));
	}


	/**
	 * @param string[] $pluginServices
	 * @return mixed[]
	 */
	private function processPluginInfo(array $pluginServices): array
	{
		$plugins = [];
		$baseEntityToPlugin = [];
		$baseEntitySimpleToPlugin = [];

		foreach ($pluginServices as $pluginService) {
			/** @var Plugin $plugin */
			$plugin = $this->container->getService($pluginService);
			$type = \get_class($plugin);
			try {
				$ref = new \ReflectionClass($plugin);
			} catch (\ReflectionException $e) {
				throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
			}

			$this->validateRoles($roles = $plugin->getRoles());
			$this->validatePrivileges($privileges = $plugin->getPrivileges());

			if (($baseEntity = $plugin->getBaseEntity()) !== null) {
				$baseEntityToPlugin[$baseEntity] = $type;
				if (preg_match('/\\\\([^\\\\]+)$/', $baseEntity, $baseEntityParser)) {
					if (isset($baseEntitySimpleToPlugin[$route = $baseEntityParser[1]]) === true) {
						throw new \RuntimeException(
							'Plugin compile error: Base entity "' . $route . '" already exist.' . "\n\n"
							. 'How to solve this issue: Plugin "' . $type . '" is not compatible with plugin "' . $baseEntitySimpleToPlugin[$route] . '".' . "\n\n"
							. 'One of the plugins should be refactored to make routing unambiguous.'
						);
					}
					$baseEntitySimpleToPlugin[$route] = $type;
				}
			}

			$plugins[$type] = [
				'service' => $pluginService,
				'type' => $type,
				'name' => Strings::firstUpper((string) preg_replace('/^.+\\\\(\w+)Plugin$/', '$1', $type)),
				'realName' => $plugin->getName(),
				'baseEntity' => $baseEntity,
				'label' => $plugin->getLabel(),
				'basePath' => \dirname($ref->getFileName()),
				'priority' => $plugin->getPriority(),
				'icon' => $plugin->getIcon(),
				'roles' => $roles,
				'privileges' => $privileges,
				'menuItem' => $plugin->getMenuItem(),
			];
		}

		return [
			'plugins' => $plugins,
			'baseEntityToPlugin' => $baseEntityToPlugin,
			'baseEntitySimpleToPlugin' => $baseEntitySimpleToPlugin,
		];
	}


	/**
	 * @param mixed[] $roles
	 */
	private function validateRoles(array $roles): void
	{
		foreach ($roles as $role) {
			if (\is_string($role) === false) {
				throw new \RuntimeException('Role must be a string, but type "' . \gettype($role) . '" given.');
			}
		}
	}


	/**
	 * @param mixed[] $privileges
	 */
	private function validatePrivileges(array $privileges): void
	{
		foreach ($privileges as $privilege) {
			if (\is_string($privilege) === false) {
				throw new \RuntimeException('Privilege must be a string, but type "' . \gettype($privilege) . '" given.');
			}
			if ($privilege === '') {
				throw new \RuntimeException('Privilege can not be empty string.');
			}
			if (preg_match('/^([a-z]+)([A-Z])([a-z]*)$/', $privilege, $parser)) {
				throw new \RuntimeException('Privilege "' . $privilege . '" does not match valid format (can not use camelCase). Did you mean "' . $parser[1] . '-' . strtolower($parser[2]) . $parser[3] . '"?');
			}
			if (strtolower($privilege) !== $privilege) {
				throw new \RuntimeException('Privilege "' . $privilege . '" must use lower characters only. Did you mean "' . strtolower($privilege) . '"?');
			}
		}
	}
}
