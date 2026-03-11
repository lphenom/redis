<?php

declare(strict_types=1);

namespace LPhenom\Redis\Tests\Cli;

use LPhenom\Redis\Cli\Widget\TableWidget;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LPhenom\Redis\Cli\Widget\TableWidget
 */
final class TableWidgetTest extends TestCase
{
    private function makeRows(): array
    {
        return [
            ['key:1', 'string', '∞', ''],
            ['key:2', 'list', '60s', ''],
            ['key:3', 'hash', '∞', ''],
            ['key:4', 'set', '120s', ''],
            ['key:5', 'zset', '∞', ''],
        ];
    }

    public function testInitialCursorAtZero(): void
    {
        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setRows($this->makeRows());

        self::assertSame(0, $widget->getCursor());
    }

    public function testCursorDown(): void
    {
        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setRows($this->makeRows());

        $widget->cursorDown();
        $widget->cursorDown();

        self::assertSame(2, $widget->getCursor());
    }

    public function testCursorDownDoesNotExceedRowCount(): void
    {
        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setRows($this->makeRows());

        for ($i = 0; $i < 20; $i++) {
            $widget->cursorDown();
        }

        self::assertSame(4, $widget->getCursor()); // max index = count - 1
    }

    public function testCursorUp(): void
    {
        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setRows($this->makeRows());

        $widget->cursorDown();
        $widget->cursorDown();
        $widget->cursorUp();

        self::assertSame(1, $widget->getCursor());
    }

    public function testCursorUpDoesNotGoBelowZero(): void
    {
        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setRows($this->makeRows());

        $widget->cursorUp();
        $widget->cursorUp();

        self::assertSame(0, $widget->getCursor());
    }

    public function testGetSelectedRow(): void
    {
        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setRows($this->makeRows());

        $widget->cursorDown();
        $row = $widget->getSelectedRow();

        self::assertNotNull($row);
        self::assertSame('key:2', $row[0]);
        self::assertSame('list', $row[1]);
    }

    public function testGetSelectedRowOnEmpty(): void
    {
        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setRows([]);

        self::assertNull($widget->getSelectedRow());
    }

    public function testSetRowsResetsCursor(): void
    {
        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setRows($this->makeRows());
        $widget->cursorDown();
        $widget->cursorDown();

        $widget->setRows([['new:key', 'string', '∞', '']]);

        self::assertSame(0, $widget->getCursor());
    }

    public function testPageDown(): void
    {
        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $rows[] = ['key:' . $i, 'string', '∞', ''];
        }

        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setRows($rows);
        $widget->setVisibleRows(10);
        $widget->pageDown();

        self::assertSame(10, $widget->getCursor());
    }

    public function testPageUp(): void
    {
        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $rows[] = ['key:' . $i, 'string', '∞', ''];
        }

        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setRows($rows);
        $widget->setVisibleRows(10);
        $widget->pageDown();
        $widget->pageDown();
        $widget->pageUp();

        self::assertSame(10, $widget->getCursor());
    }

    public function testGetRowCount(): void
    {
        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setRows($this->makeRows());

        self::assertSame(5, $widget->getRowCount());
    }

    public function testGetHeightReflectsVisibleRows(): void
    {
        $widget = new TableWidget(['Key', 'Type'], [20, 8]);
        $widget->setVisibleRows(10);

        // Height = visibleRows + 4 (header + sep + bottom + counter)
        self::assertSame(14, $widget->getHeight());
    }
}
