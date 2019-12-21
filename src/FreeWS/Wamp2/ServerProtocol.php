<?php
namespace FreeWS\Wamp2;

use \Ratchet\MessageComponentInterface;
use \Ratchet\WebSocket\WsServerInterface;
use \Ratchet\ConnectionInterface;
use \Psr\Log\LoggerAwareInterface;
use \Psr\Log\LoggerAwareTrait;

/**
 * WebSocket Application Messaging Protocol
 *
 * @link http://wamp.ws/spec
 * @link https://github.com/oberstet/autobahn-js
 * 
 * 1	HELLO	         Tx	Rx	Tx	Tx	Rx	Tx
 * 2	WELCOME	         Rx	Tx	Rx	Rx	Tx	Rx
 * 3	ABORT	         Rx	TxRx	Rx	Rx	TxRx	Rx
 * 6	GOODBYE	TxRx	TxRx	TxRx	TxRx	TxRx	TxRx
 * 8	ERROR	Rx	Tx	Rx	Rx	TxRx	TxRx
 * 16	PUBLISH	Tx	Rx				
 * 17	PUBLISHED	Rx	Tx				
 * 32	SUBSCRIBE		Rx	Tx			
 * 33	SUBSCRIBED		Tx	Rx			
 * 34	UNSUBSCRIBE		Rx	Tx			
 * 35	UNSUBSCRIBED		Tx	Rx			
 * 36	EVENT		Tx	Rx			
 * 48	CALL				Tx	Rx	
 * 50	RESULT				Rx	Tx	
 * 64	REGISTER					Rx	Tx
 * 65	REGISTERED					Tx	Rx
 * 66	UNREGISTER					Rx	Tx
 * 67	UNREGISTERED					Tx	Rx
 * 68	INVOCATION					Tx	Rx
 * 70	YIELD
 */
class ServerProtocol implements MessageComponentInterface, WsServerInterface, LoggerAwareInterface
{
    const MSG_HELLO        = 1;
    const MSG_WELCOME      = 2;
    const MSG_ABORT        = 3;
    const MSG_GOODBYE      = 6;
    const MSG_ERROR        = 8;
    const MSG_PUBLISH      = 16;
    const MSG_PUBLISHED    = 17;
    const MSG_SUBSCRIBE    = 32;
    const MSG_SUBSCRIBED   = 33;
    const MSG_UNSUBSCRIBE  = 34;
    const MSG_UNSUBSCRIBED = 35;
    const MSG_EVENT        = 36;
    const MSG_CALL         = 48;
    const MSG_RESULT       = 50;
    const MSG_REGISTER     = 64;
    const MSG_REGISTERED   = 65;
    const MSG_UNREGISTER   = 66;
    const MSG_UNREGISTERED = 67;
    const MSG_INVOCATION   = 68;
    const MSG_YIELD        = 70;

    /**
     * Behaviour
     */
    use LoggerAwareTrait;
    
    /**
     * @var WampServerInterface
     */
    protected $_decorating;

    /**
     * @var \SplObjectStorage
     */
    protected $connections;

