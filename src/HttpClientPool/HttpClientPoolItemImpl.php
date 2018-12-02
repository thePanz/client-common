<?php

namespace Http\Client\Common\HttpClientPool;

use Http\Client\Common\FlexibleHttpClient;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Psr\Http\Message\RequestInterface;
use Http\Client\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * {@inheritdoc}
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class HttpClientPoolItemImpl implements HttpClientPoolItem
{
    /**
     * @var int Number of request this client is currently sending
     */
    private $sendingRequestCount = 0;

    /**
     * @var \DateTime|null Time when this client has been disabled or null if enable
     */
    private $disabledAt;

    /**
     * Number of seconds until this client is enabled again after an error.
     *
     * null: never reenable this client.
     *
     * @var int|null
     */
    private $reenableAfter;

    /**
     * @var FlexibleHttpClient A http client responding to async and sync request
     */
    private $client;

    /**
     * @param HttpClient|HttpAsyncClient $client
     * @param null|int                   $reenableAfter Number of seconds until this client is enabled again after an error
     */
    public function __construct($client, $reenableAfter = null)
    {
        $this->client = new FlexibleHttpClient($client);
        $this->reenableAfter = $reenableAfter;
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($this->isDisabled()) {
            throw new Exception\RequestException('Cannot send the request as this client has been disabled', $request);
        }

        try {
            $this->incrementRequestCount();
            $response = $this->client->sendRequest($request);
            $this->decrementRequestCount();
        } catch (Exception $e) {
            $this->disable();
            $this->decrementRequestCount();

            throw $e;
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsyncRequest(RequestInterface $request)
    {
        if ($this->isDisabled()) {
            throw new Exception\RequestException('Cannot send the request as this client has been disabled', $request);
        }

        $this->incrementRequestCount();

        return $this->client->sendAsyncRequest($request)->then(function ($response) {
            $this->decrementRequestCount();

            return $response;
        }, function ($exception) {
            $this->disable();
            $this->decrementRequestCount();

            throw $exception;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isDisabled()
    {
        if (null !== $this->reenableAfter && null !== $this->disabledAt) {
            // Reenable after a certain time
            $now = new \DateTime();

            if (($now->getTimestamp() - $this->disabledAt->getTimestamp()) >= $this->reenableAfter) {
                $this->enable();

                return false;
            }

            return true;
        }

        return null !== $this->disabledAt;
    }

    /**
     * {@inheritdoc}
     */
    public function getSendingRequestCount()
    {
        return $this->sendingRequestCount;
    }

    /**
     * Increment the request count.
     */
    private function incrementRequestCount()
    {
        ++$this->sendingRequestCount;
    }

    /**
     * Decrement the request count.
     */
    private function decrementRequestCount()
    {
        --$this->sendingRequestCount;
    }

    /**
     * Enable the current client.
     */
    private function enable()
    {
        $this->disabledAt = null;
    }

    /**
     * Disable the current client.
     */
    private function disable()
    {
        $this->disabledAt = new \DateTime('now');
    }
}
