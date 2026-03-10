<?php

declare(strict_types=1);

namespace LPhenom\Redis\PubSub;

use LPhenom\Redis\Exception\RedisCommandException;

/**
 * Redis subscriber — subscribes to channels and processes messages.
 *
 * IMPORTANT: This class requires ext-redis and a dedicated connection.
 * Once SUBSCRIBE is called, the connection enters pub/sub mode and
 * cannot be used for other commands.
 *
 * KPHP-compatible:
 * - No constructor property promotion
 * - No readonly properties
 * - No callable types in typed arrays — uses MessageHandlerInterface
 * - try/catch with explicit catch blocks
 *
 * Usage:
 *   $subscriber = new RedisSubscriber($redisConnection);
 *   $subscriber->subscribe('channel', new MyMessageHandler());
 */
final class RedisSubscriber
{
    /** @var \Redis */
    private \Redis $redis;

    /**
     * @param \Redis $redis dedicated Redis connection for subscriptions
     */
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Subscribe to a channel and process messages.
     *
     * Blocks until unsubscribed. The handler's handle() method is called
     * for each message received.
     *
     * @param  string                  $channel channel to subscribe to
     * @param  MessageHandlerInterface $handler message handler
     * @throws RedisCommandException   on subscription error
     */
    public function subscribe(string $channel, MessageHandlerInterface $handler): void
    {
        $exception = null;

        try {
            $this->redis->subscribe(
                [$channel],
                static function (\Redis $redis, string $chan, string $message) use ($handler): void {
                    $handler->handle($chan, $message);
                }
            );
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'SUBSCRIBE failed on channel: ' . $channel . ': ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    /**
     * Subscribe to a pattern and process messages.
     *
     * @param  string                  $pattern channel pattern (e.g. "events.*")
     * @param  MessageHandlerInterface $handler message handler
     * @throws RedisCommandException   on subscription error
     */
    public function psubscribe(string $pattern, MessageHandlerInterface $handler): void
    {
        $exception = null;

        try {
            $this->redis->psubscribe(
                [$pattern],
                static function (\Redis $redis, string $pat, string $chan, string $message) use ($handler): void {
                    $handler->handle($chan, $message);
                }
            );
        } catch (\RedisException $e) {
            $exception = new RedisCommandException(
                'PSUBSCRIBE failed for pattern: ' . $pattern . ': ' . $e->getMessage(),
                0,
                $e
            );
        }

        if ($exception !== null) {
            throw $exception;
        }
    }
}
