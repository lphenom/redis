<?php

declare(strict_types=1);

namespace LPhenom\Redis\Connection;

use LPhenom\Redis\Client\PhpRedisClient;
use LPhenom\Redis\Client\RedisClientInterface;
use LPhenom\Redis\Exception\RedisConnectionException;

/**
 * Factory for creating Redis client instances.
 *
 * Creates appropriate client based on available extensions.
 *
 * KPHP-compatible:
 * - No dynamic class loading (new $className())
 * - No reflection
 * - Explicit new PhpRedisClient() / new FfiRedisClient()
 * - try/catch with explicit catch blocks
 */
final class RedisConnector
{
    /**
     * Connect to Redis and return a client instance.
     *
     * Requires ext-redis to be installed and enabled.
     *
     * @param  RedisConnectionConfig    $config connection configuration
     * @throws RedisConnectionException if connection fails
     * @return RedisClientInterface
     */
    public static function connect(RedisConnectionConfig $config): RedisClientInterface
    {
        $exception = null;
        $redis     = null;

        try {
            $redisExt = new \Redis();

            if ($config->isPersistent()) {
                $connected = $redisExt->pconnect(
                    $config->getHost(),
                    $config->getPort(),
                    $config->getTimeout()
                );
            } else {
                $connected = $redisExt->connect(
                    $config->getHost(),
                    $config->getPort(),
                    $config->getTimeout()
                );
            }

            if ($connected === false) {
                throw new RedisConnectionException(
                    'Failed to connect to Redis at ' . $config->getHost() . ':' . $config->getPort()
                );
            }

            $password = $config->getPassword();
            if ($password !== '') {
                $authResult = $redisExt->auth($password);
                if ($authResult === false) {
                    throw new RedisConnectionException('Redis AUTH failed');
                }
            }

            $database = $config->getDatabase();
            if ($database !== 0) {
                $selectResult = $redisExt->select($database);
                if ($selectResult === false) {
                    throw new RedisConnectionException('Redis SELECT ' . $database . ' failed');
                }
            }

            $redis = new PhpRedisClient($redisExt);
        } catch (RedisConnectionException $e) {
            $exception = $e;
        } catch (\RedisException $e) {
            $exception = new RedisConnectionException(
                'Redis connection error: ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($exception !== null) {
            throw $exception;
        }

        if ($redis === null) {
            throw new RedisConnectionException('Failed to create Redis client: unexpected null');
        }

        return $redis;
    }
}
