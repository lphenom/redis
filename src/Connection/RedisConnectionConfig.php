<?php

declare(strict_types=1);

namespace LPhenom\Redis\Connection;

/**
 * Redis connection configuration value object.
 *
 * Immutable. All properties set through constructor.
 *
 * KPHP-compatible:
 * - No constructor property promotion (explicit declarations)
 * - No readonly properties
 * - No reflection
 */
final class RedisConnectionConfig
{
    /** @var string */
    private string $host;

    /** @var int */
    private int $port;

    /** @var string */
    private string $password;

    /** @var int */
    private int $database;

    /** @var float */
    private float $timeout;

    /** @var bool */
    private bool $persistent;

    /**
     * @param string $host       Redis server hostname or IP
     * @param int    $port       Redis server port (default: 6379)
     * @param string $password   Redis AUTH password (empty = no auth)
     * @param int    $database   Redis database index (default: 0)
     * @param float  $timeout    Connection timeout in seconds (default: 2.0)
     * @param bool   $persistent Use persistent connections (default: false)
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        string $password = '',
        int $database = 0,
        float $timeout = 2.0,
        bool $persistent = false
    ) {
        $this->host       = $host;
        $this->port       = $port;
        $this->password   = $password;
        $this->database   = $database;
        $this->timeout    = $timeout;
        $this->persistent = $persistent;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getDatabase(): int
    {
        return $this->database;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }
}
