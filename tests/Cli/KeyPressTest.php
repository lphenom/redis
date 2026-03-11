<?php

declare(strict_types=1);

namespace LPhenom\Redis\Tests\Cli;

use LPhenom\Redis\Cli\Terminal\KeyPress;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Redis\Cli\Terminal\KeyPress
 */
final class KeyPressTest extends TestCase
{
    public function testCharKeyCreation(): void
    {
        $key = new KeyPress(KeyPress::KEY_CHAR, 'a');

        self::assertSame(KeyPress::KEY_CHAR, $key->getType());
        self::assertSame('a', $key->getChar());
        self::assertTrue($key->isChar());
        self::assertFalse($key->isSpecial());
    }

    public function testSpecialKeyCreation(): void
    {
        $key = new KeyPress(KeyPress::KEY_ENTER);

        self::assertSame(KeyPress::KEY_ENTER, $key->getType());
        self::assertSame('', $key->getChar());
        self::assertFalse($key->isChar());
        self::assertTrue($key->isSpecial());
    }

    public function testIsMethod(): void
    {
        $key = new KeyPress(KeyPress::KEY_UP);

        self::assertTrue($key->is(KeyPress::KEY_UP));
        self::assertFalse($key->is(KeyPress::KEY_DOWN));
        self::assertFalse($key->is(KeyPress::KEY_CHAR));
    }

    public function testIsKeyMethod(): void
    {
        $key = new KeyPress(KeyPress::KEY_CHAR, 'q');

        self::assertTrue($key->isKey('q'));
        self::assertFalse($key->isKey('a'));
        self::assertFalse($key->isKey('Q'));
    }

    public function testIsKeyReturnsFalseForSpecialKeys(): void
    {
        $key = new KeyPress(KeyPress::KEY_ENTER);

        self::assertFalse($key->isKey('q'));
        self::assertFalse($key->isKey(''));
    }

    public function testAllSpecialKeyConstants(): void
    {
        $constants = [
            KeyPress::KEY_UP,
            KeyPress::KEY_DOWN,
            KeyPress::KEY_LEFT,
            KeyPress::KEY_RIGHT,
            KeyPress::KEY_ENTER,
            KeyPress::KEY_ESC,
            KeyPress::KEY_TAB,
            KeyPress::KEY_CTRL_C,
            KeyPress::KEY_CTRL_D,
            KeyPress::KEY_BACKSPACE,
            KeyPress::KEY_DELETE,
            KeyPress::KEY_PAGE_UP,
            KeyPress::KEY_PAGE_DOWN,
            KeyPress::KEY_HOME,
            KeyPress::KEY_END,
            KeyPress::KEY_CHAR,
        ];

        foreach ($constants as $const) {
            $key = new KeyPress($const);
            self::assertSame($const, $key->getType());
        }
    }
}
