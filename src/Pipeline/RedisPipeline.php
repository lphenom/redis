<?php

declare(strict_types=1);

namespace LPhenom\Redis\Pipeline;

/**
 * Redis pipeline — batch command execution.
 *
 * Buffers SET and INCR commands and executes them.
 * In stub mode (null driver) commands are buffered in memory.
 *
 * KPHP-compatible:
 * - No \Redis type hints (ext-redis not available in KPHP)
 * - No callable types
 * - Explicit property declaration (no constructor promotion)
 * - No readonly properties
 *
 * Usage:
 *   $pipeline = $redis->pipeline();
 *   $pipeline->set('a', '1');
 *   $pipeline->incr('counter');
 *   $pipeline->execute();
 */
final class RedisPipeline
{
    /**
     * Buffered commands for stub/KPHP mode.
     *
     * @var array<int, array<int, string>>
     */
    private array $commands;

    /**
     * Pipeline driver — executes real Redis commands.
     * null in stub/KPHP mode.
     *
     * @var RedisPipelineDriverInterface|null
     */
    private ?RedisPipelineDriverInterface $driver;

    /**
     * @param RedisPipelineDriverInterface|null $driver null for stub/KPHP mode
     */
    public function __construct(?RedisPipelineDriverInterface $driver)
    {
        $this->driver   = $driver;
        $this->commands = [];
    }

    /**
     * Buffer a SET command.
     *
     * @param string $key
     * @param string $value
     * @param int    $ttl   seconds, 0 = no expiry
     */
    public function set(string $key, string $value, int $ttl = 0): void
    {
        $driver = $this->driver;
        if ($driver !== null) {
            $driver->set($key, $value, $ttl);
            return;
        }
        $this->commands[] = ['SET', $key, $value, (string) $ttl];
    }

    /**
     * Buffer an INCR command.
     *
     * @param string $key
     */
    public function incr(string $key): void
    {
        $driver = $this->driver;
        if ($driver !== null) {
            $driver->incr($key);
            return;
        }
        $this->commands[] = ['INCR', $key];
    }

    /**
     * Execute all buffered commands.
     */
    public function execute(): void
    {
        $driver = $this->driver;
        if ($driver !== null) {
            $driver->execute();
            return;
        }
        $this->commands = [];
    }

    /**
     * Get buffered commands (stub/KPHP mode only).
     *
     * @return array<int, array<int, string>>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
}
