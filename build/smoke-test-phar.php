#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PHAR smoke-test: require the built PHAR and verify autoloading works.
 *
 * Usage: php build/smoke-test-phar.php /path/to/lphenom-redis.phar
 */

$pharFile = $argv[1] ?? dirname(__DIR__) . '/lphenom-redis.phar';

if (!file_exists($pharFile)) {
    fwrite(STDERR, 'PHAR not found: ' . $pharFile . PHP_EOL);
    exit(1);
}

require $pharFile;

// Test 1: RedisConnectionConfig
$config = new \LPhenom\Redis\Connection\RedisConnectionConfig(
    '127.0.0.1',
    6379,
    '',
    0,
    2.0,
    false
);
if ($config->getHost() !== '127.0.0.1') {
    fwrite(STDERR, 'smoke-test: RedisConnectionConfig FAILED' . PHP_EOL);
    exit(1);
}
echo 'smoke-test: RedisConnectionConfig ok' . PHP_EOL;

// Test 2: RespClient + RespRedisClient instantiation
$resp   = new \LPhenom\Redis\Resp\RespClient('127.0.0.1', 6379, 2.0);
$client = new \LPhenom\Redis\Client\RespRedisClient($resp);
echo 'smoke-test: RespRedisClient ok' . PHP_EOL;

// Test 3: RedisPipeline with RespPipelineDriver
$driver   = new \LPhenom\Redis\Resp\RespPipelineDriver($resp);
$pipeline = new \LPhenom\Redis\Pipeline\RedisPipeline($driver);
$pipeline->set('key', 'value');
$pipeline->incr('counter');
echo 'smoke-test: RedisPipeline ok' . PHP_EOL;

// Test 4: RedisPublisher instantiation
$publisher = new \LPhenom\Redis\PubSub\RedisPublisher($client);
echo 'smoke-test: RedisPublisher ok' . PHP_EOL;

echo '=== PHAR smoke-test: OK ===' . PHP_EOL;
