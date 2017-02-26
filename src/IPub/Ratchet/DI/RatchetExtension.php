<?php
/**
 * RatchetExtension.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:Ratchet!
 * @subpackage     DI
 * @since          1.0.0
 *
 * @date           14.02.17
 */

declare(strict_types = 1);

namespace IPub\Ratchet\DI;

use Nette;
use Nette\DI;
use Nette\Http;
use Nette\PhpGenerator as Code;

use Kdyby\Console;

use Ratchet\Session\Serialize\HandlerInterface;
use React;

use IPub;
use IPub\Ratchet;
use IPub\Ratchet\Application;
use IPub\Ratchet\Clients;
use IPub\Ratchet\Commands;
use IPub\Ratchet\Message;
use IPub\Ratchet\Router;
use IPub\Ratchet\Server;
use IPub\Ratchet\Session;
use IPub\Ratchet\Users;
use IPub\Ratchet\WAMP;
use Tracy\Debugger;

/**
 * Ratchet extension container
 *
 * @package        iPublikuj:Ratchet!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
final class RatchetExtension extends DI\CompilerExtension
{
	/**
	 * @var array
	 */
	private $defaults = [
		'clients' => [
			'storage' => [
				'driver' => '@clients.driver.memory',
				'ttl'    => 0,
			],
		],
		'server'  => [
			'httpHost' => 'localhost',
			'port'     => 8080,
			'address'  => '0.0.0.0',
			'type'     => 'message',    // message|wamp
		],
		'wamp'    => [
			'version' => 'v1',    // v1|v2
			'topics'  => [
				'storage' => [
					'driver' => '@wamp.topics.driver.memory',
					'ttl'    => 0,
				],
			],
		],
		'session' => FALSE,
		'routes'  => [],
		'mapping' => [],
	];

	/**
	 * {@inheritdoc}
	 */
	public function loadConfiguration()
	{
		parent::loadConfiguration();

		// Get container builder
		$builder = $this->getContainerBuilder();
		// Get extension configuration
		$configuration = $this->getConfig($this->defaults);

		/**
		 * CONTROLLERS
		 */

		$controllerFactory = $builder->addDefinition($this->prefix('controllers.factory'))
			->setClass(Application\Controller\IControllerFactory::class)
			->setFactory(Application\Controller\ControllerFactory::class);

		if ($configuration['mapping']) {
			$controllerFactory->addSetup('setMapping', [$configuration['mapping']]);
		}

		/**
		 * USERS
		 */

		$builder->addDefinition($this->prefix('users.repository'))
			->setClass(Users\Repository::class);

		/**
		 * CLIENTS
		 */

		$builder->addDefinition($this->prefix('clients.driver.memory'))
			->setClass(Clients\Drivers\InMemory::class);

		$storageDriver = $configuration['clients']['storage']['driver'] === '@clients.driver.memory' ?
			$builder->getDefinition($this->prefix('clients.driver.memory')) :
			$builder->getDefinition($configuration['clients']['storage']['driver']);

		$builder->addDefinition($this->prefix('clients.storage'))
			->setClass(Clients\Storage::class)
			->setArguments([
				'ttl' => $configuration['clients']['storage']['ttl'],
			])
			->addSetup('?->setStorageDriver(?)', ['@' . $this->prefix('clients.storage'), $storageDriver]);

		/**
		 * ROUTING
		 */

		// Http routes collector
		$builder->addDefinition($this->prefix('router'))
			->setClass(Router\IRouter::class)
			->setFactory(Router\RouteList::class);

		/**
		 * APPLICATION
		 */

		if ($configuration['server']['type'] === 'wamp' && $configuration['wamp']['version'] === 'v1') {
			$builder->addDefinition($this->prefix('wamp.v1.topics.driver.memory'))
				->setClass(WAMP\V1\Topics\Drivers\InMemory::class);

			$storageDriver = $configuration['wamp']['topics']['storage']['driver'] === '@wamp.topics.driver.memory' ?
				$builder->getDefinition($this->prefix('wamp.v1.topics.driver.memory')) :
				$builder->getDefinition($configuration['wamp']['topics']['storage']['driver']);

			$builder->addDefinition($this->prefix('wamp.v1.topics.storage'))
				->setClass(WAMP\V1\Topics\Storage::class)
				->setArguments([
					'ttl' => $configuration['wamp']['topics']['storage']['ttl'],
				])
				->addSetup('?->setStorageDriver(?)', ['@' . $this->prefix('wamp.v1.topics.storage'), $storageDriver]);

			$application = $builder->addDefinition($this->prefix('application.wamp.v1'))
				->setClass(WAMP\V1\Provider::class);

		} else {
			$application = $builder->addDefinition($this->prefix('application.message'))
				->setClass(Message\Provider::class);
		}

		/**
		 * SESSION
		 */

		if ($configuration['session']) {
			$builder->addDefinition($this->prefix('session.serializer'))
				->setClass(HandlerInterface::class)
				->setFactory(Session\SessionSerializerFactory::class . '::create');

			$application = $builder->addDefinition($this->prefix('session.provider'))
				->setClass(Session\Provider::class)
				->setArguments(['application' => $application]);
		}

		/**
		 * SERVER
		 */

		$application = $builder->addDefinition($this->prefix('server.wrapper'))
			->setClass(Server\Wrapper::class)
			->setArguments(['application' => $application]);

		$loop = $builder->addDefinition($this->prefix('server.loop'))
			->setClass(React\EventLoop\LoopInterface::class)
			->setFactory('React\EventLoop\Factory::create');

		$configuration = new Server\Configuration(
			$configuration['server']['httpHost'],
			$configuration['server']['port'],
			$configuration['server']['address']
		);

		$builder->addDefinition($this->prefix('server.printer'))
			->setClass(Server\OutputPrinter::class);

		$builder->addDefinition($this->prefix('server.server'))
			->setClass(Server\Server::class, [
				$application,
				$loop,
				$configuration,
			]);

		// Define all console commands
		$commands = [
			'server' => Commands\ServerCommand::class,
		];

		foreach ($commands as $name => $cmd) {
			$builder->addDefinition($this->prefix('commands' . lcfirst($name)))
				->setClass($cmd)
				->addTag(Console\DI\ConsoleExtension::TAG_COMMAND);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile()
	{
		parent::beforeCompile();

		// Get container builder
		$builder = $this->getContainerBuilder();
		// Get extension configuration
		$configuration = $this->getConfig($this->defaults);

		/**
		 * ROUTER CREATION
		 */

		// Get application router
		$router = $builder->getDefinition($this->prefix('router'));

		// Init collections
		$routersFactories = [];

		foreach ($builder->findByTag('ipub.ratchet.routes') as $routerService => $priority) {
			// Priority is not defined...
			if (is_bool($priority)) {
				// ...use default value
				$priority = 100;
			}

			$routersFactories[$priority][$routerService] = $routerService;
		}

		// Sort routes by priority
		if (!empty($routersFactories)) {
			krsort($routersFactories, SORT_NUMERIC);

			foreach ($routersFactories as $priority => $items) {
				ksort($items, SORT_STRING);
				$routersFactories[$priority] = $items;
			}

			// Process all routes services by priority...
			foreach ($routersFactories as $priority => $items) {
				// ...and by service name...
				foreach ($items as $routerService) {
					$factory = new DI\Statement(['@' . $routerService, 'createRouter']);

					$router->addSetup('offsetSet', [NULL, $factory]);
				}
			}
		}

		/**
		 * CONTROLLERS INJECTS
		 */

		$allControllers = [];

		foreach ($builder->findByType(Application\Controller\IController::class) as $def) {
			$allControllers[$def->getClass()] = $def;
		}

		foreach ($allControllers as $def) {
			$def->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT)
				->addTag('ipub.ratchet.controller', $def->getClass());
		}

		/**
		 * SESSION
		 */

		if ($configuration['session']) {
			// Sessions switcher
			$original = $builder->getDefinition($originalSessionServiceName = $builder->getByType(Http\Session::class) ?: 'session');
			$builder->removeDefinition($originalSessionServiceName);
			$builder->addDefinition($this->prefix('session.original'), $original)
				->setAutowired(FALSE);

			$builder->addDefinition($originalSessionServiceName)
				->setClass(Http\Session::class)
				->setFactory(Session\SwitchableSession::class, [$this->prefix('@session.original')]);
		}
	}

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(Nette\Configurator $config, string $extensionName = 'ratchet')
	{
		$config->onCompile[] = function (Nette\Configurator $config, DI\Compiler $compiler) use ($extensionName) {
			$compiler->addExtension($extensionName, new RatchetExtension());
		};
	}
}
