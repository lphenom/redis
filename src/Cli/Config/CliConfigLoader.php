<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Config;

use LPhenom\Redis\Connection\RedisConnectionConfig;

/**
 * Loads Redis connection config for CLI tools.
 *
 * Priority (highest to lowest):
 *   1. CLI arguments (--host, --port, --password, --db)
 *   2. Environment variables (REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB)
 *   3. Config file (--config path/to/redis.php returning array)
 *   4. Defaults (127.0.0.1:6379)
  *
 * @lphenom-build none
 */
final class CliConfigLoader
{
    /**
     * Load config from CLI argv and environment.
     *
     * @param  array<int, string>    $argv
     * @return RedisConnectionConfig
     */
    public function load(array $argv): RedisConnectionConfig
    {
        // Parse CLI args
        $cliArgs = $this->parseArgs($argv);

        // Load from file if --config provided
        $fileConfig = [];
        $configPath = $cliArgs['config'] ?? null;
        if ($configPath !== null && $configPath !== '') {
            $fileConfig = $this->loadFromFile($configPath);
        }

        // Resolve host
        $host = $cliArgs['host'] ?? null;
        if ($host === null || $host === '') {
            $host = (string) (getenv('REDIS_HOST') ?: '');
        }
        if ($host === '') {
            $host = (string) ($fileConfig['host'] ?? '127.0.0.1');
        }

        // Resolve port
        $portStr = $cliArgs['port'] ?? null;
        if ($portStr === null || $portStr === '') {
            $portStr = (string) (getenv('REDIS_PORT') ?: '');
        }
        if ($portStr === '') {
            $portStr = (string) ($fileConfig['port'] ?? '6379');
        }
        $port = (int) $portStr;

        // Resolve password
        $password = $cliArgs['password'] ?? null;
        if ($password === null) {
            $password = (string) (getenv('REDIS_PASSWORD') ?: getenv('REDIS_AUTH') ?: '');
        }
        if ($password === '') {
            $password = (string) ($fileConfig['password'] ?? '');
        }

        // Resolve database
        $dbStr = $cliArgs['db'] ?? null;
        if ($dbStr === null || $dbStr === '') {
            $dbStr = (string) (getenv('REDIS_DB') ?: getenv('REDIS_DATABASE') ?: '');
        }
        if ($dbStr === '') {
            $dbStr = (string) ($fileConfig['database'] ?? '0');
        }
        $database = (int) $dbStr;

        return new RedisConnectionConfig(
            $host,
            $port > 0 ? $port : 6379,
            $password,
            $database >= 0 ? $database : 0
        );
    }

    /**
     * Parse --key=value and --key value CLI arguments.
     *
     * @param  array<int, string>    $argv
     * @return array<string, string>
     */
    private function parseArgs(array $argv): array
    {
        $result = [];
        $count  = count($argv);

        for ($i = 1; $i < $count; $i++) {
            $arg = $argv[$i];

            // --key=value
            if (substr($arg, 0, 2) === '--') {
                $part = substr($arg, 2);
                $eqPos = strpos($part, '=');
                if ($eqPos !== false) {
                    $key   = substr($part, 0, $eqPos);
                    $value = substr($part, $eqPos + 1);
                    $result[$key] = $value;
                } else {
                    // --key value
                    $next = $argv[$i + 1] ?? null;
                    if ($next !== null && substr($next, 0, 2) !== '--') {
                        $result[$part] = $next;
                        $i++;
                    } else {
                        $result[$part] = '1';
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Load config array from a PHP config file.
     * The file must return an array with keys: host, port, password, database.
     *
     * @param  string               $path
     * @return array<string, mixed>
     */
    private function loadFromFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $exception = null;
        $config    = [];

        try {
            $loaded = require $path;
            if (is_array($loaded)) {
                $config = $loaded;
            }
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            // Silently ignore config load errors in CLI — use defaults
            return [];
        }

        return $config;
    }
}
