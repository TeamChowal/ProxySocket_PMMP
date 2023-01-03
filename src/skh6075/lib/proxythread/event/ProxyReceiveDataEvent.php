<?php

declare(strict_types=1);

namespace skh6075\lib\proxythread\event;

use ArrayIterator;
use pocketmine\event\Event;

final class ProxyReceiveDataEvent extends Event{

	public function __construct(
		private readonly string $groupName,
		private readonly ArrayIterator $iterator
	){}

	public function getGroupName() : string{
		return $this->groupName;
	}

	public function getIterator(): ArrayIterator{
		return $this->iterator;
	}
}