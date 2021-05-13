<?php

declare(strict_types=1);

namespace Baraja\Plugin;


use Baraja\Plugin\Component\ComponentDIDefinition;
use Baraja\Plugin\Component\PluginComponent;
use Baraja\Plugin\Component\VueComponent;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Loaders\RobotLoader;
use Nette\Utils\Strings;
use Tracy\Debugger;
use Tracy\ILogger;

class PluginComponentExtension extends CompilerExtension
{
	private const SERVICE_PREFIX = 'baraja.pluginSystem.';


	public static function defineBasicServices(ContainerBuilder $builder): void
	{
		static $defined = false;
		if ($defined === false) {
			$defined = true;

			$builder->addDefinition(self::SERVICE_PREFIX . 'context')
				->setFactory(Context::class);

			$builder->addDefinition(self::SERVICE_PREFIX . 'pluginManager')
				->setFactory(PluginManager::class);
		}
	}


	/**
	 * Compress full plugin configuration to simple array structure and save in DIC.
	 */
	public function beforeCompile(): void
	{
		self::defineBasicServices($builder = $this->getContainerBuilder());

		$pluginServices = $this->createPluginServices($builder);

		/** @var ServiceDefinition $pluginManager */
		$pluginManager = $builder->getDefinitionByType(PluginManager::class);

		$builder->addDefinition($this->prefix('cmsPluginPanel'))
			->setFactory(CmsPluginPanel::class);

		if (PHP_SAPI === 'cli') {
			return;
		}

		/** @var mixed[] $config */
		$config = $this->getConfig();

		$components = [];
		foreach ($config as $key => $component) {
			if (\is_string($key) === false) {
				throw new \RuntimeException('Component name must be string, but "' . $key . '" given.');
			}
			if (isset($component['name'], $component['implements'], $component['view'], $component['source']) === false) {
				throw new \RuntimeException(
					'Component definition for component "' . $key . '" is invalid. '
					. 'Did you defined "name", "implements", "view" and "source"?',
				);
			}
			$name = $component['name'];
			if (is_string($name) === false) {
				throw new \RuntimeException(
					'Component "' . $key . '": Section "name" must be string, '
					. 'but "' . get_debug_type($name) . '" given.',
				);
			}
			$implements = $component['implements'];
			if (is_string($implements) === false) {
				throw new \RuntimeException(
					'Component "' . $key . '": Section "implements" must be string, '
					. 'but "' . get_debug_type($implements) . '" given.',
				);
			}
			if (\class_exists($implements) === false && \interface_exists($implements) === false) {
				throw new \RuntimeException(
					'Component "' . $key . '": Class or interface "' . $implements . '" does not exist.',
				);
			}
			if (isset($component['componentClass']) === true) {
				$componentClass = $component['componentClass'];
				if (\is_string($componentClass) === false || \class_exists($componentClass) === false) {
					throw new \RuntimeException(
						'Component "' . $key . '": Class "' . $componentClass . '" does not exist.',
					);
				}
				try {
					$componentClassRef = new \ReflectionClass($componentClass);
					if ($componentClassRef->implementsInterface(PluginComponent::class) === false) {
						throw new \RuntimeException(
							'Component "' . $key . '": Component class "' . $componentClass . '" '
							. 'must implement interface "' . PluginComponent::class . '".',
						);
					}
					if ($componentClassRef->isInstantiable() === false) {
						throw new \RuntimeException(
							'Component "' . $key . '": Component class "' . $componentClass . '" must be instantiable.'
							. "\n" . 'Did you implement it as class without abstract mode?'
							. "\n" . 'Hint: To solve this issue mark class as final with public constructor.',
						);
					}
				} catch (\ReflectionException $e) {
					throw new \RuntimeException(
						'Component "' . $key . '": Component class "' . $componentClass . '" is broken: ' . $e->getMessage(),
						$e->getCode(),
						$e,
					);
				}
			}
			$view = $component['view'];
			if (is_string($view) === false) {
				throw new \RuntimeException(
					'Component "' . $key . '": Section "view" must be string, '
					. 'but "' . get_debug_type($view) . '" given.',
				);
			}
			$source = $component['source'];
			if (is_string($source) === false) {
				throw new \RuntimeException(
					'Component "' . $key . '": Section "source" must be string, '
					. 'but "' . get_debug_type($view) . '" given.',
				);
			}
			if (\is_file($source) === false) {
				throw new \RuntimeException(
					'Component "' . $key . '": Source file does not exist, '
					. 'path "' . $source . '" given.',
				);
			}
			$params = [];
			foreach ($component['params'] ?? [] as $parameter) {
				if (is_array($parameter) && count($parameter) === 1) {
					$parameterName = array_keys($parameter)[0] ?? throw new \RuntimeException('Broken parameter.');
					$parameterValue = array_values($parameter)[0] ?? null;
					if (str_starts_with($parameterName, '?')) {
						$parameterName = str_replace('?', '', $parameterName);
						if ($parameterValue !== null) { // case '?id = null'
							throw new \RuntimeException(
								'Component "' . $key . '": Parameter default type mishmash: '
								. 'Parameter "' . $parameterName . '" can not implement default value '
								. '"' . get_debug_type($parameterValue) . '" and be both nullable.',
							);
						}
					}
				} elseif (is_string($parameter)) {
					if (str_starts_with($parameter, '?')) {
						$parameterName = str_replace('?', '', $parameter);
						$parameterValue = null;
					} else {
						$parameterName = $parameter;
						$parameterValue = '#REQUIRED#';
					}
				} else {
					throw new \RuntimeException(
						'Component "' . $key . '": Parameter "'
						. (is_scalar($parameter) ? $parameter : get_debug_type($parameter))
						. '" must be a string, but "' . \get_debug_type($view) . '" given.',
					);
				}
				$params[Strings::firstLower((string) $parameterName)] = $parameterValue;
			}
			$components[] = (new ComponentDIDefinition(
				key: $key,
				name: trim(trim($name) === '' ? $key : $name),
				implements: $implements,
				componentClass: $component['componentClass'] ?? VueComponent::class,
				view: $view,
				source: $source,
				position: (int) ($component['position'] ?? 1),
				tab: (string) ($component['tab'] ?? $key),
				params: $params,
			))->toArray();
		}

		$pluginManager->addSetup('?->setPluginServices(?)', ['@self', $pluginServices]);
		$pluginManager->addSetup('?->addComponents(?)', ['@self', $components]);
	}


