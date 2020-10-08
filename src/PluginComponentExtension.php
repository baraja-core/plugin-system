<?php

declare(strict_types=1);

namespace Baraja\Plugin;


use Baraja\Plugin\Component\ComponentDIDefinition;
use Baraja\Plugin\Component\PluginComponent;
use Baraja\Plugin\Component\VueComponent;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\Loaders\RobotLoader;
use Nette\Utils\Strings;

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
		$pluginManager = $this->getContainerBuilder()->getDefinitionByType(PluginManager::class);

		$builder->addDefinition(self::SERVICE_PREFIX . 'cmsPluginPanel')
			->setFactory(CmsPluginPanel::class);

		if (PHP_SAPI === 'cli') {
			return;
		}

		$components = [];
		foreach ($this->config ?? [] as $key => $component) {
			if (\is_string($key) === false) {
				throw new \RuntimeException('Component name must be string, but "' . $key . '" given.');
			}
			if (isset($component['name'], $component['implements'], $component['view'], $component['source']) === false) {
				throw new \RuntimeException('Component definition for component "' . $key . '" is invalid. Did you defined "name", "implements", "view" and "source"?');
			}
			if (is_string($name = $component['name']) === false) {
				throw new \RuntimeException('Component "' . $key . '": Section "name" must be string, but "' . \gettype($name) . '" given.');
			}
			if (is_string($implements = $component['implements']) === false) {
				throw new \RuntimeException('Component "' . $key . '": Section "implements" must be string, but "' . \gettype($implements) . '" given.');
			}
			if (\class_exists($implements) === false && \interface_exists($implements) === false) {
				throw new \RuntimeException('Component "' . $key . '": Class or interface "' . $implements . '" does not exist.');
			}
			if (isset($component['componentClass']) === true) {
				if (\is_string($componentClass = $component['componentClass']) === false || \class_exists($componentClass) === false) {
					throw new \RuntimeException('Component "' . $key . '": Class "' . $componentClass . '" does not exist.');
				}
				try {
					if (($componentClassRef = new \ReflectionClass($componentClass))->implementsInterface(PluginComponent::class) === false) {
						throw new \RuntimeException('Component "' . $key . '": Component class "' . $componentClass . '" must implement interface "' . PluginComponent::class . '".');
					}
					if ($componentClassRef->isInstantiable() === false) {
						throw new \RuntimeException(
							'Component "' . $key . '": Component class "' . $componentClass . '" must be instantiable.'
							. "\n" . 'Did you implement it as class without abstract mode?'
							. "\n" . 'Hint: To solve this issue mark class as final with public constructor.'
						);
					}
				} catch (\ReflectionException $e) {
					throw new \RuntimeException('Component "' . $key . '": Component class "' . $componentClass . '" is broken: ' . $e->getMessage(), $e->getCode(), $e);
				}
			}
			if (is_string($view = $component['view']) === false) {
				throw new \RuntimeException('Component "' . $key . '": Section "view" must be string, but "' . \gettype($view) . '" given.');
			}
			if (is_string($source = $component['source']) === false) {
				throw new \RuntimeException('Component "' . $key . '": Section "source" must be string, but "' . \gettype($view) . '" given.');
			}
			if (\is_file($source) === false) {
				throw new \RuntimeException('Component "' . $key . '": Source file does not exist, path "' . $source . '" given.');
			}
			$params = [];
			foreach ($component['params'] ?? [] as $parameter) {
				if (is_string($parameter) === false) {
					throw new \RuntimeException('Component "' . $key . '": Parameter "' . $parameter . '" must be string, but "' . \gettype($view) . '" given.');
				}
				$params[] = Strings::firstLower($parameter);
			}
			$components[] = (new ComponentDIDefinition(
				$key,
				trim(trim($name) === '' ? $key : $name),
				$implements,
				$component['componentClass'] ?? VueComponent::class,
				$view,
				$source,
				(int) ($component['position'] ?? 1),
				(string) ($component['tab'] ?? $key),
				$params
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
		foreach (array_unique(array_keys($robot->getIndexedClasses())) as $class) {
			if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
				throw new \RuntimeException('Class "' . $class . '" was found, but it cannot be loaded by autoloading.' . "\n" . 'More information: https://php.baraja.cz/autoloading-trid');
			}
			try {
				$rc = new \ReflectionClass($class);
			} catch (\ReflectionException $e) {
				throw new \RuntimeException('Service "' . $class . '" is broken: ' . $e->getMessage(), $e->getCode(), $e);
			}
			if ($rc->isInstantiable() && $rc->implementsInterface(Plugin::class)) {
				$plugin = $builder->addDefinition(self::SERVICE_PREFIX . 'plugin.' . str_replace('\\', '.', $class))
					->setFactory($class)
					->addTag('baraja-plugin');

				$return[] = $plugin->getName();

				if ($rc->hasMethod('setContext') === true) {
					$plugin->addSetup('?->setContext(?)', ['@self', '@' . Context::class]);
				} else {
					user_error('Possible bug: Plugin "' . $class . '" do not extends "' . BasePlugin::class . '". Please check your dependency tree.');
				}
			}
		}

		return $return;
	}
}
