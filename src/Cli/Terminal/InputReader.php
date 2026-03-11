<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Terminal;

/**
 * Non-blocking STDIN reader with special key detection.
 *
 * Reads raw bytes from STDIN and translates escape sequences
 * into KeyPress objects.
 */
final class InputReader
{
    /** @var resource */
    private $stdin;

    public function __construct()
    {
        $this->stdin = STDIN;
    }

    /**
     * Read next key press. Returns null if no input available (non-blocking).
     *
     * @param int $timeoutMs milliseconds to wait (0 = non-blocking, -1 = blocking)
     */
    public function read(int $timeoutMs = -1): ?KeyPress
    {
        $read  = [$this->stdin];
        $write = [];
        $err   = [];

        $tvSec  = 0;
        $tvUsec = 0;

        if ($timeoutMs < 0) {
            // Blocking — wait indefinitely
            $result = stream_select($read, $write, $err, null);
        } else {
            $tvSec  = (int) ($timeoutMs / 1000);
            $tvUsec = ($timeoutMs % 1000) * 1000;
            $result = stream_select($read, $write, $err, $tvSec, $tvUsec);
        }

        if ($result === false || $result === 0) {
            return null;
        }

        $byte = fread($this->stdin, 1);
        if ($byte === false || $byte === '') {
            return null;
        }

        // Handle Ctrl+C
        if ($byte === "\x03") {
            return new KeyPress(KeyPress::KEY_CTRL_C);
        }

        // Handle Ctrl+D
        if ($byte === "\x04") {
            return new KeyPress(KeyPress::KEY_CTRL_D);
        }

        // Handle Enter
        if ($byte === "\r" || $byte === "\n") {
            return new KeyPress(KeyPress::KEY_ENTER);
        }

        // Handle Tab
        if ($byte === "\t") {
            return new KeyPress(KeyPress::KEY_TAB);
        }

        // Handle Backspace (DEL = 0x7F or BS = 0x08)
        if ($byte === "\x7f" || $byte === "\x08") {
            return new KeyPress(KeyPress::KEY_BACKSPACE);
        }

        // Handle ESC sequence
        if ($byte === "\x1b") {
            return $this->readEscapeSequence();
        }

        // Regular character
        if (ord($byte) >= 32) {
            return new KeyPress(KeyPress::KEY_CHAR, $byte);
        }

        return null;
    }

    /**
     * Read an escape sequence after ESC byte was received.
     */
    private function readEscapeSequence(): KeyPress
    {
        $read  = [$this->stdin];
        $write = [];
        $err   = [];

        // Short wait for next byte
        $result = stream_select($read, $write, $err, 0, 50000);

        if ($result === false || $result === 0) {
            return new KeyPress(KeyPress::KEY_ESC);
        }

        $next = fread($this->stdin, 1);
        if ($next === false || $next === '') {
            return new KeyPress(KeyPress::KEY_ESC);
        }

        // ESC [ sequences (CSI)
        if ($next === '[') {
            return $this->readCsiSequence();
        }

        // ESC O sequences (SS3)
        if ($next === 'O') {
            $read2  = [$this->stdin];
            $write2 = [];
            $err2   = [];
            $result2 = stream_select($read2, $write2, $err2, 0, 50000);
            if ($result2 > 0) {
                $c = fread($this->stdin, 1);
                if ($c === false) {
                    return new KeyPress(KeyPress::KEY_ESC);
                }
                if ($c === 'P') {
                    return new KeyPress(KeyPress::KEY_CHAR, 'F1');
                }
                if ($c === 'Q') {
                    return new KeyPress(KeyPress::KEY_CHAR, 'F2');
                }
                if ($c === 'R') {
                    return new KeyPress(KeyPress::KEY_CHAR, 'F3');
                }
                if ($c === 'S') {
                    return new KeyPress(KeyPress::KEY_CHAR, 'F4');
                }
            }
        }

        return new KeyPress(KeyPress::KEY_ESC);
    }

    /**
     * Read CSI (Control Sequence Introducer) sequence after ESC [
     */
    private function readCsiSequence(): KeyPress
    {
        $seq = '';
        $limit = 8;
        $i = 0;

        while ($i < $limit) {
            $read  = [$this->stdin];
            $write = [];
            $err   = [];
            $result = stream_select($read, $write, $err, 0, 50000);

            if ($result === false || $result === 0) {
                break;
            }

            $c = fread($this->stdin, 1);
            if ($c === false) {
                break;
            }

            $seq .= $c;

            // Final byte of CSI sequence is in range 0x40–0x7E
            $ord = ord($c);
            if ($ord >= 0x40 && $ord <= 0x7E) {
                break;
            }

            $i++;
        }

        if ($seq === 'A') {
            return new KeyPress(KeyPress::KEY_UP);
        }
        if ($seq === 'B') {
            return new KeyPress(KeyPress::KEY_DOWN);
        }
        if ($seq === 'C') {
            return new KeyPress(KeyPress::KEY_RIGHT);
        }
        if ($seq === 'D') {
            return new KeyPress(KeyPress::KEY_LEFT);
        }
        if ($seq === 'H') {
            return new KeyPress(KeyPress::KEY_HOME);
        }
        if ($seq === 'F') {
            return new KeyPress(KeyPress::KEY_END);
        }
        if ($seq === '5~') {
            return new KeyPress(KeyPress::KEY_PAGE_UP);
        }
        if ($seq === '6~') {
            return new KeyPress(KeyPress::KEY_PAGE_DOWN);
        }
        if ($seq === '3~') {
            return new KeyPress(KeyPress::KEY_DELETE);
        }
        if ($seq === '1~') {
            return new KeyPress(KeyPress::KEY_HOME);
        }
        if ($seq === '4~') {
            return new KeyPress(KeyPress::KEY_END);
        }

        return new KeyPress(KeyPress::KEY_ESC);
    }
}
