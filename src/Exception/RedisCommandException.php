<?php

declare(strict_types=1);

namespace LPhenom\Redis\Exception;

/**
 * Thrown when a Redis command fails.
 *
 * KPHP-compatible: extends RedisException.
 *
 * @lphenom-build shared,kphp
 */
class RedisCommandException extends RedisException
{
}
