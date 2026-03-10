<?php

declare(strict_types=1);

namespace LPhenom\Redis\Resp;

use LPhenom\Redis\Pipeline\RedisPipelineDriverInterface;

/**
 * Pipeline driver backed by a RESP TCP connection.
 *
 * Buffers commands locally and sends them in a single pipeline
 * using Redis MULTI/EXEC when execute() is called.
 *
 * KPHP-compatible: no ext-redis, no callable types, explicit properties.
 */
final class RespPipelineDriver implements RedisPipelineDriverInterface
{
    /** @var RespClient */
    private RespClient $resp;

    /**
     * Buffered pipeline commands.
     *
     * @var array<int, array<int, string>>
     */
    private array $buffer;

    /**
     * @param RespClient $resp
     */
    public function __construct(RespClient $resp)
    {
        $this->resp   = $resp;
        $this->buffer = [];
    }

    public function set(string $key, string $value, int $ttl = 0): void
    {
        if ($ttl > 0) {
            $this->buffer[] = ['SET', $key, $value, 'EX', (string) $ttl];
        } else {
            $this->buffer[] = ['SET', $key, $value];
        }
    }

    public function incr(string $key): void
    {
        $this->buffer[] = ['INCR', $key];
    }

    /**
     * Flush all buffered commands using MULTI/EXEC.
     */
    public function execute(): void
    {
        if (count($this->buffer) === 0) {
            return;
        }

        $this->resp->command(['MULTI']);

        foreach ($this->buffer as $args) {
            $this->resp->command($args);
        }

        $this->resp->command(['EXEC']);
        $this->buffer = [];
    }
}
