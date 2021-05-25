<?php

declare(strict_types=1);

namespace PHPSTORM_META;

exitPoint(\Baraja\Plugin\Plugin::redirect());
exitPoint(\Baraja\Plugin\BasePlugin::error());
exitPoint(\Baraja\Plugin\BasePlugin::redirect());
exitPoint(\Baraja\Plugin\BasePlugin::terminate());
