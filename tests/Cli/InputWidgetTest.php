<?php

declare(strict_types=1);

namespace LPhenom\Redis\Tests\Cli;

use LPhenom\Redis\Cli\Widget\InputWidget;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Redis\Cli\Widget\InputWidget
 */
final class InputWidgetTest extends TestCase
{
    public function testInitialState(): void
    {
        $widget = new InputWidget('placeholder', 40);

        self::assertSame('', $widget->getValue());
        self::assertFalse($widget->isActive());
    }

    public function testSetAndGetValue(): void
    {
        $widget = new InputWidget();
        $widget->setValue('hello');

        self::assertSame('hello', $widget->getValue());
    }

    public function testAddChar(): void
    {
        $widget = new InputWidget();
        $widget->addChar('h');
        $widget->addChar('i');

        self::assertSame('hi', $widget->getValue());
    }

    public function testBackspace(): void
    {
        $widget = new InputWidget();
        $widget->setValue('hello');
        $widget->backspace();

        self::assertSame('hell', $widget->getValue());
    }

    public function testBackspaceOnEmpty(): void
    {
        $widget = new InputWidget();
        $widget->backspace();

        self::assertSame('', $widget->getValue());
    }

    public function testDelete(): void
    {
        $widget = new InputWidget();
        $widget->setValue('hello');
        // Move cursor to start
        $widget->moveCursorLeft();
        $widget->moveCursorLeft();
        $widget->moveCursorLeft();
        $widget->moveCursorLeft();
        $widget->moveCursorLeft();
        $widget->delete();

        self::assertSame('ello', $widget->getValue());
    }

    public function testClear(): void
    {
        $widget = new InputWidget();
        $widget->setValue('hello');
        $widget->clear();

        self::assertSame('', $widget->getValue());
    }

    public function testSetActive(): void
    {
        $widget = new InputWidget();
        $widget->setActive(true);

        self::assertTrue($widget->isActive());

        $widget->setActive(false);

        self::assertFalse($widget->isActive());
    }

    public function testAddCharInMiddle(): void
    {
        $widget = new InputWidget();
        $widget->setValue('hllo');
        // Move cursor left 3 positions (from end: after 'o')
        $widget->moveCursorLeft();
        $widget->moveCursorLeft();
        $widget->moveCursorLeft();
        // Cursor is after 'h', insert 'e'
        $widget->addChar('e');

        self::assertSame('hello', $widget->getValue());
    }

    public function testCursorMovement(): void
    {
        $widget = new InputWidget();
        $widget->setValue('abc');

        // Start at end (position 3)
        $widget->moveCursorLeft();  // position 2
        $widget->moveCursorLeft();  // position 1
        $widget->moveCursorLeft();  // position 0
        $widget->moveCursorLeft();  // still 0 (can't go further)

        // Insert at beginning
        $widget->addChar('X');
        self::assertSame('Xabc', $widget->getValue());
    }

    public function testCursorMovementRight(): void
    {
        $widget = new InputWidget();
        $widget->setValue('ab');
        // Move left then right back
        $widget->moveCursorLeft();
        $widget->moveCursorLeft();
        $widget->moveCursorRight();
        // Cursor at position 1, insert 'X'
        $widget->addChar('X');
        self::assertSame('aXb', $widget->getValue());
    }
}
