<?php
namespace FreeWS\Wamp2;

use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\WsServerInterface;

class TopicManager implements WsServerInterface, WampServerInterface {
    /**
     * @var WampServerInterface
     */
    protected $app;

    /**
     * @var array
     */
    protected $topicLookup = array();

    public function __construct(WampServerInterface $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $conn->WAMP->subscriptions = new \SplObjectStorage;
        $this->app->onOpen($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function onCall(ConnectionInterface $conn, $id, $topic, array $params)
    {
        $this->app->onCall($conn, $id, $this->getTopic($topic), $params);
    }

    /**
     * {@inheritdoc}
     */
    public function onSubscribe(ConnectionInterface $conn, $topic)
    {
        $topicObj = $this->getTopic($topic);
        if ($conn->WAMP->subscriptions->contains($topicObj)) {
            return $topicObj->getId();
        }
        $this->topicLookup[$topic]->add($conn);
        $conn->WAMP->subscriptions->attach($topicObj);
        if ($this->app->onSubscribe($conn, $topicObj)) {
            return $topicObj->getId();
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function onUnsubscribe(ConnectionInterface $conn, $topic)
    {
        $topicObj = $this->getTopic($topic);
        if (!$conn->WAMP->subscriptions->contains($topicObj)) {
            return;
        }
        $this->cleanTopic($topicObj, $conn);
        $this->app->onUnsubscribe($conn, $topicObj);
    }

    /**
     * {@inheritdoc}
     */
    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible)
    {
        $this->app->onPublish($conn, $this->getTopic($topic), $event, $exclude, $eligible);
        return mt_rand();
    }

    /**
     * {@inheritdoc}
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->app->onClose($conn);
        foreach ($this->topicLookup as $topic) {
            $this->cleanTopic($topic, $conn);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onError(ConnectionInterface $conn, \Exception $ex)
    {
        $this->app->onError($conn, $ex);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubProtocols()
    {
        if ($this->app instanceof WsServerInterface) {
            return $this->app->getSubProtocols();
        }
        return array();
    }

    /**
     * @param string uri
     * 
     * @return Topic
     */
    protected function getTopic($topic)
    {
        if (!array_key_exists($topic, $this->topicLookup)) {
            $this->topicLookup[$topic] = new Topic($topic);
        }
        return $this->topicLookup[$topic];
    }

    /**
     * 
     * @param Topic $topic
     * @param ConnectionInterface $conn
     */
    protected function cleanTopic(Topic $topic, ConnectionInterface $conn)
    {
        if ($conn->WAMP->subscriptions->contains($topic)) {
            $conn->WAMP->subscriptions->detach($topic);
        }
        if (array_key_exists($topic->getUri(), $this->topicLookup)) {
            $this->topicLookup[$topic->getUri()]->remove($conn);
            if (0 === $topic->count()) {
                unset($this->topicLookup[$topic->getUri()]);
            }
        }
    }
}
