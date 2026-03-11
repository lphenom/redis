<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Widget;

use LPhenom\Redis\Cli\Terminal\KeyPress;
use LPhenom\Redis\Cli\Terminal\Renderer;

/**
 * Modal dialog for confirmations and simple text input.
 */
final class ModalDialog
{
    public const TYPE_CONFIRM = 'confirm';
    public const TYPE_INPUT   = 'input';

    /** @var string */
    private string $title;

    /** @var string */
    private string $message;

    /** @var string */
    private string $type;

    /** @var InputWidget */
    private InputWidget $input;

    /** @var bool */
    private bool $confirmed;

    /** @var bool */
    private bool $visible;

    public function __construct()
    {
        $this->title     = '';
        $this->message   = '';
        $this->type      = self::TYPE_CONFIRM;
        $this->input     = new InputWidget('', 30);
        $this->confirmed = false;
        $this->visible   = false;
    }

    public function showConfirm(string $title, string $message): void
    {
        $this->title     = $title;
        $this->message   = $message;
        $this->type      = self::TYPE_CONFIRM;
        $this->confirmed = false;
        $this->visible   = true;
    }

    public function showInput(string $title, string $message, string $placeholder = ''): void
    {
        $this->title   = $title;
        $this->message = $message;
        $this->type    = self::TYPE_INPUT;
        $this->input   = new InputWidget($placeholder, 30);
        $this->input->setActive(true);
        $this->confirmed = false;
        $this->visible   = true;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function close(): void
    {
        $this->visible = false;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function getInputValue(): string
    {
        return $this->input->getValue();
    }

    /**
     * Handle key press inside dialog.
     * Returns true if dialog should close.
     */
    public function handleKey(KeyPress $key): bool
    {
        if ($this->type === self::TYPE_CONFIRM) {
            if ($key->isKey('y') || $key->isKey('Y') || $key->is(KeyPress::KEY_ENTER)) {
                $this->confirmed = true;
                $this->visible   = false;
                return true;
            }
            if ($key->isKey('n') || $key->isKey('N') || $key->is(KeyPress::KEY_ESC)) {
                $this->confirmed = false;
                $this->visible   = false;
                return true;
            }
        } else {
            // Input type
            if ($key->is(KeyPress::KEY_ENTER)) {
                $this->confirmed = true;
                $this->visible   = false;
                return true;
            }
            if ($key->is(KeyPress::KEY_ESC)) {
                $this->confirmed = false;
                $this->visible   = false;
                return true;
            }
            if ($key->is(KeyPress::KEY_BACKSPACE)) {
                $this->input->backspace();
            } elseif ($key->is(KeyPress::KEY_DELETE)) {
                $this->input->delete();
            } elseif ($key->is(KeyPress::KEY_LEFT)) {
                $this->input->moveCursorLeft();
            } elseif ($key->is(KeyPress::KEY_RIGHT)) {
                $this->input->moveCursorRight();
            } elseif ($key->isChar()) {
                $this->input->addChar($key->getChar());
            }
        }

        return false;
    }

    /**
     * Render modal dialog centered on screen.
     */
    public function render(Renderer $r): void
    {
        if (!$this->visible) {
            return;
        }

        $screenW = $r->getWidth();
        $screenH = $r->getHeight();

        $boxW = 44;
        $boxH = ($this->type === self::TYPE_INPUT) ? 7 : 6;

        $startRow = (int) (($screenH - $boxH) / 2);
        $startCol = (int) (($screenW - $boxW) / 2);

        // Draw overlay shadow effect
        $r->drawBoxWithTitle($startRow, $startCol, $boxW, $boxH, $this->title, Renderer::FG_BRIGHT_YELLOW);

        // Message line
        $inner  = $boxW - 4;
        $msgRow = $startRow + 2;
        $r->moveTo($msgRow, $startCol + 2);
        $r->writeFixed(' ' . $this->message . ' ', $inner, Renderer::FG_WHITE);

        if ($this->type === self::TYPE_INPUT) {
            $r->moveTo($startRow + 3, $startCol + 2);
            $this->input->render($r, $startRow + 3, $startCol + 2);

            $r->moveTo($startRow + 5, $startCol + 2);
            $r->writeColored(' Enter ', Renderer::FG_BLACK, Renderer::BG_BRIGHT_GREEN);
            $r->write(' confirm  ');
            $r->writeColored(' Esc ', Renderer::FG_BLACK, Renderer::BG_BRIGHT_RED);
            $r->write(' cancel');
        } else {
            $r->moveTo($startRow + 4, $startCol + 2);
            $r->writeColored(' Y ', Renderer::FG_BLACK, Renderer::BG_BRIGHT_GREEN);
            $r->write(' yes  ');
            $r->writeColored(' N ', Renderer::FG_BLACK, Renderer::BG_BRIGHT_RED);
            $r->write('  no   ');
            $r->writeColored(' Esc ', Renderer::FG_BLACK, Renderer::BG_BRIGHT_RED);
            $r->write(' cancel');
        }
    }
}
