<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Terminal;

/**
 * Buffered ANSI renderer.
 *
 * Collects output in a buffer and flushes at once to minimize
 * terminal flickering. Provides high-level drawing primitives
 * using ANSI escape codes and Unicode box-drawing characters.
  *
 * @lphenom-build none
 */
final class Renderer
{
    // Color constants (foreground)
    public const FG_BLACK   = 30;
    public const FG_RED     = 31;
    public const FG_GREEN   = 32;
    public const FG_YELLOW  = 33;
    public const FG_BLUE    = 34;
    public const FG_MAGENTA = 35;
    public const FG_CYAN    = 36;
    public const FG_WHITE   = 37;
    public const FG_DEFAULT = 39;
    public const FG_BRIGHT_BLACK   = 90;
    public const FG_BRIGHT_RED     = 91;
    public const FG_BRIGHT_GREEN   = 92;
    public const FG_BRIGHT_YELLOW  = 93;
    public const FG_BRIGHT_BLUE    = 94;
    public const FG_BRIGHT_MAGENTA = 95;
    public const FG_BRIGHT_CYAN    = 96;
    public const FG_BRIGHT_WHITE   = 97;

    // Color constants (background)
    public const BG_BLACK   = 40;
    public const BG_RED     = 41;
    public const BG_GREEN   = 42;
    public const BG_YELLOW  = 43;
    public const BG_BLUE    = 44;
    public const BG_MAGENTA = 45;
    public const BG_CYAN    = 46;
    public const BG_WHITE   = 47;
    public const BG_DEFAULT = 49;
    public const BG_BRIGHT_BLACK   = 100;
    public const BG_BRIGHT_RED     = 101;
    public const BG_BRIGHT_GREEN   = 102;
    public const BG_BRIGHT_YELLOW  = 103;
    public const BG_BRIGHT_BLUE    = 104;
    public const BG_BRIGHT_MAGENTA = 105;
    public const BG_BRIGHT_CYAN    = 106;
    public const BG_BRIGHT_WHITE   = 107;

    // Attributes
    public const ATTR_RESET  = 0;
    public const ATTR_BOLD   = 1;
    public const ATTR_DIM    = 2;
    public const ATTR_ITALIC = 3;
    public const ATTR_UNDER  = 4;
    public const ATTR_BLINK  = 5;
    public const ATTR_REVERSE = 7;

    // Box-drawing characters
    public const BOX_TL = '┌';
    public const BOX_TR = '┐';
    public const BOX_BL = '└';
    public const BOX_BR = '┘';
    public const BOX_H  = '─';
    public const BOX_V  = '│';
    public const BOX_TM = '┬';
    public const BOX_BM = '┴';
    public const BOX_ML = '├';
    public const BOX_MR = '┤';
    public const BOX_MC = '┼';

    /** @var string */
    private string $buffer = '';

    /** @var int */
    private int $width;

    /** @var int */
    private int $height;

    public function __construct(int $width, int $height)
    {
        $this->width  = $width;
        $this->height = $height;
    }

    /**
     * Move cursor to position (1-based row, col).
     */
    public function moveTo(int $row, int $col): self
    {
        $this->buffer .= "\033[" . $row . ';' . $col . 'H';
        return $this;
    }

    /**
     * Clear entire screen and move cursor to top-left.
     */
    public function clear(): self
    {
        $this->buffer .= "\033[2J\033[H";
        return $this;
    }

    /**
     * Clear from cursor to end of line.
     */
    public function clearLine(): self
    {
        $this->buffer .= "\033[K";
        return $this;
    }

    /**
     * Write text at current cursor position.
     */
    public function write(string $text): self
    {
        $this->buffer .= $text;
        return $this;
    }

    /**
     * Write text with ANSI color/attribute codes.
     *
     * @param string   $text
     * @param int      $fg   Foreground color (FG_* constant)
     * @param int      $bg   Background color (BG_* constant)
     * @param int|null $attr Attribute (ATTR_* constant), null = none
     */
    public function writeColored(string $text, int $fg = self::FG_DEFAULT, int $bg = self::BG_DEFAULT, ?int $attr = null): self
    {
        $code = $fg . ';' . $bg;
        if ($attr !== null) {
            $code = $attr . ';' . $code;
        }
        $this->buffer .= "\033[" . $code . 'm' . $text . "\033[0m";
        return $this;
    }

