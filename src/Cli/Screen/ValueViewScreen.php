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
 * Value viewer/editor screen.
 *
 * Adapts display based on key type:
 * - STRING: show value, e to edit, t to set TTL
 * - LIST:   table of elements, a to add, d to remove
 * - HASH:   table field/value, e to edit, d to delete field
 * - SET:    table of members, a to add, d to remove
 * - ZSET:   table member/score, e to edit score
 *
 * Keys: Esc/Backspace → back to key list
 */
final class ValueViewScreen implements ScreenInterface
{
    /** @var RawRedisAdapter */
    private RawRedisAdapter $adapter;

    /** @var RedisClientInterface */
    private RedisClientInterface $client;

    /** @var KeyListScreen */
    private KeyListScreen $keyList;

    /** @var StatusBar */
    private StatusBar $status;

    /** @var TableWidget */
    private TableWidget $table;

    /** @var ModalDialog */
    private ModalDialog $dialog;

    /** @var InputWidget */
    private InputWidget $editInput;

    /** @var string */
    private string $currentKey;

    /** @var string */
    private string $keyType;

    /** @var string */
    private string $stringValue;

    /** @var bool */
    private bool $editMode;

    /** @var string */
    private string $editContext; // 'value' | 'field' | 'member' | 'score' | 'ttl'

    /** @var string */
    private string $editField; // for hash field editing

    /** @var bool */
    private bool $needsRefresh;

    public function __construct(RawRedisAdapter $adapter, RedisClientInterface $client, KeyListScreen $keyList)
    {
        $this->adapter     = $adapter;
        $this->client      = $client;
        $this->keyList     = $keyList;
        $this->status      = new StatusBar();
        $this->table       = new TableWidget(['Field', 'Value'], [20, 40]);
        $this->dialog      = new ModalDialog();
        $this->editInput   = new InputWidget('', 40);
        $this->currentKey  = '';
        $this->keyType     = 'none';
        $this->stringValue = '';
        $this->editMode    = false;
        $this->editContext = 'value';
        $this->editField   = '';
        $this->needsRefresh = false;
    }

    public function onActivate(): void
    {
        $this->currentKey   = $this->keyList->getSelectedKey();
        $this->needsRefresh = true;
        $this->editMode     = false;
    }

    public function render(Renderer $r): void
    {
        $w = $r->getWidth();
        $h = $r->getHeight();

        if ($this->needsRefresh) {
            $this->refreshData();
            $this->needsRefresh = false;
        }

        // Header
        $r->moveTo(1, 1);
        $typeLabel = strtoupper($this->keyType);
        $header    = ' redis-tui  ◆  Key: ' . $this->currentKey . '  [' . $typeLabel . ']';
        $r->writeColored(str_pad($header, $w), Renderer::FG_BLACK, Renderer::BG_BRIGHT_BLUE, Renderer::ATTR_BOLD);

        // TTL info
        $r->moveTo(2, 1);
        $ttl = -2;
        try {
            $ttl = $this->adapter->ttl($this->currentKey);
        } catch (\Throwable $e) {
            // ignore
        }
        $ttlText = $ttl === -1 ? 'no expiry' : ($ttl === -2 ? 'key gone' : $ttl . ' seconds');
        $r->writeColored(' TTL: ' . $ttlText . ' ', Renderer::FG_BRIGHT_CYAN);
        $r->clearLine();

        $r->moveTo(3, 1);
        $r->writeColored(str_pad('', $w, Renderer::BOX_H), Renderer::FG_BRIGHT_BLUE);

        if ($this->keyType === 'string') {
            $this->renderString($r, $h);
        } else {
            // List, Hash, Set, ZSet — use table
            $visibleRows = $h - 11;
            $this->table->setVisibleRows(max(5, $visibleRows));
            $this->table->render($r, 4, 1);

            // Edit input (if active)
            if ($this->editMode) {
                $r->moveTo($h - 4, 1);
                $r->writeColored(' Edit > ', Renderer::FG_BRIGHT_YELLOW);
                $this->editInput->render($r, $h - 4, 9);
            }
        }

        // Hints based on type
        $hints = $this->getHints();
        $this->status->setHints($hints);
        $this->status->render($r, $h);

        $this->dialog->render($r);
    }

