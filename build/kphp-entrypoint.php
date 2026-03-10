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
 *   - src/Connection/RedisConnector.php  (uses ext-redis internally for connectPhpRedis)
 *   - src/Pipeline/PhpRedisPipelineDriver.php
 *   - src/PubSub/RedisSubscriber.php
 * These are PHP-runtime-only files.
 *
 * RespRedisClient + RespClient ARE included — they use only stream_socket_client/fread/fwrite/fgets
 * which are fully supported in KPHP compiled binary (unlike fsockopen which is NOT in KPHP).
 *
 * This file also serves as a smoke-test for KPHP binary verification.
 */

// Exceptions (no dependencies)
require_once __DIR__ . '/../src/Exception/RedisException.php';
require_once __DIR__ . '/../src/Exception/RedisConnectionException.php';
require_once __DIR__ . '/../src/Exception/RedisCommandException.php';
require_once __DIR__ . '/../src/Exception/NotImplementedException.php';

// Pipeline interface + KPHP-compatible pipeline (no \Redis dependency)
require_once __DIR__ . '/../src/Pipeline/RedisPipelineDriverInterface.php';
require_once __DIR__ . '/../src/Pipeline/RedisPipeline.php';

// Client interface (depends on: RedisPipeline)
require_once __DIR__ . '/../src/Client/RedisClientInterface.php';

// FfiRedisClient — placeholder stub
require_once __DIR__ . '/../src/Client/FfiRedisClient.php';

// RESP protocol client — works in KPHP (uses fsockopen/fread/fwrite)
require_once __DIR__ . '/../src/Resp/RespClient.php';
require_once __DIR__ . '/../src/Resp/RespPipelineDriver.php';
require_once __DIR__ . '/../src/Client/RespRedisClient.php';

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

// Test 2: RespClient can be instantiated (no actual connection here)
$resp = new \LPhenom\Redis\Resp\RespClient('127.0.0.1', 6379, 2.0);
echo 'smoke-test: RespClient instantiation ok' . PHP_EOL;

// Test 3: RespRedisClient can be instantiated
$respClient = new \LPhenom\Redis\Client\RespRedisClient($resp);
echo 'smoke-test: RespRedisClient instantiation ok' . PHP_EOL;

// Test 4: RedisPipeline with RespPipelineDriver (no connection needed for buffering)
$pipelineDriver = new \LPhenom\Redis\Resp\RespPipelineDriver($resp);
$pipeline       = new \LPhenom\Redis\Pipeline\RedisPipeline($pipelineDriver);
$pipeline->set('a', '1');
$pipeline->incr('counter');
// Note: pipeline->execute() would attempt network call — skip in smoke-test
echo 'smoke-test: RespPipelineDriver + RedisPipeline ok' . PHP_EOL;

// Test 5: FfiRedisClient stub still throws NotImplementedException
$ffiClient    = new \LPhenom\Redis\Client\FfiRedisClient();
$gotException = false;

$ex = null;
try {
    $ffiClient->get('key');
} catch (\LPhenom\Redis\Exception\NotImplementedException $e) {
    $ex           = $e;
    $gotException = true;
} catch (\Throwable $e) {
    $ex           = $e;
    $gotException = false;
}

if (!$gotException) {
    echo 'FAIL: FfiRedisClient::get() should throw NotImplementedException' . PHP_EOL;
    exit(1);
}

echo 'smoke-test: FfiRedisClient throws NotImplementedException ok' . PHP_EOL;

// Test 6: RedisPublisher (using RespRedisClient as base)
$publisher = new \LPhenom\Redis\PubSub\RedisPublisher($respClient);
echo 'smoke-test: RedisPublisher instantiation ok' . PHP_EOL;

echo '=== KPHP smoke-test: ALL OK ===' . PHP_EOL;
