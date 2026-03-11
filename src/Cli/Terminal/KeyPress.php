<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Terminal;

/**
 * Represents a single key press event from the terminal.
  *
 * @lphenom-build none
 */
final class KeyPress
{
    // Special key constants
    public const KEY_UP     = 'UP';
    public const KEY_DOWN   = 'DOWN';
    public const KEY_LEFT   = 'LEFT';
    public const KEY_RIGHT  = 'RIGHT';
    public const KEY_ENTER  = 'ENTER';
    public const KEY_ESC    = 'ESC';
    public const KEY_TAB    = 'TAB';
    public const KEY_CTRL_C = 'CTRL_C';
    public const KEY_CTRL_D = 'CTRL_D';
    public const KEY_BACKSPACE = 'BACKSPACE';
    public const KEY_DELETE = 'DELETE';
    public const KEY_PAGE_UP   = 'PAGE_UP';
    public const KEY_PAGE_DOWN = 'PAGE_DOWN';
    public const KEY_HOME   = 'HOME';
    public const KEY_END    = 'END';
    public const KEY_CHAR   = 'CHAR';

    /** @var string */
    private string $type;

    /** @var string */
    private string $char;

    public function __construct(string $type, string $char = '')
    {
        $this->type = $type;
        $this->char = $char;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getChar(): string
    {
        return $this->char;
    }

    public function isChar(): bool
    {
        return $this->type === self::KEY_CHAR;
    }

    public function isSpecial(): bool
    {
        return $this->type !== self::KEY_CHAR;
    }

    public function is(string $type): bool
    {
        return $this->type === $type;
    }

    /**
     * Check if pressed char matches given character.
     */
    public function isKey(string $char): bool
    {
        return $this->type === self::KEY_CHAR && $this->char === $char;
    }
}
