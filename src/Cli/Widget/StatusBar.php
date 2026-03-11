<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Widget;

use LPhenom\Redis\Cli\Terminal\Renderer;

/**
 * Status bar widget — bottom line with mode, hints, messages.
 */
final class StatusBar
{
    /** @var string */
    private string $mode;

    /** @var string */
    private string $message;

    /** @var string */
    private string $messageType; // 'info' | 'error' | 'success'

    /** @var array<int, string> */
    private array $hints;

    /** @var int */
    private int $messageExpiry;

    public function __construct()
    {
        $this->mode          = 'NORMAL';
        $this->message       = '';
        $this->messageType   = 'info';
        $this->hints         = [];
        $this->messageExpiry = 0;
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * Set hints shown on the right side.
     *
     * @param array<int, string> $hints e.g. ['↑↓ Navigate', 'Enter Select', 'q Quit']
     */
    public function setHints(array $hints): void
    {
        $this->hints = $hints;
    }

    public function info(string $message): void
    {
        $this->message       = $message;
        $this->messageType   = 'info';
        $this->messageExpiry = time() + 3;
    }

    public function success(string $message): void
    {
        $this->message       = $message;
        $this->messageType   = 'success';
        $this->messageExpiry = time() + 3;
    }

    public function error(string $message): void
    {
        $this->message       = $message;
        $this->messageType   = 'error';
        $this->messageExpiry = time() + 5;
    }

    public function clearMessage(): void
    {
        $this->message       = '';
        $this->messageExpiry = 0;
    }

    /**
     * Render status bar at given row.
     */
    public function render(Renderer $r, int $row): void
    {
        $width = $r->getWidth();

        $r->moveTo($row, 1);
        $r->write("\033[" . Renderer::BG_BRIGHT_BLACK . 'm');
        $r->write(str_repeat(' ', $width));
        $r->moveTo($row, 1);

        // Mode indicator
        $modeText = ' ' . $this->mode . ' ';
        $r->writeColored($modeText, Renderer::FG_BLACK, Renderer::BG_BRIGHT_CYAN, Renderer::ATTR_BOLD);
        $r->write(' ');

        // Message (check expiry)
        $now     = time();
        $message = $this->message;
        if ($message !== '' && $this->messageExpiry > 0 && $now > $this->messageExpiry) {
            $this->message = '';
            $message       = '';
        }

        if ($message !== '') {
            if ($this->messageType === 'error') {
                $r->writeColored($message, Renderer::FG_BRIGHT_WHITE, Renderer::BG_RED);
            } elseif ($this->messageType === 'success') {
                $r->writeColored($message, Renderer::FG_BLACK, Renderer::BG_BRIGHT_GREEN);
            } else {
                $r->writeColored($message, Renderer::FG_BRIGHT_WHITE);
            }
        }

        // Hints on the right
        if (count($this->hints) > 0) {
            $hintsText = implode('  ', $this->hints);
            $hintLen   = mb_strlen($hintsText);
            $modeLen   = mb_strlen($modeText) + 1 + mb_strlen($message);
            $spaces    = max(1, $width - $modeLen - $hintLen - 1);

            $r->moveTo($row, $width - $hintLen);
            $r->writeColored($hintsText, Renderer::FG_BRIGHT_BLACK);
        }

        $r->write("\033[0m");
    }
}
