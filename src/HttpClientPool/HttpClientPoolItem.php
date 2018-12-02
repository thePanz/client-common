<?php

namespace Http\Client\Common\HttpClientPool;

use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;

/**
 * A HttpClientPoolItem represent a HttpClient inside a Pool.
 *
 * It is disabled when a request failed and can be reenabled after a certain number of seconds.
 * It also keep tracks of the current number of open requests the client is currently being sending
 * (only usable for async method).
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
interface HttpClientPoolItem extends HttpClient, HttpAsyncClient
{
    /**
     * Whether this client is disabled or not.
     *
     * Will also reactivate this client if possible
     *
     * @internal
     *
     * @return bool
     */
    public function isDisabled();

    /**
     * Get current number of request that are currently being sent by the underlying HTTP client.
     *
     * @internal
     *
     * @return int
     */
    public function getSendingRequestCount();
}
