<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Widget;

use LPhenom\Redis\Cli\Terminal\Renderer;

/**
 * Generic table widget with keyboard navigation and pagination.
 *
 * Renders a table with headers and rows, supports cursor selection
 * and scrolling.
 */
final class TableWidget
{
    /** @var array<int, string> */
    private array $headers;

    /** @var array<int, int> */
    private array $colWidths;

    /** @var array<int, array<int, string>> */
    private array $rows;

    /** @var int */
    private int $cursor;

    /** @var int */
    private int $offset;

    /** @var int */
    private int $visibleRows;

    /**
     * @param array<int, string> $headers
     * @param array<int, int>    $colWidths
     */
    public function __construct(array $headers, array $colWidths)
    {
        $this->headers     = $headers;
        $this->colWidths   = $colWidths;
        $this->rows        = [];
        $this->cursor      = 0;
        $this->offset      = 0;
        $this->visibleRows = 10;
    }

    /**
     * Set table data rows.
     *
     * @param array<int, array<int, string>> $rows
     */
    public function setRows(array $rows): void
    {
        $this->rows   = $rows;
        $this->cursor = 0;
        $this->offset = 0;
    }

    /**
     * Set how many rows are visible at once.
     */
    public function setVisibleRows(int $n): void
    {
        $this->visibleRows = max(1, $n);
    }

    /**
     * Move cursor down by one row.
     */
    public function cursorDown(): void
    {
        $count = count($this->rows);
        if ($count === 0) {
            return;
        }
        if ($this->cursor < $count - 1) {
            $this->cursor++;
        }
        if ($this->cursor >= $this->offset + $this->visibleRows) {
            $this->offset++;
        }
    }

    /**
     * Move cursor up by one row.
     */
    public function cursorUp(): void
    {
        if ($this->cursor > 0) {
            $this->cursor--;
        }
        if ($this->cursor < $this->offset) {
            $this->offset--;
        }
    }

    /**
     * Move cursor down by one page.
     */
    public function pageDown(): void
    {
        $count = count($this->rows);
        if ($count === 0) {
            return;
        }
        $this->cursor = min($count - 1, $this->cursor + $this->visibleRows);
        $this->offset = min(max(0, $count - $this->visibleRows), $this->cursor);
    }

    /**
     * Move cursor up by one page.
     */
    public function pageUp(): void
    {
        $this->cursor = max(0, $this->cursor - $this->visibleRows);
        $this->offset = max(0, $this->offset - $this->visibleRows);
    }

    /**
     * Get index of currently selected row.
     */
    public function getCursor(): int
    {
        return $this->cursor;
    }

    /**
     * Get selected row data or null if empty.
     *
     * @return array<int, string>|null
     */
    public function getSelectedRow(): ?array
    {
        if (!isset($this->rows[$this->cursor])) {
            return null;
        }
        return $this->rows[$this->cursor];
    }

    /**
     * Render the table starting at (row, col) in the terminal.
     *
     * @param Renderer $r
     * @param int      $row Starting terminal row (1-based)
     * @param int      $col Starting terminal column (1-based)
     */
    public function render(Renderer $r, int $row, int $col): void
    {
        // Calculate total width
        $totalWidth = 1; // left border
        foreach ($this->colWidths as $w) {
            $totalWidth += $w + 3; // content + " | "
        }

        // Header row
        $r->moveTo($row, $col);
        $r->write(Renderer::BOX_V);
        foreach ($this->headers as $i => $header) {
            $width = $this->colWidths[$i] ?? 10;
            $r->writeFixed(' ' . $header . ' ', $width + 2, Renderer::FG_BRIGHT_CYAN, Renderer::BG_DEFAULT);
            $r->writeColored(Renderer::BOX_V, Renderer::FG_BRIGHT_BLUE);
        }

        // Separator
        $r->moveTo($row + 1, $col);
        $sep = Renderer::BOX_ML;
        foreach ($this->colWidths as $i => $w) {
            $sep .= str_repeat(Renderer::BOX_H, $w + 2);
            if ($i < count($this->colWidths) - 1) {
                $sep .= Renderer::BOX_MC;
            } else {
                $sep .= Renderer::BOX_MR;
            }
        }
        $r->writeColored($sep, Renderer::FG_BRIGHT_BLUE);

        // Data rows
        $count = count($this->rows);
        for ($i = 0; $i < $this->visibleRows; $i++) {
            $dataIdx = $this->offset + $i;
            $r->moveTo($row + 2 + $i, $col);

            if ($dataIdx < $count) {
                $rowData   = $this->rows[$dataIdx];
                $isSelected = ($dataIdx === $this->cursor);

                $r->writeColored(Renderer::BOX_V, Renderer::FG_BRIGHT_BLUE);

                foreach ($this->headers as $ci => $header) {
                    $width   = $this->colWidths[$ci] ?? 10;
                    $cell    = (string) ($rowData[$ci] ?? '');
                    $display = ' ' . $cell;

                    if ($isSelected) {
                        $r->writeFixed($display, $width + 2, Renderer::FG_BLACK, Renderer::BG_BRIGHT_CYAN);
                    } else {
                        $r->writeFixed($display, $width + 2, Renderer::FG_WHITE, Renderer::BG_DEFAULT);
                    }

                    $r->writeColored(Renderer::BOX_V, Renderer::FG_BRIGHT_BLUE);
                }
            } else {
                // Empty row
                $innerWidth = $totalWidth - 2;
                $r->writeColored(Renderer::BOX_V, Renderer::FG_BRIGHT_BLUE);
                $r->write(str_repeat(' ', $totalWidth - 2));
                $r->writeColored(Renderer::BOX_V, Renderer::FG_BRIGHT_BLUE);
            }
        }

        // Bottom border
        $r->moveTo($row + 2 + $this->visibleRows, $col);
        $bottom = Renderer::BOX_BL;
        foreach ($this->colWidths as $i => $w) {
            $bottom .= str_repeat(Renderer::BOX_H, $w + 2);
            if ($i < count($this->colWidths) - 1) {
                $bottom .= Renderer::BOX_BM;
            } else {
                $bottom .= Renderer::BOX_BR;
            }
        }
        $r->writeColored($bottom, Renderer::FG_BRIGHT_BLUE);

        // Scroll indicator
        if ($count > $this->visibleRows) {
            $pct  = (int) (($this->offset / max(1, $count - $this->visibleRows)) * $this->visibleRows);
            $scrollRow = $row + 2 + min($pct, $this->visibleRows - 1);
            $scrollCol = $col + $totalWidth;
            $r->moveTo($scrollRow, $scrollCol);
            $r->writeColored('▶', Renderer::FG_YELLOW);
        }

        // Row counter
        $r->moveTo($row + 2 + $this->visibleRows + 1, $col);
        $info = sprintf(
            ' Row %d/%d ',
            $count > 0 ? $this->cursor + 1 : 0,
            $count
        );
        $r->writeColored($info, Renderer::FG_BRIGHT_BLACK);
    }

    /**
     * Total height occupied by the widget (header + sep + rows + bottom + counter).
     */
    public function getHeight(): int
    {
        return $this->visibleRows + 4;
    }

    public function getRowCount(): int
    {
        return count($this->rows);
    }
}
