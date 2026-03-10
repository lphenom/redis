<?php

declare(strict_types=1);

namespace LPhenom\Redis\Tests\Resp;

use LPhenom\Redis\Client\RespRedisClient;
use LPhenom\Redis\Exception\RedisConnectionException;
use LPhenom\Redis\Pipeline\RedisPipeline;
use LPhenom\Redis\Resp\RespClient;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for RespRedisClient against a real Redis instance.
 *
 * Requires REDIS_HOST and REDIS_PORT env vars (set via docker-compose).
 * Tests are skipped if Redis is not reachable.
 */
final class RespRedisClientIntegrationTest extends TestCase
{
    private ?RespRedisClient $client = null;

    protected function setUp(): void
    {
        $host = (string) ($_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST') ?: '127.0.0.1');
        $port = (int)    ($_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6379);

        $resp = new RespClient($host, $port, 2.0);

        $connected = false;
        $ex        = null;

        try {
            $resp->connect();
            $connected = true;
        } catch (RedisConnectionException $e) {
            $ex = $e;
        }

        if (!$connected || $ex !== null) {
            $this->markTestSkipped('Redis not reachable at ' . $host . ':' . $port);
        }

        $this->client = new RespRedisClient($resp);
    }

    public function testSetAndGet(): void
    {
        $client = $this->client;
        if ($client === null) {
            $this->markTestSkipped('Client not initialized');
        }

        $key = 'test:resp:' . uniqid('', true);
        $client->set($key, 'hello', 10);
        $value = $client->get($key);
        $this->assertSame('hello', $value);
        $client->del($key);
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $client = $this->client;
        if ($client === null) {
            $this->markTestSkipped('Client not initialized');
        }

        $key    = 'test:resp:nonexistent:' . uniqid('', true);
        $result = $client->get($key);
        $this->assertNull($result);
    }

    public function testSetWithTtl(): void
    {
        $client = $this->client;
        if ($client === null) {
            $this->markTestSkipped('Client not initialized');
        }

        $key = 'test:resp:ttl:' . uniqid('', true);
        $client->set($key, 'val', 60);
        $this->assertTrue($client->exists($key));
        $client->del($key);
    }

    public function testExists(): void
    {
        $client = $this->client;
        if ($client === null) {
            $this->markTestSkipped('Client not initialized');
        }

        $key = 'test:resp:exists:' . uniqid('', true);
        $this->assertFalse($client->exists($key));
        $client->set($key, '1');
        $this->assertTrue($client->exists($key));
        $client->del($key);
        $this->assertFalse($client->exists($key));
    }

    public function testIncr(): void
    {
        $client = $this->client;
        if ($client === null) {
            $this->markTestSkipped('Client not initialized');
        }

        $key = 'test:resp:incr:' . uniqid('', true);
        $this->assertSame(1, $client->incr($key));
        $this->assertSame(2, $client->incr($key));
        $this->assertSame(3, $client->incr($key));
        $client->del($key);
    }

    public function testExpire(): void
    {
        $client = $this->client;
        if ($client === null) {
            $this->markTestSkipped('Client not initialized');
        }

        $key = 'test:resp:expire:' . uniqid('', true);
        $client->set($key, 'v');
        $client->expire($key, 100);
        $this->assertTrue($client->exists($key));
        $client->del($key);
    }

    public function testLpushRpop(): void
    {
        $client = $this->client;
        if ($client === null) {
            $this->markTestSkipped('Client not initialized');
        }

        $key = 'test:resp:queue:' . uniqid('', true);
        $client->lpush($key, 'job1');
        $client->lpush($key, 'job2');

        $result = $client->rpop($key);
        $this->assertSame('job1', $result);
        $result = $client->rpop($key);
        $this->assertSame('job2', $result);
        $result = $client->rpop($key);
        $this->assertNull($result);
    }

    public function testPipeline(): void
    {
        $client = $this->client;
        if ($client === null) {
            $this->markTestSkipped('Client not initialized');
        }

        $key1    = 'test:resp:pipe1:' . uniqid('', true);
        $key2    = 'test:resp:pipe2:' . uniqid('', true);
        $counter = 'test:resp:counter:' . uniqid('', true);

        $pipeline = $client->pipeline();
        $this->assertInstanceOf(RedisPipeline::class, $pipeline);

        $pipeline->set($key1, 'v1');
        $pipeline->set($key2, 'v2');
        $pipeline->incr($counter);
        $pipeline->execute();

        $client->del($key1);
        $client->del($key2);
        $client->del($counter);
    }
}