    /**
     * @param WampServerInterface $serverComponent An class to propagate calls through
     */
    public function __construct(WampServerInterface $serverComponent)
    {
        $this->_decorating = $serverComponent;
        $this->connections = new \SplObjectStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubProtocols() {
        if ($this->_decorating instanceof WsServerInterface) {
            $subs   = $this->_decorating->getSubProtocols();
            $subs[] = 'wamp.2.json';
            return $subs;
        }
        return ['wamp.2.json'];
    }

    /**
     * {@inheritdoc}
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $decor = new WampConnection($conn);
        $this->connections->attach($conn, $decor);
        $this->_decorating->onOpen($decor);
    }

    /**
     * {@inheritdoc}
     * @throws \Ratchet\Wamp\Exception
     * @throws \Ratchet\Wamp\JsonException
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $from = $this->connections[$from];
        if (null === ($json = @json_decode($msg, true))) {
            throw new JsonException;
        }
        if (!is_array($json) || $json !== array_values($json)) {
            throw new Exception("Invalid WAMP message format");
        }
        if (isset($json[1]) && !(is_string($json[1]) || is_numeric($json[1]))) {
            throw new Exception('Invalid Topic, must be a string');
        }
        switch ($json[0]) {
            /**
             * HELLO
             */
            case static::MSG_HELLO:
                $this->logger->info('HELLO');
                $from->WAMP->prefixes[$json[1]] = $json[2];
                break;
            /**
             * SUSCRIBE
             */
            case static::MSG_SUBSCRIBE:
                $this->logger->info('SUBSCRIBE');
                $this->logger->info($from->getUri($json[1]));
                $this->logger->info($json[3]);
                try {
                    $subscription = $this->_decorating->onSubscribe($from, $json[3]);
                    $data = array(self::MSG_SUBSCRIBED, $json[1], $subscription);
                    $from->send(json_encode($data));
                } catch (\Exception $ex) {
                    $uri  = $from->getUri($json[1]) . '.' . $ex->getCode();
                    $data = array(self::MSG_ERROR, self::MSG_SUBSCRIBED, $json[1], new \StdClass(), $uri);
                    $from->send(json_encode($data));
                }
                break;
            /**
             * UNSUBSCRIBE
             */
            case static::MSG_UNSUBSCRIBE:
                $this->logger->info('UNSUBSCRIBE');
                try {
                    $this->_decorating->onUnSubscribe($from, $from->getUri($json[1]));
                    $data = array(self::MSG_UNSUBSCRIBED, $json[1]);
                    $from->send(json_encode($data));
                } catch (\Exception $ex) {
                    $uri  = $from->getUri($json[1]) . '.' . $ex->getCode();
                    $data = array(self::MSG_ERROR, self::MSG_UNSUBSCRIBED, $json[1], new \StdClass(), $uri);
                    $from->send(json_encode($data));
                }
                break;
            /**
             * PUBLISH
             */
            case static::MSG_PUBLISH:
                $this->logger->info('PUBLISH');
                $exclude  = (array_key_exists(3, $json) ? $json[3] : null);
                if (!is_array($exclude)) {
                    if (true === (boolean)$exclude) {
                        $exclude = [$from->WAMP->sessionId];
                    } else {
                        $exclude = [];
                    }
                }
                $eligible = (array_key_exists(4, $json) ? $json[4] : []);
                try {
                    $publication = $this->_decorating->onPublish($from, $from->getUri($json[1]), $json[2], $exclude, $eligible);
                    $data = array(self::MSG_PUBLISHED, $json[1], $publication);
                    $from->send(json_encode($data));
                } catch (\Exception $ex) {
                    $uri  = $from->getUri($json[1]) . '.' . $ex->getCode();
                    $data = array(self::MSG_ERROR, self::MSG_PUBLISHED, $json[1], new \StdClass(), $uri);
                    $from->send(json_encode($data));
                }
                break;
            /**
             * 
             */
            case static::MSG_CALL:
                $this->logger->info('CALL');
                array_shift($json);
                $callID  = array_shift($json);
                $procURI = array_shift($json);
                if (count($json) == 1 && is_array($json[0])) {
                    $json = $json[0];
                }
                $this->_decorating->onCall($from, $callID, $from->getUri($procURI), $json);
                break;
            default:
                $this->logger->error(print_r($json, true));
                throw new Exception('Invalid WAMP message type');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onClose(ConnectionInterface $conn) {
        $decor = $this->connections[$conn];
        $this->connections->detach($conn);

        $this->_decorating->onClose($decor);
    }

    /**
     * {@inheritdoc}
     */
    public function onError(ConnectionInterface $conn, \Exception $e) {
        return $this->_decorating->onError($this->connections[$conn], $e);
    }
}
