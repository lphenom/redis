<?php

declare(strict_types=1);

namespace LPhenom\Redis\Client;

use LPhenom\Redis\Pipeline\RedisPipeline;

/**
 * Redis client interface.
 *
 * KPHP-compatible:
 * - No callable types
 * - No reflection
 * - All types are explicit
 *
 * Used by: cache, queue, realtime, auth packages.
 */
interface RedisClientInterface
{
    /**
     * Get value by key.
     *
     * @param  string      $key
     * @return string|null null if key does not exist
     */
    public function get(string $key): ?string;

    /**
     * Set value with optional TTL.
     *
     * @param string $key
     * @param string $value
     * @param int    $ttl   seconds, 0 = no expiry
     */
    public function set(string $key, string $value, int $ttl = 0): void;

    /**
     * Delete key.
     *
     * @param string $key
     */
    public function del(string $key): void;

    /**
     * Check if key exists.
     *
     * @param  string $key
     * @return bool
     */
    public function exists(string $key): bool;

    /**
     * Increment integer value of key by 1.
     *
     * @param  string $key
     * @return int    new value
     */
    public function incr(string $key): int;

    /**
     * Set expiry on key.
     *
     * @param string $key
     * @param int    $seconds
     */
    public function expire(string $key, int $seconds): void;

    /**
     * Publish a message to a channel.
     *
     * @param string $channel
     * @param string $message
     */
    public function publish(string $channel, string $message): void;

    /**
     * Prepend value to list.
     *
     * @param string $key
     * @param string $value
     */
    public function lpush(string $key, string $value): void;

    /**
     * Remove and return last element of list.
     *
     * @param  string      $key
     * @return string|null null if list is empty
     */
    public function rpop(string $key): ?string;

    /**
     * Block and pop first element of list (blocking queue).
     *
     * @param  string      $key
     * @param  int         $timeout seconds to wait, 0 = block forever
     * @return string|null null on timeout
     */
    public function blpop(string $key, int $timeout): ?string;

    /**
     * Create a pipeline for batch commands.
     *
     * @return RedisPipeline
     */
    public function pipeline(): RedisPipeline;
}
