<?php
/**
 * Client.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:Ratchet!
 * @subpackage     Clients
 * @since          1.0.0
 *
 * @date           14.02.17
 */

declare(strict_types = 1);

namespace IPub\Ratchet\Clients;

use Nette;
use Nette\Security as NS;

use Ratchet\ConnectionInterface;

use IPub;
use IPub\Ratchet\Application\Responses;

/**
 * Single client connection (proxy class of ConnectionInterface)
 *
 * @package        iPublikuj:Ratchet!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @author         Vít Ledvinka, frosty22 <ledvinka.vit@gmail.com>
 */
class Client
{
	/**
	 * Implement nette smart magic
	 */
	use Nette\SmartObject;

	/**
	 * @var ConnectionInterface
	 */
	private $connection;

	/**
	 * @param ConnectionInterface $connection
	 */
	public function __construct(ConnectionInterface $connection)
	{
		$this->connection = $connection;
	}

	/**
	 * @return void
	 */
	public function close()
	{
		$this->connection->close();
	}

	/**
	 * @param Responses\IResponse $response
	 *
	 * @return void
	 */
	public function send(Responses\IResponse $response)
	{
		$this->connection->send($response->create());
	}

	/**
	 * @return NS\User|NULL
	 */
	public function getUser()
	{
		return isset($this->connection->user) ? $this->connection->user : NULL;
	}
}
