<?php

declare(strict_types=1);

namespace LPhenom\Redis\Connection;

use LPhenom\Redis\Client\PhpRedisClient;
use LPhenom\Redis\Client\RedisClientInterface;
use LPhenom\Redis\Client\RespRedisClient;
use LPhenom\Redis\Exception\RedisCommandException;
use LPhenom\Redis\Exception\RedisConnectionException;
use LPhenom\Redis\Resp\RespClient;

/**
 * Factory for creating Redis client instances.
 *
 * Driver selection:
 *   - connectPhpRedis() — uses ext-redis (PHP runtime, shared hosting)
 *   - connectResp()     — uses raw TCP + RESP protocol (KPHP binary, or when ext-redis unavailable)
 *   - connect()         — auto-selects: ext-redis if available, else RESP
 *
 * KPHP-compatible:
 * - No dynamic class loading (new $className())
 * - No reflection
 * - Explicit new PhpRedisClient() / new RespRedisClient()
 * - try/catch with explicit catch blocks
 */
final class RedisConnector
{
    /**
     * Auto-select driver: ext-redis if available, otherwise RESP over TCP.
     *
     * @param  RedisConnectionConfig    $config
     * @throws RedisConnectionException
     * @return RedisClientInterface
     */
    public static function connect(RedisConnectionConfig $config): RedisClientInterface
    {
        if (extension_loaded('redis')) {
            return self::connectPhpRedis($config);
        }

        return self::connectResp($config);
    }

    /**
     * Connect using ext-redis (PHP runtime / shared hosting).
     *
     * @param  RedisConnectionConfig    $config
     * @throws RedisConnectionException
     * @return RedisClientInterface
     */
    public static function connectPhpRedis(RedisConnectionConfig $config): RedisClientInterface
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
        } catch (\LPhenom\Redis\Exception\RedisCommandException $e) {
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

    /**
     * Connect using raw TCP + RESP protocol.
     *
     * KPHP-compatible: fsockopen() is supported in KPHP compiled binary.
     * Use this driver in KPHP mode or when ext-redis is not available.
     *
     * @param  RedisConnectionConfig    $config
     * @throws RedisConnectionException
     * @return RespRedisClient
     */
    public static function connectResp(RedisConnectionConfig $config): RespRedisClient
    {
        $exception = null;
        $client    = null;

        try {
            $resp = new RespClient(
                $config->getHost(),
                $config->getPort(),
                $config->getTimeout()
            );

            $resp->connect();

            $password = $config->getPassword();
            if ($password !== '') {
                $resp->auth($password);
            }

            $database = $config->getDatabase();
            if ($database !== 0) {
                $resp->select($database);
            }

            $client = new RespRedisClient($resp);
        } catch (RedisConnectionException $e) {
            $exception = $e;
        } catch (RedisCommandException $e) {
            $exception = new RedisConnectionException(
                'Redis setup failed: ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($exception !== null) {
            throw $exception;
        }

        if ($client === null) {
            throw new RedisConnectionException('Failed to create RESP Redis client: unexpected null');
        }

        return $client;
    }
}
