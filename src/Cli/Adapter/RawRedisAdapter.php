<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Adapter;

use LPhenom\Redis\Exception\RedisCommandException;

/**
 * Raw Redis adapter for CLI tool.
 *
 * Provides access to Redis commands not in RedisClientInterface:
 * SCAN, TYPE, TTL, HGETALL, SMEMBERS, ZRANGE, LRANGE, etc.
 *
 * Requires ext-redis.
 */
final class RawRedisAdapter
{
    /** @var \Redis */
    private \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Scan keys matching a pattern using cursor-based iteration.
     * Returns all matching keys (iterates until cursor returns 0).
     *
     * @param  string             $pattern SCAN MATCH pattern
     * @param  int                $count   COUNT hint per iteration
     * @return array<int, string>
     */
    public function scan(string $pattern = '*', int $count = 100): array
    {
        $keys      = [];
        $cursor    = null;
        $exception = null;

        try {
            do {
                $batch = $this->redis->scan($cursor, $pattern, $count);
                if ($batch !== false && is_array($batch)) {
                    foreach ($batch as $key) {
                        $keys[] = (string) $key;
                    }
                }
            } while ($cursor !== null && $cursor !== 0);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('SCAN failed: ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $keys;
    }

    /**
     * Get type of a key.
     * Returns: string, list, set, zset, hash, none
     */
    public function type(string $key): string
    {
        $exception = null;
        $result    = 'none';

        try {
            $t = $this->redis->type($key);
            if ($t === \Redis::REDIS_STRING) {
                $result = 'string';
            } elseif ($t === \Redis::REDIS_LIST) {
                $result = 'list';
            } elseif ($t === \Redis::REDIS_SET) {
                $result = 'set';
            } elseif ($t === \Redis::REDIS_ZSET) {
                $result = 'zset';
            } elseif ($t === \Redis::REDIS_HASH) {
                $result = 'hash';
            } else {
                $result = 'none';
            }
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('TYPE failed for key ' . $key . ': ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Get TTL of a key in seconds. Returns -1 if no expiry, -2 if key does not exist.
     */
    public function ttl(string $key): int
    {
        $exception = null;
        $result    = -2;

        try {
            $result = (int) $this->redis->ttl($key);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('TTL failed for key ' . $key . ': ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Get all hash fields and values.
     *
     * @return array<string, string>
     */
    public function hgetall(string $key): array
    {
        $exception = null;
        $result    = [];

        try {
            $data = $this->redis->hGetAll($key);
            if (is_array($data)) {
                foreach ($data as $field => $value) {
                    $result[(string) $field] = (string) $value;
                }
            }
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('HGETALL failed for key ' . $key . ': ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Set a hash field.
     */
    public function hset(string $key, string $field, string $value): void
    {
        $exception = null;

        try {
            $this->redis->hSet($key, $field, $value);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('HSET failed: ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * Delete a hash field.
     */
    public function hdel(string $key, string $field): void
    {
        $exception = null;

        try {
            $this->redis->hDel($key, $field);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('HDEL failed: ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * Get list range.
     *
     * @return array<int, string>
     */
    public function lrange(string $key, int $start = 0, int $stop = -1): array
    {
        $exception = null;
        $result    = [];

        try {
            $data = $this->redis->lRange($key, $start, $stop);
            if (is_array($data)) {
                foreach ($data as $item) {
                    $result[] = (string) $item;
                }
            }
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('LRANGE failed for key ' . $key . ': ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Remove list elements by value.
     */
    public function lrem(string $key, string $value, int $count = 1): void
    {
        $exception = null;

        try {
            $this->redis->lRem($key, $value, $count);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('LREM failed: ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * Get set members.
     *
     * @return array<int, string>
     */
    public function smembers(string $key): array
    {
        $exception = null;
        $result    = [];

        try {
            $data = $this->redis->sMembers($key);
            if (is_array($data)) {
                foreach ($data as $item) {
                    $result[] = (string) $item;
                }
            }
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('SMEMBERS failed for key ' . $key . ': ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Add set member.
     */
    public function sadd(string $key, string $member): void
    {
        $exception = null;

        try {
            $this->redis->sAdd($key, $member);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('SADD failed: ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * Remove set member.
     */
    public function srem(string $key, string $member): void
    {
        $exception = null;

        try {
            $this->redis->sRem($key, $member);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('SREM failed: ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * Get sorted set range with scores.
     *
     * @return array<int, array<int, string>> [[member, score], ...]
     */
    public function zrangeWithScores(string $key, int $start = 0, int $stop = -1): array
    {
        $exception = null;
        $result    = [];

        try {
            $data = $this->redis->zRange($key, $start, $stop, true);
            if (is_array($data)) {
                foreach ($data as $member => $score) {
                    $result[] = [(string) $member, (string) $score];
                }
            }
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('ZRANGE failed for key ' . $key . ': ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Add/update sorted set member with score.
     */
    public function zadd(string $key, float $score, string $member): void
    {
        $exception = null;

        try {
            $this->redis->zAdd($key, $score, $member);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('ZADD failed: ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * Remove sorted set member.
     */
    public function zrem(string $key, string $member): void
    {
        $exception = null;

        try {
            $this->redis->zRem($key, $member);
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('ZREM failed: ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * Get the underlying Redis instance.
     */
    public function getRedis(): \Redis
    {
        return $this->redis;
    }

    /**
     * Get server info.
     *
     * @return array<string, string>
     */
    public function info(): array
    {
        $exception = null;
        $result    = [];

        try {
            $data = $this->redis->info();
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    $result[(string) $k] = (string) $v;
                }
            }
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('INFO failed: ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Get number of keys in current database.
     */
    public function dbsize(): int
    {
        $exception = null;
        $result    = 0;

        try {
            $result = (int) $this->redis->dbSize();
        } catch (\RedisException $e) {
            $exception = new RedisCommandException('DBSIZE failed: ' . $e->getMessage(), 0, $e);
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }
}
