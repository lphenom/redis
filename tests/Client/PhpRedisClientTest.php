<?php

declare(strict_types=1);

namespace LPhenom\Redis\Tests\Client;

use LPhenom\Redis\Client\PhpRedisClient;
use LPhenom\Redis\Exception\RedisCommandException;
use LPhenom\Redis\Pipeline\RedisPipeline;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PhpRedisClient using a mocked \Redis extension.
 */
final class PhpRedisClientTest extends TestCase
{
    /** @var \Redis&MockObject */
    private \Redis $redisMock;

    private PhpRedisClient $client;

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is not loaded');
        }

        $this->redisMock = $this->createMock(\Redis::class);
        $this->client    = new PhpRedisClient($this->redisMock);
    }

    public function testGetReturnsString(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn('bar');

        $result = $this->client->get('foo');
        $this->assertSame('bar', $result);
    }

    public function testGetReturnsNullWhenKeyNotFound(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('get')
            ->with('missing')
            ->willReturn(false);

        $result = $this->client->get('missing');
        $this->assertNull($result);
    }

    public function testSetWithoutTtl(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('set')
            ->with('key', 'val');

        $this->client->set('key', 'val');
    }

    public function testSetWithTtl(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('setex')
            ->with('key', 60, 'val');

        $this->client->set('key', 'val', 60);
    }

    public function testDel(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('del')
            ->with('key');

        $this->client->del('key');
    }

    public function testExistsReturnsTrue(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('exists')
            ->with('key')
            ->willReturn(1);

        $this->assertTrue($this->client->exists('key'));
    }

    public function testExistsReturnsFalse(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('exists')
            ->with('missing')
            ->willReturn(0);

        $this->assertFalse($this->client->exists('missing'));
    }

    public function testIncr(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('incr')
            ->with('counter')
            ->willReturn(5);

        $result = $this->client->incr('counter');
        $this->assertSame(5, $result);
    }

    public function testIncrThrowsOnFalse(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('incr')
            ->with('bad')
            ->willReturn(false);

        $this->expectException(RedisCommandException::class);
        $this->client->incr('bad');
    }

    public function testExpire(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('expire')
            ->with('key', 120);

        $this->client->expire('key', 120);
    }

    public function testPublish(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('publish')
            ->with('channel', 'message');

        $this->client->publish('channel', 'message');
    }

    public function testLpush(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('lPush')
            ->with('queue', 'job1');

        $this->client->lpush('queue', 'job1');
    }

    public function testRpopReturnsValue(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('rPop')
            ->with('queue')
            ->willReturn('job1');

        $result = $this->client->rpop('queue');
        $this->assertSame('job1', $result);
    }

    public function testRpopReturnsNullWhenEmpty(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('rPop')
            ->with('queue')
            ->willReturn(false);

        $result = $this->client->rpop('queue');
        $this->assertNull($result);
    }

    public function testBlpopReturnsValue(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('blPop')
            ->with(['queue'], 5)
            ->willReturn(['queue', 'job1']);

        $result = $this->client->blpop('queue', 5);
        $this->assertSame('job1', $result);
    }

    public function testBlpopReturnsNullOnTimeout(): void
    {
        $this->redisMock
            ->expects($this->once())
            ->method('blPop')
            ->with(['queue'], 1)
            ->willReturn(null);

        $result = $this->client->blpop('queue', 1);
        $this->assertNull($result);
    }

    public function testPipelineReturnsPipelineInstance(): void
    {
        $pipelineMock = $this->createMock(\Redis::class);
        $pipelineMock->method('exec')->willReturn([]);

        $this->redisMock
            ->expects($this->once())
            ->method('multi')
            ->with(\Redis::PIPELINE)
            ->willReturn($pipelineMock);

        $pipeline = $this->client->pipeline();
        $this->assertInstanceOf(RedisPipeline::class, $pipeline);
    }
}
