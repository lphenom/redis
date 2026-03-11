<?php

declare(strict_types=1);

namespace LPhenom\Redis\Resp;

use LPhenom\Redis\Exception\RedisCommandException;
use LPhenom\Redis\Exception\RedisConnectionException;

/**
 * RESP (REdis Serialization Protocol) TCP client.
 *
 * Implements Redis wire protocol over a raw TCP socket.
 * Compatible with both PHP runtime and KPHP compiled binary.
 *
 * Uses stream_socket_client() — supported in KPHP.
 * Does NOT use fsockopen() — not available in KPHP.
 * Does NOT use stream_set_timeout() — not available in KPHP.
 *
 * No dependencies on ext-redis, no FFI, no hiredis.
 *
 * RESP protocol: https://redis.io/docs/reference/protocol-spec/
 *
 * KPHP-compatible:
 * - No constructor property promotion
 * - No readonly properties
 * - No callable types
 * - No str_starts_with/str_ends_with — uses substr()
 * - No resource type annotation — uses mixed (KPHP doesn't support resource type)
 * - Uses stream_socket_client(), fread(), fwrite(), fgets(), fclose() — all KPHP-supported
 * - try/catch with explicit catch blocks
 * - Explicit null checks, not isset+throw pattern
 *
 * @lphenom-build shared,kphp
 */
final class RespClient
{
    /**
     * TCP socket handle.
     * Typed as mixed for KPHP compatibility — resource type is not supported in KPHP.
     *
     * @var mixed
     */
    private mixed $socket;

    /** @var string */
    private string $host;

    /** @var int */
    private int $port;

    /** @var float */
    private float $timeout;

    /**
     * @param string $host
     * @param int    $port
     * @param float  $timeout seconds
     */
    public function __construct(string $host, int $port, float $timeout)
    {
        $this->host    = $host;
        $this->port    = $port;
        $this->timeout = $timeout;
        $this->socket  = null;
    }

    /**
     * Open TCP connection to Redis.
     *
     * Uses stream_socket_client() — available in both PHP and KPHP.
     *
     * @throws RedisConnectionException
     */
    public function connect(): void
    {
        $errorCode    = 0;
        $errorMessage = '';

        // stream_socket_client() is supported in KPHP (unlike fsockopen).
        // Returns mixed in KPHP type system.
        $socket = stream_socket_client(
            'tcp://' . $this->host . ':' . $this->port,
            $errorCode,
            $errorMessage,
            $this->timeout
        );

        if ($socket === false) {
            throw new RedisConnectionException(
                'Cannot connect to Redis ' . $this->host . ':' . $this->port
                . ' — ' . $errorMessage . ' (' . $errorCode . ')'
            );
        }

        $this->socket = $socket;
    }

    /**
     * Close TCP connection.
     */
    public function disconnect(): void
    {
        $s = $this->socket;
        if ($s !== null) {
            fclose($s);
            $this->socket = null;
        }
    }

    /**
     * Send an AUTH command.
     *
     * @param  string                $password
     * @throws RedisCommandException
     */
    public function auth(string $password): void
    {
        $reply = $this->command(['AUTH', $password]);
        if ($reply !== 'OK') {
            throw new RedisCommandException('Redis AUTH failed');
        }
    }

    /**
     * Send a SELECT command.
     *
     * @param  int                   $database
     * @throws RedisCommandException
     */
    public function select(int $database): void
    {
        $reply = $this->command(['SELECT', (string) $database]);
        if ($reply !== 'OK') {
            throw new RedisCommandException('Redis SELECT ' . $database . ' failed');
        }
    }

    /**
     * Execute a Redis command and return the reply.
     *
     * Returns:
     *   - string  for simple strings (+OK) and bulk strings ($...)
     *   - null    for null bulk strings ($-1)
     *   - int     for integers (:...)
     *
     * @param  array<int, string>    $args command + arguments
     * @throws RedisCommandException
     * @return string|int|null
     */
    public function command(array $args): mixed
    {
        $this->send($args);
        return $this->readReply();
    }

    /**
     * Send RESP array to the socket.
     *
     * @param  array<int, string>    $args
     * @throws RedisCommandException
     */
    private function send(array $args): void
    {
        $s = $this->socket;
        if ($s === null) {
            throw new RedisCommandException('Redis: not connected');
        }

        $parts = '*' . count($args) . "\r\n";
        foreach ($args as $arg) {
            $parts .= '$' . strlen($arg) . "\r\n" . $arg . "\r\n";
        }

        $written = fwrite($s, $parts);
        if ($written === false) {
            throw new RedisCommandException('Redis: failed to write to socket');
        }
    }

    /**
     * Read and parse a single RESP reply from the socket.
     *
     * @throws RedisCommandException
     * @return string|int|null
     */
    private function readReply(): mixed
    {
        $s = $this->socket;
        if ($s === null) {
            throw new RedisCommandException('Redis: not connected');
        }

        $line = fgets($s, 4096);
        if ($line === false) {
            throw new RedisCommandException('Redis: failed to read from socket');
        }

        $line = rtrim((string) $line, "\r\n");
        if ($line === '') {
            throw new RedisCommandException('Redis: empty reply');
        }

        $type    = $line[0];
        $payload = substr($line, 1);

        if ($type === '+') {
            // Simple string: +OK
            return $payload;
        }

        if ($type === '-') {
            // Error: -ERR message
            throw new RedisCommandException('Redis error: ' . $payload);
        }

        if ($type === ':') {
            // Integer: :42
            return (int) $payload;
        }

        if ($type === '$') {
            // Bulk string: $6\r\nfoobar\r\n or $-1 (null)
            $length = (int) $payload;
            if ($length === -1) {
                return null;
            }
            $data      = '';
            $remaining = $length + 2; // +2 for \r\n
            while ($remaining > 0) {
                $chunk = fread($s, $remaining);
                if ($chunk === false) {
                    throw new RedisCommandException('Redis: failed to read bulk data');
                }
                $data      .= (string) $chunk;
                $remaining -= strlen((string) $chunk);
            }
            return rtrim($data, "\r\n");
        }

        if ($type === '*') {
            // Array: for BLPOP — read elements, return value at index 1
            $count = (int) $payload;
            if ($count === -1) {
                return null;
            }
            $result = null;
            for ($i = 0; $i < $count; $i++) {
                $element = $this->readReply();
                if ($i === 1) {
                    $result = $element !== null ? (string) $element : null;
                }
            }
            return $result;
        }

        throw new RedisCommandException('Redis: unknown reply type "' . $type . '"');
    }
}
