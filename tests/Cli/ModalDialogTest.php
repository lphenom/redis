<?php

declare(strict_types=1);

namespace LPhenom\Redis\Tests\Cli;

use LPhenom\Redis\Cli\Terminal\KeyPress;
use LPhenom\Redis\Cli\Widget\ModalDialog;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Redis\Cli\Widget\ModalDialog
 */
final class ModalDialogTest extends TestCase
{
    public function testInitiallyNotVisible(): void
    {
        $dialog = new ModalDialog();

        self::assertFalse($dialog->isVisible());
    }

    public function testShowConfirmMakesVisible(): void
    {
        $dialog = new ModalDialog();
        $dialog->showConfirm('Test', 'Are you sure?');

        self::assertTrue($dialog->isVisible());
    }

    public function testShowInputMakesVisible(): void
    {
        $dialog = new ModalDialog();
        $dialog->showInput('Enter value', 'Type here:', 'placeholder');

        self::assertTrue($dialog->isVisible());
    }

    public function testConfirmWithYKey(): void
    {
        $dialog = new ModalDialog();
        $dialog->showConfirm('Test', 'Delete?');

        $closed = $dialog->handleKey(new KeyPress(KeyPress::KEY_CHAR, 'y'));

        self::assertTrue($closed);
        self::assertTrue($dialog->isConfirmed());
        self::assertFalse($dialog->isVisible());
    }

    public function testConfirmWithEnterKey(): void
    {
        $dialog = new ModalDialog();
        $dialog->showConfirm('Test', 'Delete?');

        $closed = $dialog->handleKey(new KeyPress(KeyPress::KEY_ENTER));

        self::assertTrue($closed);
        self::assertTrue($dialog->isConfirmed());
    }

    public function testCancelWithNKey(): void
    {
        $dialog = new ModalDialog();
        $dialog->showConfirm('Test', 'Delete?');

        $closed = $dialog->handleKey(new KeyPress(KeyPress::KEY_CHAR, 'n'));

        self::assertTrue($closed);
        self::assertFalse($dialog->isConfirmed());
        self::assertFalse($dialog->isVisible());
    }

    public function testCancelWithEscKey(): void
    {
        $dialog = new ModalDialog();
        $dialog->showConfirm('Test', 'Delete?');

        $closed = $dialog->handleKey(new KeyPress(KeyPress::KEY_ESC));

        self::assertTrue($closed);
        self::assertFalse($dialog->isConfirmed());
    }

    public function testInputDialogTypingAndConfirm(): void
    {
        $dialog = new ModalDialog();
        $dialog->showInput('Enter', 'Value:', 'default');

        $dialog->handleKey(new KeyPress(KeyPress::KEY_CHAR, 'h'));
        $dialog->handleKey(new KeyPress(KeyPress::KEY_CHAR, 'e'));
        $dialog->handleKey(new KeyPress(KeyPress::KEY_CHAR, 'l'));
        $dialog->handleKey(new KeyPress(KeyPress::KEY_CHAR, 'l'));
        $dialog->handleKey(new KeyPress(KeyPress::KEY_CHAR, 'o'));

        $closed = $dialog->handleKey(new KeyPress(KeyPress::KEY_ENTER));

        self::assertTrue($closed);
        self::assertTrue($dialog->isConfirmed());
        self::assertSame('hello', $dialog->getInputValue());
    }

    public function testInputDialogBackspace(): void
    {
        $dialog = new ModalDialog();
        $dialog->showInput('Enter', 'Value:', '');

        $dialog->handleKey(new KeyPress(KeyPress::KEY_CHAR, 'a'));
        $dialog->handleKey(new KeyPress(KeyPress::KEY_CHAR, 'b'));
        $dialog->handleKey(new KeyPress(KeyPress::KEY_BACKSPACE));

        $dialog->handleKey(new KeyPress(KeyPress::KEY_ENTER));

        self::assertSame('a', $dialog->getInputValue());
    }

    public function testCloseThenReopenResets(): void
    {
        $dialog = new ModalDialog();

        $dialog->showConfirm('Test', 'First');
        $dialog->handleKey(new KeyPress(KeyPress::KEY_CHAR, 'y'));
        self::assertTrue($dialog->isConfirmed());

        $dialog->showConfirm('Test', 'Second');
        self::assertFalse($dialog->isConfirmed()); // reset on reopen
    }

    public function testCloseMethod(): void
    {
        $dialog = new ModalDialog();
        $dialog->showConfirm('Test', 'Message');
        $dialog->close();

        self::assertFalse($dialog->isVisible());
    }
}
