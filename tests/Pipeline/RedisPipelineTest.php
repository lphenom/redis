<?php

declare(strict_types=1);

namespace LPhenom\Redis\Tests\Pipeline;

use LPhenom\Redis\Pipeline\PhpRedisPipelineDriver;
use LPhenom\Redis\Pipeline\RedisPipeline;
use LPhenom\Redis\Pipeline\RedisPipelineDriverInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RedisPipeline.
 */
final class RedisPipelineTest extends TestCase
{
    public function testExecuteWithNullDriver(): void
    {
        $pipeline = new RedisPipeline(null);
        $pipeline->set('a', '1');
        $pipeline->set('b', '2', 60);
        $pipeline->incr('counter');

        // Should not throw — execute clears commands
        $pipeline->execute();
        $this->assertTrue(true);
    }

    public function testGetCommandsInNullMode(): void
    {
        $pipeline = new RedisPipeline(null);
        $pipeline->set('key', 'val');
        $pipeline->incr('counter');

        $commands = $pipeline->getCommands();
        $this->assertCount(2, $commands);
    }

    public function testSetAndIncrWithDriver(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is not loaded');
        }

        $redisMock = $this->createMock(\Redis::class);

        $redisMock
            ->expects($this->once())
            ->method('set')
            ->with('key', 'val');

        $redisMock
            ->expects($this->once())
            ->method('incr')
            ->with('counter');

        $redisMock
            ->expects($this->once())
            ->method('exec');

        $driver   = new PhpRedisPipelineDriver($redisMock);
        $pipeline = new RedisPipeline($driver);
        $pipeline->set('key', 'val');
        $pipeline->incr('counter');
        $pipeline->execute();
    }

    public function testSetWithTtlCallsSetex(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is not loaded');
        }

        $redisMock = $this->createMock(\Redis::class);

        $redisMock
            ->expects($this->once())
            ->method('setex')
            ->with('key', 60, 'val');

        $redisMock
            ->expects($this->once())
            ->method('exec');

        $driver   = new PhpRedisPipelineDriver($redisMock);
        $pipeline = new RedisPipeline($driver);
        $pipeline->set('key', 'val', 60);
        $pipeline->execute();
    }

    public function testDriverInterfaceAbstraction(): void
    {
        $driverMock = new class () implements RedisPipelineDriverInterface {
            /** @var array<int, string> */
            public array $log = [];

            public function set(string $key, string $value, int $ttl = 0): void
            {
                $this->log[] = 'SET:' . $key . '=' . $value;
            }

            public function incr(string $key): void
            {
                $this->log[] = 'INCR:' . $key;
            }

            public function execute(): void
            {
                $this->log[] = 'EXEC';
            }
        };

        $pipeline = new RedisPipeline($driverMock);
        $pipeline->set('x', '1');
        $pipeline->incr('y');
        $pipeline->execute();

        $this->assertSame(['SET:x=1', 'INCR:y', 'EXEC'], $driverMock->log);
    }
}
