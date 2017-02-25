<?php
/**
 * Wrapper.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:Ratchet!
 * @subpackage     Server
 * @since          1.0.0
 *
 * @date           25.02.17
 */

declare(strict_types = 1);

namespace IPub\Ratchet\Server;

use Nette;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\WebSocket;

use IPub;
use IPub\Ratchet\Application;
use IPub\Ratchet\Clients;

/**
 * Ratchet server application wrapper
 * Purpose of this class is to create better interface for connection objects
 *
 * @package        iPublikuj:Ratchet!
 * @subpackage     Server
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
final class Wrapper implements MessageComponentInterface, WebSocket\WsServerInterface
{
	/**
	 * Implement nette smart magic
	 */
	use Nette\SmartObject;

	/**
	 * @var Application\IApplication
	 */
	private $application;

	/**
	 * @var Clients\IStorage
	 */
	private $clientsStorage;

	/**
	 * @param Application\IApplication $application
	 * @param Clients\IStorage $clientsStorage
	 */
	public function __construct(
		Application\IApplication $application,
		Clients\IStorage $clientsStorage
	) {
		$this->application = $application;
		$this->clientsStorage = $clientsStorage;
	}

	/**
	 * {@inheritdoc}
	 */
	public function onOpen(ConnectionInterface $connection)
	{
		$storageId = $this->clientsStorage->getStorageId($connection);

		$this->clientsStorage->addClient($storageId, $connection);

		return $this->application->onOpen($this->getConnectionClient($connection));
	}

	/**
	 * {@inheritdoc}
	 */
	public function onClose(ConnectionInterface $connection)
	{
		$storageId = $this->clientsStorage->getStorageId($connection);

		$client = $this->getConnectionClient($connection);

		$this->clientsStorage->removeClient($storageId);

		return $this->application->onClose($client);
	}

	/**
	 * {@inheritdoc}
	 */
	public function onError(ConnectionInterface $connection, \Exception $e)
	{
		return $this->application->onError($this->getConnectionClient($connection), $e);
	}

	/**
	 * {@inheritdoc}
	 */
	public function onMessage(ConnectionInterface $from, $msg)
	{
		return $this->application->onMessage($this->getConnectionClient($from), $msg);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSubProtocols()
	{
		if ($this->application instanceof WebSocket\WsServerInterface) {
			return $this->application->getSubProtocols();

		} else {
			return [];
		}
	}

	/**
	 * @param ConnectionInterface $connection
	 *
	 * @return Clients\Client
	 */
	private function getConnectionClient(ConnectionInterface $connection)
	{
		$storageId = $this->clientsStorage->getStorageId($connection);

		return $this->clientsStorage->getClient($storageId);
	}
}
