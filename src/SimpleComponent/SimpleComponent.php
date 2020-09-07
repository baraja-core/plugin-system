<?php

declare(strict_types=1);

namespace Baraja\Plugin\SimpleComponent;


interface SimpleComponent
{
	/**
	 * @return mixed[]
	 */
	public function toArray(): array;
}
