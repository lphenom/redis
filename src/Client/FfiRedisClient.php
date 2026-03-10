<?php

declare(strict_types=1);

namespace LPhenom\Redis\Client;

use LPhenom\Redis\Exception\NotImplementedException;
use LPhenom\Redis\Pipeline\RedisPipeline;

/**
 * FFI Redis client stub — placeholder for KPHP FFI implementation.
 *
 * This class is a stub. FFI bindings will be implemented later.
 * Throws NotImplementedException on every method call.
 *
 * KPHP-compatible:
 * - No constructor property promotion
 * - No readonly properties
 * - No callable types
 *
 * @see RedisClientInterface
 */
final class FfiRedisClient implements RedisClientInterface
{
    public function get(string $key): ?string
    {
        throw new NotImplementedException('FfiRedisClient::get() is not implemented yet');
    }

    public function set(string $key, string $value, int $ttl = 0): void
    {
        throw new NotImplementedException('FfiRedisClient::set() is not implemented yet');
    }

    public function del(string $key): void
    {
        throw new NotImplementedException('FfiRedisClient::del() is not implemented yet');
    }

    public function exists(string $key): bool
    {
        throw new NotImplementedException('FfiRedisClient::exists() is not implemented yet');
    }

    public function incr(string $key): int
    {
        throw new NotImplementedException('FfiRedisClient::incr() is not implemented yet');
    }

    public function expire(string $key, int $seconds): void
    {
        throw new NotImplementedException('FfiRedisClient::expire() is not implemented yet');
    }

    public function publish(string $channel, string $message): void
    {
        throw new NotImplementedException('FfiRedisClient::publish() is not implemented yet');
    }

    public function lpush(string $key, string $value): void
    {
        throw new NotImplementedException('FfiRedisClient::lpush() is not implemented yet');
    }

    public function rpop(string $key): ?string
    {
        throw new NotImplementedException('FfiRedisClient::rpop() is not implemented yet');
    }

    public function blpop(string $key, int $timeout): ?string
    {
        throw new NotImplementedException('FfiRedisClient::blpop() is not implemented yet');
    }

    public function pipeline(): RedisPipeline
    {
        throw new NotImplementedException('FfiRedisClient::pipeline() is not implemented yet');
    }
}
