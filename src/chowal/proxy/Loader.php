<?php

declare(strict_types=1);

namespace chowal\proxy;

use pocketmine\plugin\PluginBase;
use RuntimeException;
use skh6075\lib\proxythread\libProxyThread;
use skh6075\lib\proxythread\proxy\MultiProxy;
use skh6075\lib\proxythread\proxy\SendServerInfo;
use function is_int;
use function is_string;

final class Loader extends PluginBase{

	private const KEY_RECEIVE = 'receive';
	private const KEY_SEND = 'send';

	private readonly MultiProxy $proxy;

	protected function onEnable() : void{
		$this->saveDefaultConfig();
		$this->proxy = libProxyThread::createMultiProxy($this);
		foreach($this->getConfig()->getAll() as $groupName => $data){
			if(!isset($data[self::KEY_RECEIVE]) || !isset($data[self::KEY_SEND])){
				throw new RuntimeException("Missing required elements in $groupName. ('receive', 'send'");
			}
			$receive = $data[self::KEY_RECEIVE];
			if(!is_int($receive)){
				throw new RuntimeException("Port must be made of natural numbers. [$groupName/receive]");
			}
			if(!is_string($groupName)){
				$groupName = (string) $groupName;
			}
			$sendServers = [];
			foreach($data[self::KEY_SEND] as $index => $serverInfo){
				if(!isset($serverInfo['port'])){
					throw new RuntimeException("Missing required elements ('port') [$groupName/send/$index]");
				}
				$port = $serverInfo['port'];
				if(!is_int($port)){
					throw new RuntimeException("Port must be made of natural numbers. [$groupName/send/$index/port]");
				}
				$sendServers[] = new SendServerInfo($port, $serverInfo['ip'] ?? SendServerInfo::ADDRESS_DEFAULT);
			}
			$this->proxy->insert($groupName, $receive, $sendServers);
		}
	}

	protected function onDisable() : void{
		$this->proxy->close();
	}
}