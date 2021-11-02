<?php

declare(strict_types=1);

namespace Baraja\Plugin;


use Baraja\Plugin\Component\PluginComponent;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\DI\Container;
use Nette\DI\Extensions\InjectExtension;
use Nette\Utils\Strings;

final class PluginManager
{
	/** @var array<int, mixed> */
	private array $components = [];

	/** @var array<int, mixed>|null */
	private ?array $pluginInfo = null;

	/** @var string[] */
	private array $baseEntityToPlugin;

	/** @var string[] */
	private array $baseEntitySimpleToPlugin;

	/** @var array<string, class-string> (name => type) */
	private array $pluginNameToType;

	private Cache $cache;


	public function __construct(
		private Container $container,
		Storage $storage
	) {
		$this->cache = new Cache($storage, 'baraja-plugin-manager');
	}


	/**
	 * Effective all Plugin services setter.
	 *
	 * @param string[] $pluginServices
	 * @internal use in DIC
	 */
	public function setPluginServices(array $pluginServices): void
	{
		$cache = $this->cache->load('plugin-info');
		if ($cache === null || ((string) ($cache['hash'] ?? '')) !== $this->getPluginServicesHash($pluginServices)) {
			[$plugins, $baseEntityToPlugin, $baseEntitySimpleToPlugin] = $this->processPluginInfo($pluginServices);

			$pluginNameToType = [];
			foreach ($plugins as $plugin) {
				$pluginNameToType[$plugin['name']] = $plugin['type'];
			}

			$this->cache->save('plugin-info', $cache = [
				'hash' => $this->getPluginServicesHash($pluginServices),
				'plugins' => $plugins,
				'baseEntityToPlugin' => $baseEntityToPlugin,
				'baseEntitySimpleToPlugin' => $baseEntitySimpleToPlugin,
				'nameToType' => $pluginNameToType,
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
	public function getComponents(Plugin|PluginInfoEntity|string $plugin, ?string $view): array
	{
		if (is_string($plugin)) {
			assert(class_exists($plugin));
			$pluginService = $this->getPluginByType($plugin);
		} elseif ($plugin instanceof PluginInfoEntity) {
			$pluginService = $this->getPluginByType($plugin->getType());
		} else {
			$pluginService = $plugin;
		}
		$implements = [];
		$implements[$pluginService::class] = true;
		$baseEntity = $pluginService->getBaseEntity();
		if ($baseEntity !== null) {
			$implements[$baseEntity] = true;
			try {
				$baseEntityExist = class_exists($baseEntity);
			} catch (\Throwable $e) {
				throw new \RuntimeException('Base entity "' . $baseEntity . '" is broken: ' . $e->getMessage(), $e->getCode(), $e);
			}
			if ($baseEntityExist === false) {
				throw new \InvalidArgumentException('Entity class "' . $baseEntity . '" does not exist or is not autoloadable.');
			}
			try {
				foreach ((new \ReflectionClass($baseEntity))->getInterfaces() as $interface) {
					$implements[$interface->getName()] = true;
				}
			} catch (\ReflectionException) {
			}
		}

		$return = [];
		foreach ($this->components as $component) {
			if (
				isset($implements[$component['implements']]) === true
				&& (
					$view === null
					|| $component['view'] === $view
				)
			) {
				/** @var PluginComponent $componentService */
				$componentService = new $component['componentClass']($component);

				foreach (InjectExtension::getInjectProperties($component['componentClass']) as $property => $service) {
					/** @var object $dependency @phpstan-ignore-next-line */
					$dependency = $this->container->getByType($service);
					$componentService->{$property} = $dependency;
				}

				$return[] = $componentService;
			}
		}

		usort($return, static fn(PluginComponent $a, PluginComponent $b): int => $a->getPosition() < $b->getPosition() ? 1 : -1);

		return $return;
	}


	/**
	 * @param mixed[] $components
	 * @internal use in DIC
	 */
	public function addComponents(array $components): void
	{
		$this->components = array_merge($this->components, $components);
	}


	/**
	 * @param mixed[] $component
	 * @internal use in DIC
	 */
	public function addComponent(array $component): void
	{
		$this->components[] = $component;
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
		$typeClass = $type::class;
		foreach ($this->pluginNameToType as $pluginName => $pluginType) {
			if ($pluginType === $typeClass) {
				return $pluginName;
			}
		}

		return Strings::firstUpper((string) preg_replace('/^.+\\\\(\w+)Plugin$/', '$1', $typeClass));
	}


	/**
	 * @param class-string $type
	 */
	public function getPluginByType(string $type): Plugin
	{
		if (is_subclass_of($type, Plugin::class) === false) {
			throw new \RuntimeException(sprintf(
				'Plugin must be instance of "%s", but "%s" given.',
				Plugin::class,
				$type,
			));
		}

		/** @var Plugin $service */
		$service = $this->container->getByType($type);

		return $service;
	}


	/**
	 * @return mixed[]
	 */
	public function getPluginInfo(): array
	{
		return $this->pluginInfo ?? [];
	}


	/**
	 * @return array<int, PluginInfoEntity>
	 */
	public function getPluginInfoEntities(): array
	{
		$return = [];
		foreach ($this->getPluginInfo() as $item) {
			$return[] = new PluginInfoEntity(
				service: $item['service'],
				type: $item['type'],
				name: $item['name'],
				realName: $item['realName'],
				baseEntity: $item['baseEntity'],
				label: $item['label'],
				basePath: $item['basePath'],
				priority: $item['priority'],
				icon: $item['icon'],
				roles: $item['roles'],
				privileges: $item['privileges'],
				menuItem: $item['menuItem'],
			);
		}

		return $return;
	}


	/**
	 * @param array<int, string> $pluginServices
	 */
	private function getPluginServicesHash(array $pluginServices): string
	{
		return md5(implode('|', $pluginServices));
	}


	/**
	 * @param array<int, string> $pluginServices
	 * @return array{0: array<class-string<Plugin>, array{service: string, type: class-string<Plugin>, name: string, realName: string, baseEntity: string|null, label: string, basePath: string, priority: int, icon: string|null, roles: array<int, string>, privileges: array<int, string>, menuItem: array<string, string|null>|null}>, 1: array<class-string, class-string<Plugin>>, 2: array<string, class-string<Plugin>>}
	 */
	private function processPluginInfo(array $pluginServices): array
	{
		$plugins = [];
		$baseEntityToPlugin = [];
		$baseEntitySimpleToPlugin = [];

		foreach ($pluginServices as $pluginService) {
			/** @var Plugin $plugin */
			$plugin = $this->container->getService($pluginService);
			$type = $plugin::class;
			$ref = new \ReflectionClass($plugin);
			$roles = $plugin->getRoles();
			$privileges = $plugin->getPrivileges();
			$this->validateRoles($roles);
			$this->validatePrivileges($privileges);

			$baseEntity = $plugin->getBaseEntity();
			if ($baseEntity !== null) {
				$baseEntityToPlugin[$baseEntity] = $type;
				if (preg_match('/\\\\([^\\\\]+)$/', $baseEntity, $baseEntityParser)) {
					$route = (string) $baseEntityParser[1];
					if (isset($baseEntitySimpleToPlugin[$route]) === true) {
						throw new \RuntimeException(
							'Plugin compile error: Base entity "' . $route . '" already exist.' . "\n\n"
							. 'How to solve this issue: Plugin "' . $type . '" is not compatible with plugin "'
							. $baseEntitySimpleToPlugin[$route] . '".' . "\n\n"
							. 'One of the plugins should be refactored to make routing unambiguous.',
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
				'basePath' => \dirname((string) $ref->getFileName()),
				'priority' => $plugin->getPriority(),
				'icon' => $plugin->getIcon(),
				'roles' => $roles,
				'privileges' => $privileges,
				'menuItem' => $plugin->getMenuItem(),
			];
		}

		return [
			$plugins,
			$baseEntityToPlugin,
			$baseEntitySimpleToPlugin,
		];
	}


	/**
	 * @param mixed[] $roles
	 */
	private function validateRoles(array $roles): void
	{
		foreach ($roles as $role) {
			if (is_string($role) === false) {
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
			if (is_string($privilege) === false) {
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
