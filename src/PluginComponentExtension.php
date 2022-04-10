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
		$builder = $this->getContainerBuilder();
		self::defineBasicServices($builder);

		$pluginServices = $this->createPluginServices($builder);

		/** @var ServiceDefinition $pluginManager */
		$pluginManager = $builder->getDefinitionByType(PluginManager::class);

		if (PHP_SAPI === 'cli') {
			return;
		}

		/** @var array<string|int, array<string, mixed>> $config */
		$config = $this->getConfig();

		$components = [];
		foreach ($config as $key => $component) {
			if (\is_string($key) === false) {
				throw new \RuntimeException(sprintf('Component name must be string, but "%s" (%s) given.', $key, get_debug_type($key)));
			}
			if (isset($component['name'], $component['implements'], $component['view'], $component['source']) === false) {
				throw new \RuntimeException(
					sprintf('Component definition for component "%s" is invalid. ', $key)
					. 'Did you defined "name", "implements", "view" and "source"?',
				);
			}
			$name = $component['name'];
			if (is_string($name) === false) {
				throw new \RuntimeException(sprintf('Component "%s": Section "name" must be string, but "%s" given.', $key, get_debug_type($name)));
			}
			$implements = $component['implements'];
			if (is_string($implements) === false) {
				throw new \RuntimeException(sprintf('Component "%s": Section "implements" must be string, but "%s" given.', $key, get_debug_type($implements)));
			}
			if (\class_exists($implements) === false && \interface_exists($implements) === false) {
				throw new \RuntimeException(sprintf('Component "%s": Class or interface "%s" does not exist.', $key, $implements));
			}
			if (isset($component['componentClass']) === true) {
				$componentClass = $component['componentClass'];
				if (\is_string($componentClass) === false || \class_exists($componentClass) === false) {
					throw new \RuntimeException(sprintf('Component "%s": Class "%s" does not exist.', $key, print_r($componentClass, true)));
				}
				try {
					$componentClassRef = new \ReflectionClass($componentClass);
					if ($componentClassRef->implementsInterface(PluginComponent::class) === false) {
						throw new \RuntimeException(sprintf('Component "%s": Component class "%s" must implement interface "%s".', $key, $componentClass, PluginComponent::class));
					}
					if ($componentClassRef->isInstantiable() === false) {
						throw new \RuntimeException(
							sprintf('Component "%s": Component class "%s" must be instantiable.', $key, $componentClass)
							. "\n" . 'Did you implement it as class without abstract mode?'
							. "\n" . 'Hint: To solve this issue mark class as final with public constructor.',
						);
					}
				} catch (\ReflectionException $e) {
					throw new \RuntimeException(
						sprintf(
							'Component "%s": Component class "%s" is broken: %s',
							$key,
							$componentClass,
							$e->getMessage(),
						),
						500,
						$e,
					);
				}
			}
			$view = $component['view'];
			if (is_string($view) === false) {
				throw new \RuntimeException(sprintf('Component "%s": Section "view" must be string, but "%s" given.', $key, get_debug_type($view)));
			}
			$source = $component['source'];
			if (is_string($source) === false) {
				throw new \RuntimeException(sprintf('Component "%s": Section "source" must be string, but "%s" given.', $key, get_debug_type($view)));
			}
			if (\is_file($source) === false) {
				throw new \RuntimeException(sprintf('Component "%s": Source file does not exist, path "%s" given.', $key, $source));
			}
			$componentParameters = $component['params'] ?? [];
			assert(is_array($componentParameters));
			$params = [];
			foreach ($componentParameters as $parameter) {
				if (is_array($parameter) && count($parameter) === 1) {
					$parameterName = array_keys($parameter)[0] ?? throw new \RuntimeException('Broken parameter.');
					$parameterValue = array_values($parameter)[0] ?? null;
					if (str_starts_with($parameterName, '?')) {
						$parameterName = str_replace('?', '', $parameterName);
						if ($parameterValue !== null) { // case '?id = null'
							throw new \RuntimeException(sprintf(
								'Component "%s": Parameter default type mishmash: Parameter "%s" can not implement default value "%s" and be both nullable.',
								$key,
								$parameterName,
								get_debug_type($parameterValue),
							));
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
					throw new \RuntimeException(sprintf(
						'Component "%s": Parameter "%s" must be a string, but "%s" given.',
						$key,
						is_scalar($parameter) ? $parameter : get_debug_type($parameter),
						get_debug_type($view),
					));
				}
				$params[Strings::firstLower((string) $parameterName)] = $parameterValue;
			}
			$tab = $component['tab'] ?? $key;
			$position = $component['position'] ?? 1;
			$componentClass = $component['componentClass'] ?? VueComponent::class;
			assert(is_string($tab));
			assert(is_int($position));
			assert(is_string($componentClass));
			$components[] = (new ComponentDIDefinition(
				key: $key,
				name: trim(trim($name) === '' ? $key : $name),
				implements: $implements,
				componentClass: $componentClass,
				view: $view,
				source: $source,
				position: $position,
				tab: $tab,
				params: $params,
			))->toArray();
		}

		$pluginManager->addSetup('?->setPluginServices(?)', ['@self', $pluginServices]);
		$pluginManager->addSetup('?->addComponents(?)', ['@self', $components]);
	}


	/**
	 * @return array<int, string>
	 */
	private function createPluginServices(ContainerBuilder $builder): array
	{
		$rootDir = dirname(__DIR__, 4);
		$robot = new RobotLoader;
		$robot->addDirectory($rootDir);
		$robot->setTempDirectory($rootDir . '/temp/cache/baraja.pluginSystem');
		$robot->acceptFiles = ['*Plugin.php'];
		$robot->reportParseErrors(false);
		$robot->refresh();

		$return = [];
		foreach ($robot->getIndexedClasses() as $class => $path) {
			try {
				if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
					trigger_error(
						sprintf('Class "%s" was found, but it cannot be loaded by autoloading.', $class)
						. "\n" . 'More information: https://php.baraja.cz/autoloading-trid',
					);
					continue;
				}
			} catch (\Throwable $e) {
				if (preg_match('/^Interface "Composer\\\[^"]+" not found$/u', $e->getMessage())) {
					continue;
				}
				try {
					if (class_exists(Debugger::class)) {
						Debugger::log($e, ILogger::WARNING);
					}
				} catch (\Throwable) {
					// Silence is golden.
				}
				try {
					trigger_error(sprintf('Class "%s" is broken: %s', $class, $e->getMessage()));
				} catch (\Throwable) {
					// Silence is golden.
				}
				continue;
			}
			$rc = new \ReflectionClass($class);
			if ($rc->isInstantiable() && $rc->implementsInterface(Plugin::class)) {
				$plugin = $builder->addDefinition(sprintf('%s.%s', $this->prefix('plugin'), str_replace('\\', '.', $class)))
					->setFactory($class)
					->setType($class)
					->setAutowired($class)
					->addTag('baraja-plugin');

				$return[] = (string) $plugin->getName();
				if ($rc->hasMethod('setContext') === true) {
					$plugin->addSetup('?->setContext(?)', ['@self', '@' . Context::class]);
				} else {
					trigger_error(sprintf(
						'Possible bug: Plugin "%s" do not extends "%s". Please check your dependency tree.',
						$class,
						BasePlugin::class,
					));
				}
			}
		}

		return $return;
	}
}
