<?php
namespace FreeWS\Wamp2;

use \Ratchet\ConnectionInterface;
use \FreeWS\Wamp2\WampServerInterface;
use \FreeWS\Wamp2\Topic;
use \Psr\Log\LoggerAwareInterface;
use \Psr\Log\LoggerAwareTrait;

/**
 *
 * @author jeromeklam
 *
 */
class Wamp2StorageListener implements WampServerInterface, LoggerAwareInterface
{

    /**
     * Behaviour
     */
    use LoggerAwareTrait;

    /**
     *
     * @var array
     */
    protected $subscribedTopics = array();

    /**
     *
     * @var \FreeWS\Wamp2\DataproviderInterface
     */
    protected $dataProvider = null;

    /**
     * Set dataprovider
     *
     * @param \FreeWS\Wamp2\DataproviderInterface $p_provider
     *
     * @return \FreeWS\Wamp2\Wamp2StorageListener
     */
    public function setDataProvider($p_provider)
    {
        $this->dataProvider = $p_provider;
        return $this;
    }

    /**
     *
     * @param ConnectionInterface $conn
     * @param Topic               $topic
     *
     * @return boolean
     */
    public function onSubscribe(ConnectionInterface $conn, $topic)
    {
        $this->logger->info('Wamp2.onSubscribe');
        $this->subscribedTopics[$topic->getUri()] = $topic;
        return true;
    }

    /**
     *
     * @param ConnectionInterface $conn
     * @param Topic               $topic
     *
     * @return boolean
     */
    public function onUnSubscribe(ConnectionInterface $conn, $topic)
    {
        $this->logger->info('Wamp2.onUnSubscribe');
        if (array_key_exists($topic->getUri(), $this->subscribedTopics)) {
            unset($this->subscribedTopics[$topic->getUri()]);
        }
        return true;
    }

    /**
     *
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $this->logger->info('Wamp2.onOpen');
    }

    /**
     *
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->logger->info('Wamp2.onClose');
    }

    /**
     *
     * @param ConnectionInterface $conn
     * @param mixed               $id
     * @param Topic               $topic
     * @param array               $params
     */
    public function onCall(ConnectionInterface $conn, $id, $topic, array $params)
    {
        $this->logger->info('Wamp2.onCall');
    }

    /**
     *
     * @param ConnectionInterface $conn
     * @param Topic               $topic
     * @param mixed               $event
     * @param array               $exclude
     * @param array               $eligible
     */
    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible)
    {
        $this->logger->info('Wamp2.onPublish');
    }

    /**
     *
     * @param ConnectionInterface $conn
     * @param \Exception          $ex
     */
    public function onError(ConnectionInterface $conn, \Exception $ex)
    {
        $this->logger->info('Wamp2.onError');
    }

    /**
     *
     * @param string $entry
     */
    public function onEvent($entry)
    {
        $this->logger->info('Wamp2.onEvent');
        $this->logger->debug(print_r($entry, true));
        try {
            $object = unserialize($entry);
            $event = \FreeFW\Constants::EVENT_NONE;
            if (array_key_exists('event', $object)) {
                $event = $object['event'];
            }
            if (array_key_exists($event, $this->subscribedTopics)) {
                $topic = $this->subscribedTopics[$event];
                $json  = new \stdClass();
                $json->event = $event;
                $json->type  = $object['type'];
                $json->id    = $object['id'];
                if ($this->dataProvider !== null) {
                    $json->datas = $this->dataProvider->getDataByEvent($event, $object['type'], $object['id']);
                }
                $topic->broadcast($json);
            } else {
                $this->logger->info('Wamp2.onEvent topic not attached');
            }
        } catch (\Exception $ex) {
            $this->logger->critical($ex->getMessage);
        }
    }
}
