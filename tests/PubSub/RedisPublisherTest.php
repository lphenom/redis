<?php

declare(strict_types=1);

namespace LPhenom\Redis\Tests\PubSub;

use LPhenom\Redis\Client\RedisClientInterface;
use LPhenom\Redis\Pipeline\RedisPipeline;
use LPhenom\Redis\PubSub\RedisPublisher;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RedisPublisher.
 */
final class RedisPublisherTest extends TestCase
{
    public function testPublishCallsClientPublish(): void
    {
        $clientMock = new class () implements RedisClientInterface {
            public string $lastChannel = '';
            public string $lastMessage = '';

            public function get(string $key): ?string
            {
                return null;
            }
            public function set(string $key, string $value, int $ttl = 0): void
            {
            }
            public function del(string $key): void
            {
            }
            public function exists(string $key): bool
            {
                return false;
            }
            public function incr(string $key): int
            {
                return 0;
            }
            public function expire(string $key, int $seconds): void
            {
            }
            public function lpush(string $key, string $value): void
            {
            }
            public function rpop(string $key): ?string
            {
                return null;
            }
            public function blpop(string $key, int $timeout): ?string
            {
                return null;
            }
            public function pipeline(): RedisPipeline
            {
                return new RedisPipeline(null);
            }

            public function publish(string $channel, string $message): void
            {
                $this->lastChannel = $channel;
                $this->lastMessage = $message;
            }
        };

        $publisher = new RedisPublisher($clientMock);
        $publisher->publish('events', 'hello');

        $this->assertSame('events', $clientMock->lastChannel);
        $this->assertSame('hello', $clientMock->lastMessage);
    }
}
