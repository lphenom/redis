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
    host: '127.0.0.1',
    port: 6379
);
if ($config->getHost() !== '127.0.0.1') {
    fwrite(STDERR, 'smoke-test: RedisConnectionConfig FAILED' . PHP_EOL);
    exit(1);
}
echo 'smoke-test: RedisConnectionConfig ok' . PHP_EOL;

// Test 2: FfiRedisClient instantiation and NotImplementedException
$ffiClient = new \LPhenom\Redis\Client\FfiRedisClient();
$gotException = false;

try {
    $ffiClient->get('test');
} catch (\LPhenom\Redis\Exception\NotImplementedException $e) {
    $gotException = true;
} catch (\Throwable $e) {
    $gotException = false;
}

if (!$gotException) {
    fwrite(STDERR, 'smoke-test: FfiRedisClient FAILED' . PHP_EOL);
    exit(1);
}
echo 'smoke-test: FfiRedisClient ok' . PHP_EOL;

// Test 3: RedisPipeline in null mode
$pipeline = new \LPhenom\Redis\Pipeline\RedisPipeline(null);
$pipeline->set('key', 'value');
$pipeline->incr('counter');
$pipeline->execute();
echo 'smoke-test: RedisPipeline ok' . PHP_EOL;

// Test 4: RedisPublisher instantiation
$publisher = new \LPhenom\Redis\PubSub\RedisPublisher($ffiClient);
echo 'smoke-test: RedisPublisher ok' . PHP_EOL;

echo '=== PHAR smoke-test: OK ===' . PHP_EOL;
