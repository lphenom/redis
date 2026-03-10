<?php

declare(strict_types=1);

namespace LPhenom\Redis\Tests\Connection;

use LPhenom\Redis\Connection\RedisConnectionConfig;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RedisConnectionConfig.
 */
final class RedisConnectionConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new RedisConnectionConfig();

        $this->assertSame('127.0.0.1', $config->getHost());
        $this->assertSame(6379, $config->getPort());
        $this->assertSame('', $config->getPassword());
        $this->assertSame(0, $config->getDatabase());
        $this->assertSame(2.0, $config->getTimeout());
        $this->assertFalse($config->isPersistent());
    }

    public function testCustomValues(): void
    {
        $config = new RedisConnectionConfig(
            host: 'redis.example.com',
            port: 6380,
            password: 'secret',
            database: 3,
            timeout: 5.0,
            persistent: true
        );

        $this->assertSame('redis.example.com', $config->getHost());
        $this->assertSame(6380, $config->getPort());
        $this->assertSame('secret', $config->getPassword());
        $this->assertSame(3, $config->getDatabase());
        $this->assertSame(5.0, $config->getTimeout());
        $this->assertTrue($config->isPersistent());
    }

    public function testIsImmutable(): void
    {
        $config = new RedisConnectionConfig(host: 'original');
        $this->assertSame('original', $config->getHost());

        // Create a new config — original is unchanged
        $config2 = new RedisConnectionConfig(host: 'changed');
        $this->assertSame('original', $config->getHost());
        $this->assertSame('changed', $config2->getHost());
    }
}
