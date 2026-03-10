<?php

declare(strict_types=1);

/**
 * KPHP entrypoint for lphenom/redis.
 *
 * KPHP does not support Composer PSR-4 autoloading.
 * All source files must be explicitly required in dependency order:
 * Exceptions → Interfaces → Value objects → Implementations
 *
 * NOTE: Only KPHP-compatible files are included.
 * Files requiring ext-redis (\Redis class) are NOT included:
 *   - src/Client/PhpRedisClient.php
 *   - src/Connection/RedisConnector.php
 *   - src/Pipeline/PhpRedisPipelineDriver.php
 *   - src/PubSub/RedisSubscriber.php
 * These are PHP-runtime-only files.
 *
 * This file also serves as a smoke-test for KPHP binary verification.
 */

// Exceptions (no dependencies)
require_once __DIR__ . '/../src/Exception/RedisException.php';
require_once __DIR__ . '/../src/Exception/RedisConnectionException.php';
require_once __DIR__ . '/../src/Exception/RedisCommandException.php';
require_once __DIR__ . '/../src/Exception/NotImplementedException.php';

// Pipeline interface + KPHP-compatible stub (no \Redis dependency)
require_once __DIR__ . '/../src/Pipeline/RedisPipelineDriverInterface.php';
require_once __DIR__ . '/../src/Pipeline/RedisPipeline.php';

// Client interface (depends on: RedisPipeline)
require_once __DIR__ . '/../src/Client/RedisClientInterface.php';

// FfiRedisClient — KPHP stub (no ext-redis needed)
require_once __DIR__ . '/../src/Client/FfiRedisClient.php';

// Connection config (value object, no ext-redis dependency)
require_once __DIR__ . '/../src/Connection/RedisConnectionConfig.php';

// PubSub publisher (depends on: RedisClientInterface)
require_once __DIR__ . '/../src/PubSub/MessageHandlerInterface.php';
require_once __DIR__ . '/../src/PubSub/RedisPublisher.php';

// =============================================================================
// Smoke-test: verify the package loads and basic types work
// =============================================================================

// Test 1: RedisConnectionConfig value object
$config = new \LPhenom\Redis\Connection\RedisConnectionConfig(
    '127.0.0.1',
    6379,
    '',
    0,
    2.0,
    false
);

if ($config->getHost() !== '127.0.0.1') {
    echo 'FAIL: RedisConnectionConfig host' . PHP_EOL;
    exit(1);
}

if ($config->getPort() !== 6379) {
    echo 'FAIL: RedisConnectionConfig port' . PHP_EOL;
    exit(1);
}

echo 'smoke-test: RedisConnectionConfig ok' . PHP_EOL;

// Test 2: FfiRedisClient throws NotImplementedException
$ffiClient  = new \LPhenom\Redis\Client\FfiRedisClient();
$gotException = false;

$ex = null;
try {
    $ffiClient->get('key');
} catch (\LPhenom\Redis\Exception\NotImplementedException $e) {
    $ex = $e;
    $gotException = true;
} catch (\Throwable $e) {
    $ex = $e;
    $gotException = false;
}

if (!$gotException) {
    echo 'FAIL: FfiRedisClient::get() should throw NotImplementedException' . PHP_EOL;
    exit(1);
}

echo 'smoke-test: FfiRedisClient throws NotImplementedException ok' . PHP_EOL;

// Test 3: RedisPipeline (null driver — no ext-redis needed)
$pipeline = new \LPhenom\Redis\Pipeline\RedisPipeline(null);
$pipeline->set('a', '1');
$pipeline->set('b', '2', 60);
$pipeline->incr('counter');
$pipeline->execute();

echo 'smoke-test: RedisPipeline null driver mode ok' . PHP_EOL;

// Test 4: RedisPublisher (using FfiRedisClient as base)
$publisher = new \LPhenom\Redis\PubSub\RedisPublisher($ffiClient);
// Do not call publish — would throw. Just verify instantiation.
echo 'smoke-test: RedisPublisher instantiation ok' . PHP_EOL;

echo '=== KPHP smoke-test: ALL OK ===' . PHP_EOL;
