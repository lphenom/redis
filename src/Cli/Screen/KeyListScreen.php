<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Screen;

use LPhenom\Redis\Cli\Adapter\RawRedisAdapter;
use LPhenom\Redis\Cli\Terminal\KeyPress;
use LPhenom\Redis\Cli\Terminal\Renderer;
use LPhenom\Redis\Cli\Widget\InputWidget;
use LPhenom\Redis\Cli\Widget\ModalDialog;
use LPhenom\Redis\Cli\Widget\StatusBar;
use LPhenom\Redis\Cli\Widget\TableWidget;
use LPhenom\Redis\Client\RedisClientInterface;

/**
 * Main screen: browse Redis keys in a table.
 *
 * Columns: Key | Type | TTL | Size
 * Keys: ↑↓ Navigate, / Filter, Enter View value, d Delete, r Refresh, p Pipeline, s PubSub
 */
final class KeyListScreen implements ScreenInterface
{
    /** @var RawRedisAdapter */
    private RawRedisAdapter $adapter;

    /** @var RedisClientInterface */
    private RedisClientInterface $client;

    /** @var TableWidget */
    private TableWidget $table;

    /** @var StatusBar */
    private StatusBar $status;

    /** @var InputWidget */
    private InputWidget $filter;

    /** @var ModalDialog */
    private ModalDialog $dialog;

    /** @var bool */
    private bool $filterMode;

    /** @var string */
    private string $filterPattern;

    /** @var bool */
    private bool $needsRefresh;

    /** @var string */
    private string $selectedKey;

    public function __construct(RawRedisAdapter $adapter, RedisClientInterface $client)
    {
        $this->adapter       = $adapter;
        $this->client        = $client;
        $this->table         = new TableWidget(
            ['Key', 'Type', 'TTL', 'Encoding'],
            [42, 8, 8, 12]
        );
        $this->status        = new StatusBar();
        $this->filter        = new InputWidget('Filter pattern (e.g. user:*)', 40);
        $this->dialog        = new ModalDialog();
        $this->filterMode    = false;
        $this->filterPattern = '*';
        $this->needsRefresh  = true;
        $this->selectedKey   = '';
    }

    public function onActivate(): void
    {
        $this->needsRefresh = true;
    }

    public function render(Renderer $r): void
    {
        $w = $r->getWidth();
        $h = $r->getHeight();

        // Refresh data if needed
        if ($this->needsRefresh) {
            $this->refreshData($r);
            $this->needsRefresh = false;
        }

        // Header bar
        $r->moveTo(1, 1);
        $r->writeColored(
            str_pad(' redis-tui  ◆  LPhenom Redis TUI', $w),
            Renderer::FG_BLACK,
            Renderer::BG_BRIGHT_BLUE,
            Renderer::ATTR_BOLD
        );

        // Server info line
        $r->moveTo(2, 1);
        $dbSize = 0;

        try {
            $dbSize = $this->adapter->dbsize();
        } catch (\Throwable $e) {
            // ignore
        }

        $pattern = $this->filterPattern;
        $info    = sprintf(' Pattern: %s  Keys in DB: %d ', $pattern, $dbSize);
        $r->writeColored($info, Renderer::FG_BRIGHT_CYAN);
        $r->clearLine();

        // Filter input (row 3)
        $r->moveTo(3, 1);
        $r->writeColored(' Filter: ', Renderer::FG_BRIGHT_YELLOW);
        $this->filter->render($r, 3, 10);
        $r->clearLine();

        // Table (row 5 onwards)
        $visibleRows = $h - 10;
        $this->table->setVisibleRows(max(5, $visibleRows));

        $r->moveTo(4, 1);
        $r->writeColored(
            str_pad('', $w, Renderer::BOX_H),
            Renderer::FG_BRIGHT_BLUE
        );

        $this->table->render($r, 5, 1);

        // Status bar
        $this->status->setHints([
            '↑↓ Move',
            'Enter View',
            '/ Filter',
            'd Del',
            'p Pipeline',
            's PubSub',
            'r Reload',
            'q Quit',
        ]);
        $this->status->render($r, $h);

        // Modal dialog
        $this->dialog->render($r);
    }