    public function handleInput(KeyPress $key): ?string
    {
        // Modal dialog
        if ($this->dialog->isVisible()) {
            $closed = $this->dialog->handleKey($key);
            if ($closed && $this->dialog->isConfirmed()) {
                $this->handleDialogConfirm();
            }
            return null;
        }

        // Edit mode
        if ($this->editMode) {
            $this->handleEditInput($key);
            return null;
        }

        // Navigation
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

        // Back to key list
        if ($key->is(KeyPress::KEY_ESC) || $key->is(KeyPress::KEY_BACKSPACE)) {
            return 'keys';
        }

        // Refresh
        if ($key->isKey('r') || $key->isKey('R')) {
            $this->needsRefresh = true;
            return null;
        }

        // Edit string value
        if ($key->isKey('e') || $key->isKey('E')) {
            if ($this->keyType === 'string') {
                $this->editContext = 'value';
                $this->editInput   = new InputWidget('New value', 50);
                $this->editInput->setValue($this->stringValue);
                $this->editInput->setActive(true);
                $this->editMode = true;
                $this->status->setMode('EDIT');
            } elseif ($this->keyType === 'hash') {
                $row = $this->table->getSelectedRow();
                if ($row !== null && isset($row[0])) {
                    $this->editContext = 'hash_value';
                    $this->editField   = $row[0];
                    $this->editInput   = new InputWidget('New value for ' . $row[0], 50);
                    $this->editInput->setValue($row[1] ?? '');
                    $this->editInput->setActive(true);
                    $this->editMode = true;
                    $this->status->setMode('EDIT');
                }
            } elseif ($this->keyType === 'zset') {
                $row = $this->table->getSelectedRow();
                if ($row !== null && isset($row[0])) {
                    $this->editContext = 'zset_score';
                    $this->editField   = $row[0]; // member
                    $this->editInput   = new InputWidget('New score', 20);
                    $this->editInput->setValue($row[1] ?? '0');
                    $this->editInput->setActive(true);
                    $this->editMode = true;
                    $this->status->setMode('EDIT');
                }
            }
            return null;
        }

        // Add element
        if ($key->isKey('a') || $key->isKey('A')) {
            if ($this->keyType === 'list') {
                $this->editContext = 'list_add';
                $this->editInput   = new InputWidget('New list value', 50);
                $this->editInput->setActive(true);
                $this->editMode = true;
                $this->status->setMode('ADD');
            } elseif ($this->keyType === 'set') {
                $this->editContext = 'set_add';
                $this->editInput   = new InputWidget('New set member', 50);
                $this->editInput->setActive(true);
                $this->editMode = true;
                $this->status->setMode('ADD');
            } elseif ($this->keyType === 'hash') {
                $this->dialog->showInput('Add Hash Field', 'Field name:', 'field_name');
                $this->editContext = 'hash_add_field';
            } elseif ($this->keyType === 'zset') {
                $this->editContext = 'zset_add';
                $this->editInput   = new InputWidget('member:score (e.g. Alice:9.5)', 50);
                $this->editInput->setActive(true);
                $this->editMode = true;
                $this->status->setMode('ADD');
            }
            return null;
        }

        // Delete element
        if ($key->isKey('d') || $key->isKey('D')) {
            $row = $this->table->getSelectedRow();
            if ($row !== null && isset($row[0])) {
                $label = $row[0];
                $this->dialog->showConfirm('Delete', 'Remove "' . $label . '"?');
                $this->editContext = 'delete';
                $this->editField   = $label;
            } elseif ($this->keyType === 'string') {
                $this->dialog->showConfirm('Delete Key', 'Delete key "' . $this->currentKey . '"?');
                $this->editContext = 'delete_key';
            }
            return null;
        }

        // Set TTL
        if ($key->isKey('t') || $key->isKey('T')) {
            $this->editContext = 'ttl';
            $this->editInput   = new InputWidget('TTL in seconds (0 = remove expiry)', 20);
            $this->editInput->setActive(true);
            $this->editMode = true;
            $this->status->setMode('TTL');
            return null;
        }

        if ($key->isKey('q') || $key->isKey('Q')) {
            return 'quit';
        }

        return null;
    }

