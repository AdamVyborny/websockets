<?php
/**
 * IWrapper.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:WebSockets!
 * @subpackage     Server
 * @since          1.0.0
 *
 * @date           04.03.17
 */

declare(strict_types = 1);

namespace IPub\WebSockets\Server;

use IPub;
use IPub\WebSockets\Entities;

interface IWrapper
{
	/**
	 * @param Entities\Clients\IClient $client
	 *
	 * @return void
	 */
	function handleOpen(Entities\Clients\IClient $client);

	/**
	 * @param Entities\Clients\IClient $client
	 * @param string $message
	 *
	 * @return void
	 */
	function handleMessage(Entities\Clients\IClient $client, string $message);

	/**
	 * @param Entities\Clients\IClient $client
	 *
	 * @return void
	 */
	function handleClose(Entities\Clients\IClient $client);

	/**
	 * @param Entities\Clients\IClient $client
	 * @param \Exception $ex
	 *
	 * @return void
	 */
	function handleError(Entities\Clients\IClient $client, \Exception $ex);
}
