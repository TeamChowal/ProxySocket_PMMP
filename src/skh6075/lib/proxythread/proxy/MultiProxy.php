<?php

declare(strict_types=1);

namespace skh6075\lib\proxythread\proxy;

use ArrayIterator;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use skh6075\lib\proxythread\event\ProxyReceiveDataEvent;
use skh6075\lib\proxythread\exception\ProxyException;
use skh6075\lib\proxythread\thread\ProxyThread;
use Volatile;

final class MultiProxy{
	use SingletonTrait;

	public const KEY_IDENTIFY = 'identify';
	public const KEY_DATA = 'data';

	public function __construct(
		Plugin $plugin
	){
		$this->initialize($plugin);
		self::setInstance($this);
	}

	/**
	 * @phpstan-var array<string, ProxyThread>
	 * @var ProxyThread[]
	 */
	private array $threads = [];

	/**
	 * @phpstan-var array<string, Volatile>
	 * @var Volatile[]
	 */
	private array $volatiles = [];

	private function initialize(Plugin $plugin) : void{
		$plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void{
			foreach($this->volatiles as $groupName => $volatile){
				while($volatile->count() > 0){
					$chunk = $volatile->shift();
					(new ProxyReceiveDataEvent($groupName, new ArrayIterator((array)$chunk)))->call();
				}
			}
		}), 5);
	}

	/** @param SendServerInfo[] $sendPorts */
	public function insert(string $groupName, int $receivePort, array $sendPorts): void{
		$thread = new ProxyThread($receivePort, $sendPorts);
		$this->volatiles[$groupName] = $thread->getReceiveQueue();
		$this->threads[$groupName] = $thread;
		$thread->start();
	}

	public function close(): void{
		foreach($this->threads as $key => $thread){
			$this->delete($key);
		}
	}

	public function delete(string $groupName): void{
		if(!isset($this->threads[$groupName])){
			throw ProxyException::wrap("No proxy found with key $groupName");
		}

		($proxy = $this->threads[$groupName])->shutdown();
		/**
		 * @noinspection PhpStatementHasEmptyBodyInspection
		 * Waiting for shutdown
		 */
		while($proxy->isRunning()){
		}
		unset($this->threads[$groupName], $this->volatiles[$groupName]);
	}

	public function select(string $groupName) : ?ProxyThread{
		return $this->threads[$groupName] ?? null;
	}

	/**
	 * @param mixed[] $data
	 * @phpstan-param  array{
	 *     identify: string,
	 *     data: mixed
	 * }              $data
	 *
	 * @throws ProxyException
	 */
	public function send(string $groupName, array $data) : void{
		$thread = $this->select($groupName);
		if($thread === null){
			throw ProxyException::wrap("No proxy found with key $groupName");
		}
		$thread->send($data);
	}
}