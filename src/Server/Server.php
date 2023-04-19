<?php declare(strict_types = 1);

namespace IPub\WebSockets\Server;

use Closure;
use Nette;
use Psr\Log;
use React;
use React\EventLoop;
use Throwable;

/**
 * WebSocket server
 *
 * @package        iPublikuj:WebSockets!
 * @subpackage     Server
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 *
 * @method onCreate(Server $server)
 * @method onStart(EventLoop\LoopInterface $loop, Server $server)
 * @method onStop(EventLoop\LoopInterface $loop, Server $server)
 */
final class Server
{

	/**
	 * Implement nette smart magic
	 */
	use Nette\SmartObject;

	public const VERSION = 'IPub/WebSockets/1.0.0';

	/** @var Closure */
	public $onCreate = [];

	/** @var Closure */
	public $onStart = [];

	/** @var Closure */
	public $onStop = [];

	/** @var Handlers */
	private $handlers;

	/** @var EventLoop\LoopInterface */
	private $loop;

	/** @var Configuration */
	private $configuration;

	/** @var Log\LoggerInterface|Log\NullLogger|null */
	private $logger;

	/**
	 * @param Handlers $handlers
	 * @param EventLoop\LoopInterface $loop
	 * @param Configuration $configuration
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		Handlers $handlers,
		EventLoop\LoopInterface $loop,
		Configuration $configuration,
		?Log\LoggerInterface $logger = null
	) {
		$this->loop = $loop;
		$this->configuration = $configuration;
		$this->handlers = $handlers;
		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * Run IO server
	 *
	 * @param React\Socket\SocketServer|null $socket
	 *
	 * @return void
	 */
	public function create($socket = null): void
	{
		$client = $this->configuration->getAddress() . ':' . $this->configuration->getPort();

		if ($socket === null) {
			$socket = new React\Socket\SocketServer($client, [], $this->loop);

			if ($this->configuration->isSslEnabled()) {
				$socket = new React\Socket\SecureServer($socket, $this->loop, $this->configuration->getSslConfiguration());
			}

			if ($this->configuration->getPort() === 80) {
				$client = '0.0.0.0:843';

			} else {
				$client = $this->configuration->getAddress() . ':8843';
			}
		}

		$socket->on('connection', function (React\Socket\ConnectionInterface $connection): void {
			$this->handlers->handleConnect($connection);
		});

		$socket->on('error', function (Throwable $ex): void {
			$this->logger->error('Could not establish connection: ' . $ex->getMessage());
		});

		$flashSocket = new React\Socket\SocketServer($client, [], $this->loop);

		$flashSocket->on('connection', function (React\Socket\ConnectionInterface $connection): void {
			$this->handlers->handleFlashConnect($connection);
		});

		$this->logger->debug('Starting IPub\WebSockets');
		$this->logger->debug(sprintf('Launching WebSockets WS Server on: %s:%s', $this->configuration->getAddress(), $this->configuration->getPort()));

		$this->onCreate($this);
	}

	public function run(): void
	{
		$this->onStart($this->loop, $this);

		$this->loop->run();
	}

	public function stop(): void
	{
		$this->onStop($this->loop, $this);

		$this->loop->stop();
	}

}
