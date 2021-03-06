<?php
declare(strict_types=1);

namespace Callcenter;

use Evenement\EventEmitter;
use Psr\Log\NullLogger;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Psr\Log\LoggerInterface;

final class WebsocketHandler extends EventEmitter implements MessageComponentInterface
{
    /**
     * @var \SplObjectStorage
     */
    private $clients;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * WebsocketHandler constructor.
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        if ($logger == null) {
            $logger = new NullLogger();
        }

        $this->logger = $logger;
        $this->clients = new \SplObjectStorage;
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn) : void
    {
        $this->clients->attach($conn);
    }

    /**
     * @param ConnectionInterface $from
     * @param string $msg
     */
    public function onMessage(ConnectionInterface $from, $msg = '') : void
    {
        $this->logger->debug("WS: " . $msg);

        $parts = explode(":", $msg);
        $cmd = $parts[0];

        switch ($cmd) {
            case 'HELLO':
                $this->emit(
                    'websocket.hello', [
                        new CallcenterEvent(
                            'websocket.hello',
                            [
                                'wsconnection' => $from
                            ]
                        )
                    ]
                );
                break;
            case 'PAUSE':
                $agentid = $parts[1];

                $this->emit(
                    'websocket.pause', [
                        new CallcenterEvent(
                            'websocket.pause',
                            [
                                'wsconnection' => $from,
                                'agentid' => $agentid
                            ]
                        )
                    ]
                );
                break;
            case 'AVAIL':
                $agentid = $parts[1];

                $this->emit('websocket.avail', [
                        new CallcenterEvent(
                            'websocket.avail',
                            [
                                'wsconnection' => $from,
                                'agentid' => $agentid
                            ]
                        )
                    ]
                );
                break;
            default:
                $this->logger->error("Unknown msg: {$msg}");
        }
    }

    /**
     * @param string $msg
     */
    public function sendtoAll(string $msg) : void
    {
        /**
         * @var ConnectionInterface $client
         */
        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn) : void
    {
        $this->clients->detach($conn);
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $conn, \Exception $e) : void
    {
        $conn->close();
    }
}
