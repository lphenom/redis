<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Terminal;

/**
 * Low-level terminal control: raw mode, size, cleanup.
 *
 * Uses ANSI escape codes and stty for terminal manipulation.
 * No ext-ncurses dependency.
 */
final class Terminal
{
    /** @var bool */
    private bool $rawMode = false;

    /** @var string */
    private string $savedStty = '';

    /**
     * Enter raw mode: disable echo, line buffering.
     */
    public function enableRaw(): void
    {
        $exception = null;

        try {
            $saved = (string) shell_exec('stty -g 2>/dev/null');
            $this->savedStty = trim($saved);
            system('stty -echo -icanon min 1 time 0 2>/dev/null');
            $this->rawMode = true;
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw new \RuntimeException('Failed to enable raw mode: ' . $exception->getMessage());
        }
    }

    /**
     * Restore terminal to previous state.
     */
    public function disableRaw(): void
    {
        if (!$this->rawMode) {
            return;
        }

        if ($this->savedStty !== '') {
            system('stty ' . escapeshellarg($this->savedStty) . ' 2>/dev/null');
        } else {
            system('stty echo icanon 2>/dev/null');
        }

        $this->rawMode = false;
    }

    /**
     * Get terminal width in columns.
     */
    public function getWidth(): int
    {
        $cols = (int) shell_exec('tput cols 2>/dev/null');
        if ($cols <= 0) {
            $cols = 80;
        }
        return $cols;
    }

    /**
     * Get terminal height in rows.
     */
    public function getHeight(): int
    {
        $rows = (int) shell_exec('tput lines 2>/dev/null');
        if ($rows <= 0) {
            $rows = 24;
        }
        return $rows;
    }

    /**
     * Clear the entire screen.
     */
    public function clear(): void
    {
        echo "\033[2J\033[H";
    }

    /**
     * Hide the cursor.
     */
    public function hideCursor(): void
    {
        echo "\033[?25l";
    }

    /**
     * Show the cursor.
     */
    public function showCursor(): void
    {
        echo "\033[?25h";
    }

    /**
     * Move cursor to column 1 of given row (1-based).
     */
    public function moveTo(int $row, int $col): void
    {
        echo "\033[" . $row . ';' . $col . 'H';
    }

    public function isRaw(): bool
    {
        return $this->rawMode;
    }
}