    /**
     * Handle key press in edit mode.
     */
    private function handleEditInput(KeyPress $key): void
    {
        if ($key->is(KeyPress::KEY_ENTER)) {
            $value = $this->editInput->getValue();
            $this->editMode = false;
            $this->editInput->setActive(false);
            $this->status->setMode('NORMAL');
            $this->applyEdit($value);
            return;
        }
        if ($key->is(KeyPress::KEY_ESC)) {
            $this->editMode = false;
            $this->editInput->setActive(false);
            $this->status->setMode('NORMAL');
            return;
        }
        if ($key->is(KeyPress::KEY_BACKSPACE)) {
            $this->editInput->backspace();
        } elseif ($key->is(KeyPress::KEY_DELETE)) {
            $this->editInput->delete();
        } elseif ($key->is(KeyPress::KEY_LEFT)) {
            $this->editInput->moveCursorLeft();
        } elseif ($key->is(KeyPress::KEY_RIGHT)) {
            $this->editInput->moveCursorRight();
        } elseif ($key->isChar()) {
            $this->editInput->addChar($key->getChar());
        }
    }

    /**
     * Apply edited value based on context.
     */
    private function applyEdit(string $value): void
    {
        $ex = null;

        try {
            if ($this->editContext === 'value') {
                $this->client->set($this->currentKey, $value);
                $this->stringValue = $value;
                $this->status->success('Value updated');
            } elseif ($this->editContext === 'hash_value') {
                $this->adapter->hset($this->currentKey, $this->editField, $value);
                $this->status->success('Field "' . $this->editField . '" updated');
                $this->needsRefresh = true;
            } elseif ($this->editContext === 'hash_add_value') {
                $this->adapter->hset($this->currentKey, $this->editField, $value);
                $this->status->success('Field "' . $this->editField . '" added');
                $this->needsRefresh = true;
            } elseif ($this->editContext === 'list_add') {
                $this->client->lpush($this->currentKey, $value);
                $this->status->success('Added to list');
                $this->needsRefresh = true;
            } elseif ($this->editContext === 'set_add') {
                $this->adapter->sadd($this->currentKey, $value);
                $this->status->success('Added to set');
                $this->needsRefresh = true;
            } elseif ($this->editContext === 'zset_add') {
                $parts = explode(':', $value, 2);
                $member = $parts[0];
                $score  = isset($parts[1]) ? (float) $parts[1] : 0.0;
                $this->adapter->zadd($this->currentKey, $score, $member);
                $this->status->success('Added to zset');
                $this->needsRefresh = true;
            } elseif ($this->editContext === 'zset_score') {
                $this->adapter->zadd($this->currentKey, (float) $value, $this->editField);
                $this->status->success('Score updated');
                $this->needsRefresh = true;
            } elseif ($this->editContext === 'ttl') {
                $seconds = (int) $value;
                if ($seconds > 0) {
                    $this->client->expire($this->currentKey, $seconds);
                    $this->status->success('TTL set to ' . $seconds . 's');
                } else {
                    // Remove TTL by persisting
                    $this->adapter->getRedis()->persist($this->currentKey);
                    $this->status->success('TTL removed (key persisted)');
                }
            }
        } catch (\Throwable $e) {
            $ex = $e;
        }

        if ($ex !== null) {
            $this->status->error('Error: ' . $ex->getMessage());
        }
    }

    /**
     * Handle dialog confirmation.
     */
    private function handleDialogConfirm(): void
    {
        if ($this->editContext === 'hash_add_field') {
            $field = $this->dialog->getInputValue();
            if ($field !== '') {
                $this->editField   = $field;
                $this->editContext = 'hash_add_value';
                $this->editInput   = new InputWidget('Value for ' . $field, 50);
                $this->editInput->setActive(true);
                $this->editMode = true;
                $this->status->setMode('ADD');
            }
            return;
        }

        if ($this->editContext === 'delete') {
            $ex = null;
            try {
                if ($this->keyType === 'hash') {
                    $this->adapter->hdel($this->currentKey, $this->editField);
                } elseif ($this->keyType === 'list') {
                    $this->adapter->lrem($this->currentKey, $this->editField);
                } elseif ($this->keyType === 'set') {
                    $this->adapter->srem($this->currentKey, $this->editField);
                } elseif ($this->keyType === 'zset') {
                    $this->adapter->zrem($this->currentKey, $this->editField);
                }
                $this->status->success('Removed "' . $this->editField . '"');
                $this->needsRefresh = true;
            } catch (\Throwable $e) {
                $ex = $e;
            }
            if ($ex !== null) {
                $this->status->error('Error: ' . $ex->getMessage());
            }
            return;
        }

        if ($this->editContext === 'delete_key') {
            $ex = null;
            try {
                $this->client->del($this->currentKey);
                $this->status->success('Key deleted');
            } catch (\Throwable $e) {
                $ex = $e;
            }
            if ($ex !== null) {
                $this->status->error('Error: ' . $ex->getMessage());
            }
        }
    }

