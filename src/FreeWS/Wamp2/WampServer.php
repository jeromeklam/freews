<?php
namespace FreeWS\Wamp2;

use \Ratchet\MessageComponentInterface;
use \Ratchet\WebSocket\WsServerInterface;
use \Ratchet\ConnectionInterface;
use \Psr\Log\LoggerInterface;
use \Psr\Log\LoggerAwareInterface;
use \Psr\Log\LoggerAwareTrait;

/**
 * Wamp2 : https://wamp-proto.org/_static/gen/wamp_latest_ietf.html
 *
 * @author jeromeklam
 *
 */
class WampServer implements MessageComponentInterface, WsServerInterface, LoggerAwareInterface
{

    /**
     * Behaviour
     */
    use LoggerAwareTrait;

    /**
     * @var ServerProtocol
     */
    protected $wampProtocol;

    /**
     * App
     * @var WampServerInterface
     */
    protected $app = null;

    /**
     * This class just makes it 1 step easier to use Topic objects in WAMP
     * If you're looking at the source code, look in the __construct of this
     *  class and use that to make your application instead of using this
     */
    public function __construct(WampServerInterface $app, $p_logger)
    {
        echo '__construct' . PHP_EOL;
        $this->setLogger($p_logger);
        $this->app          = $app;
        $this->wampProtocol = new ServerProtocol(new TopicManager($app));
        // Logger
        $this->wampProtocol->setLogger($p_logger);
        $this->app->setLogger($p_logger);
    }

    /**
     * {@inheritdoc}
     */
    public function onOpen(ConnectionInterface $conn)
    {
        echo 'onOpen' . PHP_EOL;
        $this->logger->info('Wamp2.WampServer.onOpen.start');
        $this->wampProtocol->onOpen($conn);
        $this->logger->info('Wamp2.WampServer.onOpen.start');
    }

    /**
     * {@inheritdoc}
     */
    public function onMessage(ConnectionInterface $conn, $msg)
    {
        echo 'onMessage' . PHP_EOL;
        $this->logger->info('Wamp2.onMessage.onMessage.start');
        try {
            $this->wampProtocol->onMessage($conn, $msg);
        } catch (Exception $we) {
            $this->logger->error($we->getMessage());
            $conn->close(1007);
        }
        $this->logger->info('Wamp2.onMessage.onMessage.end');
    }

    /**
     * {@inheritdoc}
     */
    public function onClose(ConnectionInterface $conn)
    {
        echo 'onClose' . PHP_EOL;
        $this->logger->info('Wamp2.WampServer.onClose.start');
        $this->wampProtocol->onClose($conn);
        $this->logger->info('Wamp2.WampServer.onClose.end');
    }

    /**
     * {@inheritdoc}
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo 'onError' . PHP_EOL;
        $this->logger->info('Wamp2.WampServer.onError.start');
        $this->logger->error($e->getMessage());
        $this->wampProtocol->onError($conn, $e);
        $this->logger->info('Wamp2.WampServer.onError.end');
    }

    /**
     * {@inheritdoc}
     */
    public function getSubProtocols()
    {
        echo 'getSubProtocols' . PHP_EOL;
        $this->logger->info('Wamp2.WampServer.getSubProtocols');
        $subs = $this->wampProtocol->getSubProtocols();
        return $subs;
    }
}
