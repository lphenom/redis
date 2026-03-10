<?php

declare(strict_types=1);

namespace LPhenom\Redis\Tests\Client;

use LPhenom\Redis\Client\FfiRedisClient;
use LPhenom\Redis\Exception\NotImplementedException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FfiRedisClient stub.
 */
final class FfiRedisClientTest extends TestCase
{
    private FfiRedisClient $client;

    protected function setUp(): void
    {
        $this->client = new FfiRedisClient();
    }

    public function testGetThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->client->get('key');
    }

    public function testSetThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->client->set('key', 'value');
    }

    public function testDelThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->client->del('key');
    }

    public function testExistsThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->client->exists('key');
    }

    public function testIncrThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->client->incr('key');
    }

    public function testExpireThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->client->expire('key', 60);
    }

    public function testPublishThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->client->publish('channel', 'message');
    }

    public function testLpushThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->client->lpush('key', 'value');
    }

    public function testRpopThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->client->rpop('key');
    }

    public function testBlpopThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->client->blpop('key', 1);
    }

    public function testPipelineThrowsNotImplementedException(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->client->pipeline();
    }
}
