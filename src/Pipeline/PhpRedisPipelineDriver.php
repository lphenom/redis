<?php

declare(strict_types=1);

namespace LPhenom\Redis\Pipeline;

/**
 * ext-redis pipeline driver implementation.
 *
 * NOT included in KPHP entrypoint — requires ext-redis.
 * Used only in PHP runtime mode via PhpRedisClient::pipeline().
 *
 * @lphenom-build shared
 */
final class PhpRedisPipelineDriver implements RedisPipelineDriverInterface
{
    /** @var \Redis */
    private \Redis $redis;

    /**
     * @param \Redis $redis Pipeline-mode Redis instance (from Redis::multi(Redis::PIPELINE))
     */
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function set(string $key, string $value, int $ttl = 0): void
    {
        if ($ttl > 0) {
            $this->redis->setex($key, $ttl, $value);
        } else {
            $this->redis->set($key, $value);
        }
    }

    public function incr(string $key): void
    {
        $this->redis->incr($key);
    }

    public function execute(): void
    {
        $this->redis->exec();
    }
}
