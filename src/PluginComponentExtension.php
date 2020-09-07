<?php

declare(strict_types=1);

namespace Baraja\Plugin;


use Baraja\Plugin\Component\PluginComponent;
use Baraja\Plugin\Component\VueComponent;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Utils\Strings;

class PluginComponentExtension extends CompilerExtension
{

	/**
	 * Compress full plugin configuration to simple array structure and save in DIC.
	 */
	public function beforeCompile(): void
	{
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
			$components[] = [
				'key' => $key,
				'name' => trim(trim($name) === '' ? $key : $name),
				'implements' => $implements,
				'componentClass' => $component['componentClass'] ?? VueComponent::class,
				'view' => $view,
				'source' => $source,
				'position' => (int) ($component['position'] ?? 1),
				'tab' => (string) ($component['tab'] ?? $key),
				'params' => $params,
			];
		}

		/** @var ServiceDefinition $pluginManager */
		$pluginManager = $this->getContainerBuilder()->getDefinitionByType(PluginManager::class);
		$pluginManager->addSetup('?->setPluginServices(array_keys($this->findByTag(\'baraja-plugin\')))', ['@self']);
		$pluginManager->addSetup('?->setComponents(?)', ['@self', $components]);
	}
}
