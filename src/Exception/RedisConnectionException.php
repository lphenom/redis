<?php

declare(strict_types=1);

namespace LPhenom\Redis\Exception;

/**
 * Thrown when a connection to Redis cannot be established.
 *
 * KPHP-compatible: extends RedisException.
 */
class RedisConnectionException extends RedisException
{
}
