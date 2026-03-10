<?php

declare(strict_types=1);

namespace LPhenom\Redis\Exception;

/**
 * Thrown when a feature is not yet implemented (e.g. FfiRedisClient stubs).
 *
 * KPHP-compatible: extends RedisException.
 */
class NotImplementedException extends RedisException
{
}
