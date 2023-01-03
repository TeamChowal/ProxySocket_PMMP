<?php

declare(strict_types=1);

namespace skh6075\lib\proxythread\exception;

use Exception;

final class ProxyException extends Exception{
	public static function wrap(string $message): ProxyException{
		return new ProxyException($message);
	}
}