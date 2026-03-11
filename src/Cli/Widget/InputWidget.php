<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Widget;

use LPhenom\Redis\Cli\Terminal\Renderer;

/**
 * Single-line text input widget with cursor and editing support.
  *
 * @lphenom-build none
 */
final class InputWidget
{
    /** @var string */
    private string $value;

    /** @var string */
    private string $placeholder;

    /** @var int */
    private int $cursor;

    /** @var int */
    private int $width;

    /** @var bool */
    private bool $active;

    public function __construct(string $placeholder = '', int $width = 40)
    {
        $this->value       = '';
        $this->placeholder = $placeholder;
        $this->cursor      = 0;
        $this->width       = $width;
        $this->active      = false;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value  = $value;
        $this->cursor = mb_strlen($value);
    }

    public function clear(): void
    {
        $this->value  = '';
        $this->cursor = 0;
    }

    /**
     * Handle a printable character input.
     */
    public function addChar(string $char): void
    {
        $len = mb_strlen($this->value);
        $before = mb_substr($this->value, 0, $this->cursor);
        $after  = mb_substr($this->value, $this->cursor);
        $this->value  = $before . $char . $after;
        $this->cursor = min($len + 1, $this->cursor + 1);
    }

    /**
     * Handle backspace.
     */
    public function backspace(): void
    {
        if ($this->cursor > 0) {
            $before = mb_substr($this->value, 0, $this->cursor - 1);
            $after  = mb_substr($this->value, $this->cursor);
            $this->value  = $before . $after;
            $this->cursor--;
        }
    }

    /**
     * Handle delete key (delete char after cursor).
     */
    public function delete(): void
    {
        $len = mb_strlen($this->value);
        if ($this->cursor < $len) {
            $before = mb_substr($this->value, 0, $this->cursor);
            $after  = mb_substr($this->value, $this->cursor + 1);
            $this->value = $before . $after;
        }
    }

    public function moveCursorLeft(): void
    {
        if ($this->cursor > 0) {
            $this->cursor--;
        }
    }

    public function moveCursorRight(): void
    {
        $len = mb_strlen($this->value);
        if ($this->cursor < $len) {
            $this->cursor++;
        }
    }

    /**
     * Render input field at (row, col).
     */
    public function render(Renderer $r, int $row, int $col): void
    {
        $value   = $this->value;
        $len     = mb_strlen($value);
        $width   = $this->width;
        $visible = $width - 2; // inner width

        // Scroll view to keep cursor visible
        $viewStart = 0;
        if ($this->cursor >= $visible) {
            $viewStart = $this->cursor - $visible + 1;
        }

        $visibleText = mb_substr($value, $viewStart, $visible);
        $padding     = str_repeat(' ', max(0, $visible - mb_strlen($visibleText)));
        $display     = $visibleText . $padding;

        $borderFg = $this->active ? Renderer::FG_BRIGHT_YELLOW : Renderer::FG_BRIGHT_BLUE;

        $r->moveTo($row, $col);
        $r->writeColored('▌', $borderFg);

        if ($this->active) {
            // Show cursor position
            $cursorPos = $this->cursor - $viewStart;
            $before    = mb_substr($display, 0, $cursorPos);
            $cursorCh  = mb_substr($display, $cursorPos, 1);
            if ($cursorCh === '') {
                $cursorCh = ' ';
            }
            $after = mb_substr($display, $cursorPos + 1);

            $r->writeColored($before, Renderer::FG_WHITE, Renderer::BG_DEFAULT);
            $r->writeColored($cursorCh, Renderer::FG_BLACK, Renderer::BG_BRIGHT_WHITE);
            $r->writeColored($after, Renderer::FG_WHITE, Renderer::BG_DEFAULT);
        } elseif ($len === 0 && $this->placeholder !== '') {
            $ph = mb_substr($this->placeholder, 0, $visible);
            $phPad = str_repeat(' ', max(0, $visible - mb_strlen($ph)));
            $r->writeColored($ph . $phPad, Renderer::FG_BRIGHT_BLACK, Renderer::BG_DEFAULT);
        } else {
            $r->writeColored($display, Renderer::FG_WHITE, Renderer::BG_DEFAULT);
        }

        $r->writeColored('▐', $borderFg);
    }
}
