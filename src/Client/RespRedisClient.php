<?php

declare(strict_types=1);

namespace LPhenom\Redis\Client;

use LPhenom\Redis\Exception\RedisCommandException;
use LPhenom\Redis\Pipeline\RedisPipeline;
use LPhenom\Redis\Resp\RespClient;
use LPhenom\Redis\Resp\RespPipelineDriver;

/**
 * Redis client implementation using raw TCP + RESP protocol.
 *
 * Works in BOTH modes:
 *   - PHP 8.1+ runtime (fsockopen + streams)
 *   - KPHP compiled binary (stream_socket_client is KPHP-supported)
 *
 * No ext-redis required. No FFI. No C libraries.
 * Pure PHP RESP protocol implementation.
 *
 * KPHP-compatible:
 * - No constructor property promotion
 * - No readonly properties
 * - No callable types in arrays
 * - Explicit null checks
 * - try/catch with explicit catch blocks
 * - No str_starts_with/str_ends_with
 *
 * @see RedisClientInterface
 * @see RespClient
 *
 * @lphenom-build shared,kphp
 */
final class RespRedisClient implements RedisClientInterface
{
    /** @var RespClient */
    private RespClient $resp;

    /**
     * @param RespClient $resp connected RESP client
     */
    public function __construct(RespClient $resp)
    {
        $this->resp = $resp;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): ?string
    {
        $exception = null;
        $result    = null;

        try {
            $reply = $this->resp->command(['GET', $key]);
            $result = $reply !== null ? (string) $reply : null;
        } catch (RedisCommandException $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, string $value, int $ttl = 0): void
    {
        $exception = null;

        try {
            if ($ttl > 0) {
                $this->resp->command(['SET', $key, $value, 'EX', (string) $ttl]);
            } else {
                $this->resp->command(['SET', $key, $value]);
            }
        } catch (RedisCommandException $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function del(string $key): void
    {
        $exception = null;

        try {
            $this->resp->command(['DEL', $key]);
        } catch (RedisCommandException $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        $exception = null;
        $result    = false;

        try {
            $reply  = $this->resp->command(['EXISTS', $key]);
            $result = ((int) $reply) > 0;
        } catch (RedisCommandException $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function incr(string $key): int
    {
        $exception = null;
        $result    = 0;

        try {
            $reply  = $this->resp->command(['INCR', $key]);
            $result = (int) $reply;
        } catch (RedisCommandException $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function expire(string $key, int $seconds): void
    {
        $exception = null;

        try {
            $this->resp->command(['EXPIRE', $key, (string) $seconds]);
        } catch (RedisCommandException $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function publish(string $channel, string $message): void
    {
        $exception = null;

        try {
            $this->resp->command(['PUBLISH', $channel, $message]);
        } catch (RedisCommandException $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function lpush(string $key, string $value): void
    {
        $exception = null;

        try {
            $this->resp->command(['LPUSH', $key, $value]);
        } catch (RedisCommandException $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rpop(string $key): ?string
    {
        $exception = null;
        $result    = null;

        try {
            $reply  = $this->resp->command(['RPOP', $key]);
            $result = $reply !== null ? (string) $reply : null;
        } catch (RedisCommandException $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function blpop(string $key, int $timeout): ?string
    {
        $exception = null;
        $result    = null;

        try {
            // BLPOP returns a 2-element array: [key, value] or nil on timeout
            // RespClient handles the array parsing and returns index-1 value
            $reply  = $this->resp->command(['BLPOP', $key, (string) $timeout]);
            $result = $reply !== null ? (string) $reply : null;
        } catch (RedisCommandException $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function pipeline(): RedisPipeline
    {
        $driver = new RespPipelineDriver($this->resp);
        return new RedisPipeline($driver);
    }
}
