<?php

declare(strict_types=1);

namespace skh6075\lib\proxythread\proxy;

final class SendServerInfo{

	public const ADDRESS_DEFAULT = '127.0.0.1';

	public function __construct(
		private readonly int $port,
		private readonly string $address = self::ADDRESS_DEFAULT
	){}

	public function getAddress() : string{
		return $this->address;
	}

	public function getPort() : int{
		return $this->port;
	}
}