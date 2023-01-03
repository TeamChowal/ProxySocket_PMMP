<?php

declare(strict_types=1);

namespace chowal\proxy;

use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\plugin\PluginBase;
use skh6075\lib\proxythread\event\ProxyReceiveDataEvent;
use skh6075\lib\proxythread\libProxyThread;
use skh6075\lib\proxythread\proxy\MultiProxy;
use skh6075\lib\proxythread\thread\ProxyThread;

final class Loader extends PluginBase{

	private readonly MultiProxy $proxy;

	protected function onEnable() : void{
		$this->proxy = libProxyThread::createMultiProxy($this);
		$this->proxy->insert('lobby', new ProxyThread($this->proxy, 2500, [2501]));
		$manager = $this->getServer()->getPluginManager();
		$manager->registerEvent(PlayerChatEvent::class, function(PlayerChatEvent $ev) : void{
			$this->proxy->select('lobby')->send([
				ProxyThread::KEY_IDENTIFY => "multi-message",
				ProxyThread::KEY_DATA => [
					'msg' => $ev->getFormat()
				]
			]);
		}, EventPriority::MONITOR, $this);
		$manager->registerEvent(ProxyReceiveDataEvent::class, function(ProxyReceiveDataEvent $ev) : void{
			$iterator = $ev->getIterator();
			if($iterator->offsetGet(ProxyThread::KEY_IDENTIFY) !== "multi-message"){
				return;
			}
			$data = $iterator->offsetGet(ProxyThread::KEY_DATA);
			$this->getServer()->broadcastPackets($this->getServer()->getOnlinePlayers(), [TextPacket::raw($data['msg'])]);
		}, EventPriority::MONITOR, $this);
	}

	protected function onDisable() : void{
		$this->proxy->close();
	}
}