    /**
     * Render string value with edit hint.
     */
    private function renderString(Renderer $r, int $h): void
    {
        $r->moveTo(4, 1);
        $r->writeColored(' Value:', Renderer::FG_BRIGHT_YELLOW, Renderer::BG_DEFAULT, Renderer::ATTR_BOLD);
        $r->moveTo(5, 1);
        $r->drawBox(5, 1, $r->getWidth() - 2, 5);
        $r->moveTo(6, 3);
        $r->writeFixed($this->stringValue, $r->getWidth() - 6, Renderer::FG_BRIGHT_GREEN);

        if ($this->editMode) {
            $r->moveTo($h - 4, 1);
            $r->writeColored(' New value > ', Renderer::FG_BRIGHT_YELLOW);
            $this->editInput->render($r, $h - 4, 14);
        }
    }

    /**
     * Load data from Redis.
     */
    private function refreshData(): void
    {
        if ($this->currentKey === '') {
            return;
        }

        $exception = null;
        $type      = 'none';

        try {
            $type = $this->adapter->type($this->currentKey);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            $this->status->error('TYPE failed: ' . $exception->getMessage());
            return;
        }

        $this->keyType = $type;

        $ex2 = null;
        try {
            if ($type === 'string') {
                $val = $this->client->get($this->currentKey);
                $this->stringValue = $val ?? '';
                $this->table->setRows([]);
            } elseif ($type === 'hash') {
                $this->table = new TableWidget(['Field', 'Value'], [25, 45]);
                $data  = $this->adapter->hgetall($this->currentKey);
                $rows  = [];
                foreach ($data as $field => $value) {
                    $rows[] = [$field, $value];
                }
                $this->table->setRows($rows);
            } elseif ($type === 'list') {
                $this->table = new TableWidget(['#', 'Value'], [5, 60]);
                $items = $this->adapter->lrange($this->currentKey, 0, 199);
                $rows  = [];
                foreach ($items as $i => $item) {
                    $rows[] = [(string) $i, $item];
                }
                $this->table->setRows($rows);
            } elseif ($type === 'set') {
                $this->table = new TableWidget(['Member'], [65]);
                $items = $this->adapter->smembers($this->currentKey);
                $rows  = [];
                foreach ($items as $item) {
                    $rows[] = [$item];
                }
                $this->table->setRows($rows);
            } elseif ($type === 'zset') {
                $this->table = new TableWidget(['Member', 'Score'], [50, 15]);
                $items = $this->adapter->zrangeWithScores($this->currentKey, 0, 199);
                $this->table->setRows($items);
            }
        } catch (\Throwable $e) {
            $ex2 = $e;
        }

        if ($ex2 !== null) {
            $this->status->error('Load failed: ' . $ex2->getMessage());
        }
    }

    /**
     * Return contextual hints.
     *
     * @return array<int, string>
     */
    private function getHints(): array
    {
        $base = ['↑↓ Move', 'Esc Back', 'r Reload', 't TTL', 'q Quit'];

        if ($this->keyType === 'string') {
            return array_merge(['e Edit', 'd Delete'], $base);
        }
        if ($this->keyType === 'hash') {
            return array_merge(['e Edit', 'a Add field', 'd Del field'], $base);
        }
        if ($this->keyType === 'list') {
            return array_merge(['a Push', 'd Remove'], $base);
        }
        if ($this->keyType === 'set') {
            return array_merge(['a Add', 'd Remove'], $base);
        }
        if ($this->keyType === 'zset') {
            return array_merge(['e Score', 'a Add', 'd Remove'], $base);
        }
        return $base;
    }
}
