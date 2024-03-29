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
	/** @var array<int, array{
	 *     key: string,
	 *     name: string,
	 *     implements: class-string,
	 *     componentClass: class-string,
	 *     view: string,
	 *     source: string,
	 *     position: int,
	 *     tab: string,
	 *     params: array<int|string, string|int|float|bool|null>
	 * }>
	 */
	private array $components = [];

	/** @var array<class-string<Plugin>, array{
	 *     service: string,
	 *     type: class-string<Plugin>,
	 *     name: string,
	 *     realName: string,
	 *     baseEntity: string|null,
	 *     label: string,
	 *     basePath: string,
	 *     priority: int,
	 *     icon: string|null,
	 *     roles: array<int, string>,
	 *     privileges: array<int, string>,
	 *     menuItem: array<string, string|null>|null
	 * }>|null
	 */
	private ?array $pluginInfo = null;

	/** @var array<string, string> */
	private array $baseEntityToPlugin;

	/** @var string[] */
	private array $baseEntitySimpleToPlugin;

	/** @var array<string, class-string> (name => type) */
	private array $pluginNameToType;

	private Cache $cache;


	public function __construct(
		private Container $container,
		Storage $storage,
	) {
		$this->cache = new Cache($storage, 'baraja-plugin-manager');
	}


	/**
	 * Effective all Plugin services setter.
	 *
	 * @param array<int, string> $pluginServices
	 * @internal use in DIC
	 */
	public function setPluginServices(array $pluginServices): void
	{
		/** @var array{hash: string, plugins: array<class-string<Plugin>, array{service: string, type: class-string<Plugin>, name: string, realName: string, baseEntity: string|null, label: string, basePath: string, priority: int, icon: string|null, roles: array<int, string>, privileges: array<int, string>, menuItem: array<string, string|null>|null}>, baseEntityToPlugin: array<class-string, class-string<Plugin>>, baseEntitySimpleToPlugin: array<string, class-string<Plugin>>, nameToType: array<string, class-string>}|null $cache */
		$cache = $this->cache->load('plugin-info');
		if ($cache === null || $cache['hash'] !== $this->getPluginServicesHash($pluginServices)) {
			[$plugins, $baseEntityToPlugin, $baseEntitySimpleToPlugin] = $this->processPluginInfo($pluginServices);

			$pluginNameToType = [];
			foreach ($plugins as $plugin) {
				$pluginNameToType[$plugin['name']] = $plugin['type'];
			}

			$cache = [
				'hash' => $this->getPluginServicesHash($pluginServices),
				'plugins' => $plugins,
				'baseEntityToPlugin' => $baseEntityToPlugin,
				'baseEntitySimpleToPlugin' => $baseEntitySimpleToPlugin,
				'nameToType' => $pluginNameToType,
			];
			$this->cache->save('plugin-info', $cache);
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
				throw new \RuntimeException(
					sprintf('Base entity "%s" is broken: %s', $baseEntity, $e->getMessage()),
					500,
					$e,
				);
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
	 * @param array<int, array{
	 *     key: string,
	 *     name: string,
	 *     implements: class-string,
	 *     componentClass: class-string,
	 *     view: string,
	 *     source: string,
	 *     position: int|null,
	 *     tab: string,
	 *     params: array<int|string, string|int|float|bool|null>|null
	 * }> $components
	 * @internal use in DIC
	 */
	public function addComponents(array $components): void
	{
		foreach ($components as $component) {
			$this->addComponent($component);
		}
	}


	/**
	 * @param array{
	 *     key: string,
	 *     name: string,
	 *     implements: class-string,
	 *     componentClass: class-string,
	 *     view: string,
	 *     source: string,
	 *     position: int|null,
	 *     tab: string,
	 *     params: array<int|string, string|int|float|bool|null>|null
	 * } $component
	 * @internal use in DIC
	 */
	public function addComponent(array $component): void
	{
		if (isset($component['position']) === false) {
			$component['position'] = 0;
		}
		if (isset($component['params']) === false) {
			$component['params'] = [];
		}
		$this->components[] = $component;
	}


	/**
	 * @return array<int, array{
	 *     key: string,
	 *     name: string,
	 *     implements: class-string,
	 *     componentClass: class-string,
	 *     view: string,
	 *     source: string,
	 *     position: int,
	 *     tab: string,
	 *     params: array<int|string, string|int|float|bool|null>
	 * }>
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
			throw new \RuntimeException(sprintf('Plugin "%s" does not exist.', $name), 404);
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
	 * @return array<class-string<Plugin>, array{
	 *     service: string,
	 *     type: class-string<Plugin>,
	 *     name: string,
	 *     realName: string,
	 *     baseEntity: string|null,
	 *     label: string,
	 *     basePath: string,
	 *     priority: int,
	 *     icon: string|null,
	 *     roles: array<int, string>,
	 *     privileges: array<int, string>,
	 *     menuItem: array<string, string|null>|null
	 * }>
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
	 * @return array{
	 *     0: array<class-string<Plugin>, array{
	 *         service: string,
	 *         type: class-string<Plugin>,
	 *         name: string,
	 *         realName: string,
	 *         baseEntity: string|null,
	 *         label: string,
	 *         basePath: string,
	 *         priority: int,
	 *         icon: string|null,
	 *         roles: array<int, string>,
	 *         privileges: array<int, string>,
	 *         menuItem: array<string, string|null>|null
	 *     }>,
	 *     1: array<class-string, class-string<Plugin>>,
	 *     2: array<string, class-string<Plugin>>
	 * }
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
						throw new \RuntimeException(sprintf(
							'Plugin compile error: Base entity "%s" already exist.' . "\n\n"
							. 'How to solve this issue: Plugin "%s" is not compatible with plugin "%s".' . "\n\n"
							. 'One of the plugins should be refactored to make routing unambiguous.',
							$route,
							$type,
							$baseEntitySimpleToPlugin[$route],
						));
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
				'basePath' => dirname((string) $ref->getFileName()),
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
				throw new \RuntimeException(sprintf('Role must be a string, but type "%s" given.', get_debug_type($role)));
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
				throw new \RuntimeException(sprintf('Privilege must be a string, but type "%s" given.', get_debug_type($privilege)));
			}
			if ($privilege === '') {
				throw new \RuntimeException('Privilege can not be empty string.');
			}
			if (preg_match('/^([a-z]+)([A-Z])([a-z]*)$/', $privilege, $parser)) {
				throw new \RuntimeException(sprintf(
					'Privilege "%s" does not match valid format (can not use camelCase). Did you mean "%s-%s"?',
					$privilege,
					$parser[1],
					strtolower($parser[2]) . $parser[3],
				));
			}
			if (strtolower($privilege) !== $privilege) {
				throw new \RuntimeException(sprintf('Privilege "%s" must use lower characters only. Did you mean "%s"?', $privilege, strtolower($privilege)));
			}
		}
	}
}
