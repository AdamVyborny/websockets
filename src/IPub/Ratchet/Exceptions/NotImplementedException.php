<?php
/**
 * NotImplementedException.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:Ratchet!
 * @subpackage     Exceptions
 * @since          1.0.0
 *
 * @date           21.02.17
 */

declare(strict_types = 1);

namespace IPub\Ratchet\Exceptions;

use Nette;

class NotImplementedException extends Nette\NotImplementedException implements IException
{
}
