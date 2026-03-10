<?php

declare(strict_types=1);

namespace LPhenom\Redis\PubSub;

use LPhenom\Redis\Client\RedisClientInterface;

/**
 * Redis publisher — publishes messages to channels.
 *
 * KPHP-compatible:
 * - No constructor property promotion
 * - No readonly properties
 * - No callable types
 *
 * Usage:
 *   $publisher = new RedisPublisher($redisClient);
 *   $publisher->publish('events', json_encode(['type' => 'user.created']));
 */
final class RedisPublisher
{
    /** @var RedisClientInterface */
    private RedisClientInterface $client;

    /**
     * @param RedisClientInterface $client
     */
    public function __construct(RedisClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Publish a message to a channel.
     *
     * @param string $channel channel name
     * @param string $message message payload
     */
    public function publish(string $channel, string $message): void
    {
        $this->client->publish($channel, $message);
    }
}