	/**
	 * @return string[]
	 */
	private function createPluginServices(ContainerBuilder $builder): array
	{
		$robot = new RobotLoader;
		$robot->addDirectory($rootDir = dirname(__DIR__, 4));
		$robot->setTempDirectory($rootDir . '/temp/cache/baraja.pluginSystem');
		$robot->acceptFiles = ['*Plugin.php'];
		$robot->reportParseErrors(false);
		$robot->refresh();

		$return = [];
		foreach ($robot->getIndexedClasses() as $class => $path) {
			try {
				if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
					trigger_error(
						'Class "' . $class . '" was found, but it cannot be loaded by autoloading.'
						. "\n" . 'More information: https://php.baraja.cz/autoloading-trid',
					);
					continue;
				}
			} catch (\Throwable $e) {
				Debugger::log($e, ILogger::WARNING);
				trigger_error('Class "' . $class . '" is broken: ' . $e->getMessage());
				continue;
			}
			try {
				$rc = new \ReflectionClass($class);
			} catch (\ReflectionException $e) {
				throw new \RuntimeException(
					'Service "' . $class . '" is broken: ' . $e->getMessage(),
					$e->getCode(),
					$e,
				);
			}
			if ($rc->isInstantiable() && $rc->implementsInterface(Plugin::class)) {
				$plugin = $builder->addDefinition($this->prefix('plugin') . '.' . str_replace('\\', '.', $class))
					->setFactory($class)
					->setType($class)
					->setAutowired($class)
					->addTag('baraja-plugin');

				$return[] = (string) $plugin->getName();
				if ($rc->hasMethod('setContext') === true) {
					$plugin->addSetup('?->setContext(?)', ['@self', '@' . Context::class]);
				} else {
					trigger_error(
						'Possible bug: Plugin "' . $class . '" do not extends "' . BasePlugin::class . '". '
						. 'Please check your dependency tree.',
					);
				}
			}
		}

		return $return;
	}
}
