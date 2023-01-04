<?php

declare(strict_types=1);

namespace skh6075\lib\proxythread\thread;

use InvalidArgumentException;
use pocketmine\thread\ThreadException;
use skh6075\lib\proxythread\proxy\MultiProxy;
use skh6075\lib\proxythread\proxy\SendServerInfo;
use Socket;
use Thread;
use Volatile;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_array;
use function json_decode;
use function json_encode;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_sendto;
use function socket_set_nonblock;

final class ProxyThread extends Thread{

	private bool $shutdown = false;

	private string $SendServersBuffer;
	private Volatile $sendQueue;
	private Volatile $receiveQueue;

	public function __construct(
		private readonly int $receivePort,
		array $sendServers //multi-proxy-socket
	){
		$this->SendServersBuffer = igbinary_serialize($sendServers);
		$this->sendQueue = new Volatile();
		$this->receiveQueue = new Volatile();
	}

	public function shutdown() : void{
		$this->shutdown = true;
	}

	public function getReceiveQueue() : Volatile{
		return $this->receiveQueue;
	}

	public function run(){
		$receiveSocket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_nonblock($receiveSocket);

		if($receiveSocket === false){
			throw new InvalidArgumentException("Failed to create socket");
		}

		if(socket_bind($receiveSocket, "0.0.0.0", $this->receivePort) === false){
			throw new InvalidArgumentException("Failed to bind port (bindPort: $this->receivePort)");
		}

		$sendSocket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if($sendSocket === false){
			throw new InvalidArgumentException("Failed to create socket");
		}
		/** @phpstan-var SendServerInfo[] $sendServers */
		$sendServers = igbinary_unserialize($this->SendServersBuffer);
		while(!$this->shutdown){
			$this->receiveData($receiveSocket);

			while($this->sendQueue->count() > 0){
				$chunk = $this->sendQueue->shift();
				if(!isset($chunk[MultiProxy::KEY_IDENTIFY], $chunk[MultiProxy::KEY_DATA])){
					continue;
				}

				foreach($sendServers as $serverInfo){
					socket_sendto($sendSocket, json_encode((array) $chunk), 65535, 0, $serverInfo->getAddress(), $serverInfo->getPort());
				}
			}
		}
		socket_close($sendSocket);
		socket_close($receiveSocket);
	}

	private function receiveData(Socket $receiveSocket) : void{
		$buffer = "";
		if(socket_recvfrom($receiveSocket, $buffer, 65535, 0, $source, $port) === false){
			$errno = socket_last_error($receiveSocket);
			if($errno === SOCKET_EWOULDBLOCK){
				return;
			}
			throw new ThreadException("Failed received");
		}

		if($buffer !== null && $buffer !== ""){
			$data = json_decode($buffer, true);
			if(!is_array($data) || !isset($data[MultiProxy::KEY_IDENTIFY], $data[MultiProxy::KEY_DATA])){
				return;
			}

			$this->receiveQueue[] = $data;
		}
	}

	/**
	 * @param mixed[] $data
	 * @phpstan-param  array{
	 *     identify: string,
	 *     data: mixed
	 * } $data
	 */
	public function send(array $data) : void{
		$this->sendQueue[] = $data;
	}
}