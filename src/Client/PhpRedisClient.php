<?php

declare(strict_types=1);

namespace LPhenom\Redis\Client;

use LPhenom\Redis\Exception\RedisCommandException;
use LPhenom\Redis\Exception\RedisConnectionException;
use LPhenom\Redis\Pipeline\PhpRedisPipelineDriver;
use LPhenom\Redis\Pipeline\RedisPipeline;

/**
 * Redis client implementation using ext-redis extension.
 *
 * KPHP-compatible:
 * - No constructor property promotion
 * - No readonly properties
 * - No callable types in arrays
 * - Explicit null checks (not isset+throw pattern)
 * - try/catch with at least one catch block
 *
 * @see RedisClientInterface
 *
 * @lphenom-build shared
 */
final class PhpRedisClient implements RedisClientInterface
{
    /**
     * @var \Redis
     */
    private \Redis $redis;

    /**
     * @param \Redis $redis connected Redis instance
     */
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): ?string
    {
        $exception = null;
        $result    = null;

        try {
            $value = $this->redis->get($key);
            if ($value === false) {
                $result = null;
            } else {
                $result = (string) $value;
            }
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'GET failed for key: ' . $key . ': ' . $e->getMessage(),
                0,
                $e
            );
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
                $this->redis->setex($key, $ttl, $value);
            } else {
                $this->redis->set($key, $value);
            }
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'SET failed for key: ' . $key . ': ' . $e->getMessage(),
                0,
                $e
            );
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
            $this->redis->del($key);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'DEL failed for key: ' . $key . ': ' . $e->getMessage(),
                0,
                $e
            );
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
            $count  = $this->redis->exists($key);
            $result = ((int) $count) > 0;
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'EXISTS failed for key: ' . $key . ': ' . $e->getMessage(),
                0,
                $e
            );
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
            $value = $this->redis->incr($key);
            if ($value === false) {
                throw new RedisCommandException('INCR returned false for key: ' . $key);
            }
            $result = (int) $value;
        } catch (RedisCommandException $e) {
            $exception = $e;
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'INCR failed for key: ' . $key . ': ' . $e->getMessage(),
                0,
                $e
            );
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
            $this->redis->expire($key, $seconds);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'EXPIRE failed for key: ' . $key . ': ' . $e->getMessage(),
                0,
                $e
            );
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
            $this->redis->publish($channel, $message);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'PUBLISH failed on channel: ' . $channel . ': ' . $e->getMessage(),
                0,
                $e
            );
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
            $this->redis->lPush($key, $value);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'LPUSH failed for key: ' . $key . ': ' . $e->getMessage(),
                0,
                $e
            );
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
            $value = $this->redis->rPop($key);
            if ($value === false) {
                $result = null;
            } else {
                $result = (string) $value;
            }
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'RPOP failed for key: ' . $key . ': ' . $e->getMessage(),
                0,
                $e
            );
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
            $data = $this->redis->blPop([$key], $timeout);
            if ($data === null || $data === false) {
                $result = null;
            } elseif (is_array($data) && isset($data[1])) {
                $result = (string) $data[1];
            }
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'BLPOP failed for key: ' . $key . ': ' . $e->getMessage(),
                0,
                $e
            );
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
        $exception = null;
        $pipe      = null;

        try {
            /** @var \Redis $pipelineRedis */
            $pipelineRedis = $this->redis->multi(\Redis::PIPELINE);
            $driver        = new PhpRedisPipelineDriver($pipelineRedis);
            $pipe          = new RedisPipeline($driver);
        } catch (\RedisException $e) {
            $exception = new RedisConnectionException(
                'Failed to start pipeline: ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($exception !== null) {
            throw $exception;
        }

        if ($pipe === null) {
            throw new RedisConnectionException('Failed to start pipeline: returned null');
        }

        return $pipe;
    }
}