    /**
     * Write text centered in a field of given width.
     */
    public function writeCentered(string $text, int $width, int $fg = self::FG_DEFAULT, int $bg = self::BG_DEFAULT): self
    {
        $len     = mb_strlen($text);
        $padding = max(0, $width - $len);
        $left    = (int) ($padding / 2);
        $right   = $padding - $left;
        $padded  = str_repeat(' ', $left) . $text . str_repeat(' ', $right);
        return $this->writeColored($padded, $fg, $bg);
    }

    /**
     * Write text left-aligned in a field of given width (truncated if too long).
     */
    public function writeFixed(string $text, int $width, int $fg = self::FG_DEFAULT, int $bg = self::BG_DEFAULT): self
    {
        $len = mb_strlen($text);
        if ($len > $width) {
            $text = mb_substr($text, 0, max(0, $width - 1)) . '…';
        } else {
            $text = $text . str_repeat(' ', $width - $len);
        }
        return $this->writeColored($text, $fg, $bg);
    }

    /**
     * Draw a horizontal line at given row.
     */
    public function hLine(int $row, int $col, int $length, string $char = self::BOX_H): self
    {
        $this->moveTo($row, $col);
        $this->buffer .= str_repeat($char, $length);
        return $this;
    }

    /**
     * Draw a box border.
     *
     * @param int $row    Top-left row (1-based)
     * @param int $col    Top-left column (1-based)
     * @param int $width  Box width (including borders)
     * @param int $height Box height (including borders)
     */
    public function drawBox(int $row, int $col, int $width, int $height, int $fg = self::FG_BRIGHT_BLUE): self
    {
        $inner = $width - 2;

        // Top border
        $this->moveTo($row, $col);
        $this->writeColored(self::BOX_TL . str_repeat(self::BOX_H, $inner) . self::BOX_TR, $fg);

        // Side borders
        for ($r = $row + 1; $r < $row + $height - 1; $r++) {
            $this->moveTo($r, $col);
            $this->writeColored(self::BOX_V, $fg);
            $this->moveTo($r, $col + $width - 1);
            $this->writeColored(self::BOX_V, $fg);
        }

        // Bottom border
        $this->moveTo($row + $height - 1, $col);
        $this->writeColored(self::BOX_BL . str_repeat(self::BOX_H, $inner) . self::BOX_BR, $fg);

        return $this;
    }

    /**
     * Draw a box with a title in the top border.
     */
    public function drawBoxWithTitle(int $row, int $col, int $width, int $height, string $title, int $fg = self::FG_BRIGHT_BLUE): self
    {
        $this->drawBox($row, $col, $width, $height, $fg);

        // Draw title in top border
        $titleFull = ' ' . $title . ' ';
        $titleLen  = mb_strlen($titleFull);
        $titleCol  = $col + (int) (($width - $titleLen) / 2);
        $this->moveTo($row, $titleCol);
        $this->writeColored($titleFull, self::FG_BRIGHT_WHITE, self::BG_DEFAULT, self::ATTR_BOLD);

        return $this;
    }

    /**
     * Draw a horizontal separator line inside a box.
     */
    public function drawSeparator(int $row, int $col, int $width, int $fg = self::FG_BRIGHT_BLUE): self
    {
        $inner = $width - 2;
        $this->moveTo($row, $col);
        $this->writeColored(self::BOX_ML . str_repeat(self::BOX_H, $inner) . self::BOX_MR, $fg);
        return $this;
    }

    /**
     * Flush buffer to STDOUT.
     */
    public function flush(): void
    {
        if ($this->buffer !== '') {
            echo $this->buffer;
            $this->buffer = '';
        }
    }

    /**
     * Reset all attributes.
     */
    public function reset(): self
    {
        $this->buffer .= "\033[0m";
        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Update dimensions (on terminal resize).
     */
    public function setDimensions(int $width, int $height): void
    {
        $this->width  = $width;
        $this->height = $height;
    }
}
