<?php

declare(strict_types=1);

namespace LPhenom\Redis\Pipeline;

/**
 * Driver interface for RedisPipeline.
 *
 * KPHP-compatible alternative to using \Redis directly in RedisPipeline.
 * Implement this interface to connect RedisPipeline to the actual Redis extension.
 *
 * @see PhpRedisPipelineDriver for the ext-redis implementation
 */
interface RedisPipelineDriverInterface
{
    /**
     * Execute SET command (with optional TTL).
     *
     * @param string $key
     * @param string $value
     * @param int    $ttl   0 = no expiry
     */
    public function set(string $key, string $value, int $ttl = 0): void;

    /**
     * Execute INCR command.
     *
     * @param string $key
     */
    public function incr(string $key): void;

    /**
     * Flush all buffered commands (EXEC).
     */
    public function execute(): void;
}
