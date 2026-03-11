<?php

declare(strict_types=1);

namespace LPhenom\Redis\PubSub;

/**
 * Interface for handling received pub/sub messages.
 *
 * KPHP-compatible alternative to callable/Closure.
 * Implement this interface to handle messages from RedisSubscriber.
 *
 * Usage:
 *   class MyHandler implements MessageHandlerInterface {
 *       public function handle(string $channel, string $message): void {
 *           // process message
 *       }
 *   }
 *
 *   $subscriber->subscribe('events', new MyHandler());
 *
 * @lphenom-build shared,kphp
 */
 */
interface MessageHandlerInterface
{
    /**
     * Handle a received message.
     *
     * @param string $channel channel the message was received on
     * @param string $message message payload
     */
    public function handle(string $channel, string $message): void;
}
