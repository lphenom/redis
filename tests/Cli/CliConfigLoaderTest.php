<?php

declare(strict_types=1);

namespace LPhenom\Redis\Tests\Cli;

use LPhenom\Redis\Cli\Config\CliConfigLoader;
use LPhenom\Redis\Connection\RedisConnectionConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Redis\Cli\Config\CliConfigLoader
 */
final class CliConfigLoaderTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear env vars to avoid interference
        putenv('REDIS_HOST');
        putenv('REDIS_PORT');
        putenv('REDIS_PASSWORD');
        putenv('REDIS_DB');
        putenv('REDIS_AUTH');
        putenv('REDIS_DATABASE');
    }

    public function testDefaultsWhenNoArgsOrEnv(): void
    {
        $loader = new CliConfigLoader();
        $config = $loader->load(['redis-tui']);

        self::assertSame('127.0.0.1', $config->getHost());
        self::assertSame(6379, $config->getPort());
        self::assertSame('', $config->getPassword());
        self::assertSame(0, $config->getDatabase());
    }

    public function testCliArgsParsedCorrectly(): void
    {
        $loader = new CliConfigLoader();
        $config = $loader->load([
            'redis-tui',
            '--host=192.168.1.100',
            '--port=6380',
            '--password=secret',
            '--db=3',
        ]);

        self::assertSame('192.168.1.100', $config->getHost());
        self::assertSame(6380, $config->getPort());
        self::assertSame('secret', $config->getPassword());
        self::assertSame(3, $config->getDatabase());
    }

    public function testCliArgsWithSpace(): void
    {
        $loader = new CliConfigLoader();
        $config = $loader->load([
            'redis-tui',
            '--host',
            '10.0.0.1',
            '--port',
            '6381',
        ]);

        self::assertSame('10.0.0.1', $config->getHost());
        self::assertSame(6381, $config->getPort());
    }

    public function testEnvVarsUsedWhenNoCliArgs(): void
    {
        putenv('REDIS_HOST=redis.example.com');
        putenv('REDIS_PORT=6382');
        putenv('REDIS_PASSWORD=envpass');
        putenv('REDIS_DB=2');

        $loader = new CliConfigLoader();
        $config = $loader->load(['redis-tui']);

        self::assertSame('redis.example.com', $config->getHost());
        self::assertSame(6382, $config->getPort());
        self::assertSame('envpass', $config->getPassword());
        self::assertSame(2, $config->getDatabase());

        // Cleanup
        putenv('REDIS_HOST');
        putenv('REDIS_PORT');
        putenv('REDIS_PASSWORD');
        putenv('REDIS_DB');
    }

    public function testCliArgsTakePrecedenceOverEnv(): void
    {
        putenv('REDIS_HOST=env-host');

        $loader = new CliConfigLoader();
        $config = $loader->load(['redis-tui', '--host=cli-host']);

        self::assertSame('cli-host', $config->getHost());

        putenv('REDIS_HOST');
    }

    public function testInvalidPortFallsBackToDefault(): void
    {
        $loader = new CliConfigLoader();
        $config = $loader->load(['redis-tui', '--port=0']);

        // Port 0 → falls back to 6379
        self::assertSame(6379, $config->getPort());
    }

    public function testReturnsRedisConnectionConfigInstance(): void
    {
        $loader = new CliConfigLoader();
        $config = $loader->load(['redis-tui']);

        self::assertInstanceOf(RedisConnectionConfig::class, $config);
    }
}
