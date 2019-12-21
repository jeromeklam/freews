<?php
namespace FreeWS\Socket;

use Ratchet\ConnectionInterface;

/**
 * A topic/channel containing connections that have subscribed to it
 */
class Topic implements \IteratorAggregate, \Countable
{

    /**
     * Id
     * @var number
     */
    private $id = null;

    /**
     * Uri
     * @var string
     */
    private $uri = null;

    /**
     * Subscribers
     * @var SplObjectStorage
     */
    private $subscribers;

    /**
     * @param string $topicUri uri
     */
    public function __construct($topicUri)
    {
        $this->uri         = $topicUri;
        $this->id          = mt_rand();
        $this->subscribers = new \SplObjectStorage;
    }

    /**
     *
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     *
     * @return number
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->getId();
    }

    /**
     * Send a message to all the connections in this topic
     * @param string|array $msg Payload to publish
     * @return Topic The same Topic object to chain
     */
    public function broadcast($msg)
    {
        foreach ($topic->getSubscribers() as $client) {
            $client->send($msg);
        }
        return $this;
    }

    /**
     * @param  WampConnection $conn
     * @return boolean
     */
    public function has(ConnectionInterface $conn) {
        return $this->subscribers->contains($conn);
    }

    /**
     * @param WampConnection $conn
     * @return Topic
     */
    public function add(ConnectionInterface $conn) {
        $this->subscribers->attach($conn);

        return $this;
    }

    /**
     * @param WampConnection $conn
     * @return Topic
     */
    public function remove(ConnectionInterface $conn) {
        if ($this->subscribers->contains($conn)) {
            $this->subscribers->detach($conn);
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator() {
        return $this->subscribers;
    }

    /**
     * {@inheritdoc}
     */
    public function count() {
        return $this->subscribers->count();
    }
}