    public function handleInput(KeyPress $key): ?string
    {
        // Handle modal dialog first
        if ($this->dialog->isVisible()) {
            $closed = $this->dialog->handleKey($key);
            if ($closed && $this->dialog->isConfirmed()) {
                // Delete confirmed
                $keyToDelete = $this->selectedKey;
                if ($keyToDelete !== '') {
                    $ex = null;
                    try {
                        $this->client->del($keyToDelete);
                        $this->status->success('Deleted: ' . $keyToDelete);
                    } catch (\Throwable $e) {
                        $ex = $e;
                    }
                    if ($ex !== null) {
                        $this->status->error('Delete failed: ' . $ex->getMessage());
                    }
                    $this->needsRefresh = true;
                }
            }
            return null;
        }

        // Handle filter mode
        if ($this->filterMode) {
            if ($key->is(KeyPress::KEY_ENTER)) {
                $this->filterPattern = $this->filter->getValue();
                if ($this->filterPattern === '') {
                    $this->filterPattern = '*';
                }
                $this->filterMode = false;
                $this->filter->setActive(false);
                $this->needsRefresh = true;
                $this->status->setMode('NORMAL');
                $this->status->info('Filter applied: ' . $this->filterPattern);
                return null;
            }
            if ($key->is(KeyPress::KEY_ESC)) {
                $this->filterMode = false;
                $this->filter->setActive(false);
                $this->status->setMode('NORMAL');
                return null;
            }
            if ($key->is(KeyPress::KEY_BACKSPACE)) {
                $this->filter->backspace();
                return null;
            }
            if ($key->is(KeyPress::KEY_DELETE)) {
                $this->filter->delete();
                return null;
            }
            if ($key->isChar()) {
                $this->filter->addChar($key->getChar());
                return null;
            }
            return null;
        }

        // Normal mode keys
        if ($key->is(KeyPress::KEY_UP)) {
            $this->table->cursorUp();
            return null;
        }

        if ($key->is(KeyPress::KEY_DOWN)) {
            $this->table->cursorDown();
            return null;
        }

        if ($key->is(KeyPress::KEY_PAGE_UP)) {
            $this->table->pageUp();
            return null;
        }

        if ($key->is(KeyPress::KEY_PAGE_DOWN)) {
            $this->table->pageDown();
            return null;
        }

        if ($key->is(KeyPress::KEY_ENTER)) {
            $row = $this->table->getSelectedRow();
            if ($row !== null) {
                $this->selectedKey = $row[0] ?? '';
            }
            return 'value';
        }

        if ($key->isKey('/')) {
            $this->filterMode = true;
            $this->filter->setActive(true);
            $this->filter->clear();
            $this->status->setMode('FILTER');
            return null;
        }

        if ($key->isKey('r') || $key->isKey('R')) {
            $this->needsRefresh = true;
            $this->status->info('Refreshing...');
            return null;
        }

        if ($key->isKey('d') || $key->isKey('D')) {
            $row = $this->table->getSelectedRow();
            if ($row !== null && isset($row[0]) && $row[0] !== '') {
                $this->selectedKey = $row[0];
                $this->dialog->showConfirm('Delete Key', 'Delete "' . $this->selectedKey . '"?');
            }
            return null;
        }

        if ($key->isKey('p') || $key->isKey('P')) {
            return 'pipeline';
        }

        if ($key->isKey('s') || $key->isKey('S')) {
            return 'pubsub';
        }

        if ($key->isKey('q') || $key->isKey('Q') || $key->is(KeyPress::KEY_ESC)) {
            return 'quit';
        }

        return null;
    }

    /**
     * Get the currently selected key (for ValueViewScreen to use).
     */
    public function getSelectedKey(): string
    {
        return $this->selectedKey;
    }

    /**
     * Load keys from Redis and populate table.
     */
    private function refreshData(Renderer $r): void
    {
        $pattern   = $this->filterPattern;
        $exception = null;
        $keys      = [];

        try {
            $keys = $this->adapter->scan($pattern, 500);
            sort($keys);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            $this->status->error('Scan failed: ' . $exception->getMessage());
            $this->table->setRows([]);
            return;
        }

        $rows = [];
        foreach ($keys as $key) {
            $type = 'unknown';
            $ttl  = -1;

            try {
                $type = $this->adapter->type($key);
                $ttl  = $this->adapter->ttl($key);
            } catch (\Throwable $e) {
                // skip
            }

            $ttlDisplay = $ttl === -1 ? '∞' : (string) $ttl . 's';
            if ($ttl === -2) {
                $ttlDisplay = 'gone';
            }

            $rows[] = [$key, $type, $ttlDisplay, ''];
        }

        $this->table->setRows($rows);
    }
}
