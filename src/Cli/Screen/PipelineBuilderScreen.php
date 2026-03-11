<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Screen;

use LPhenom\Redis\Cli\Terminal\KeyPress;
use LPhenom\Redis\Cli\Terminal\Renderer;
use LPhenom\Redis\Cli\Widget\InputWidget;
use LPhenom\Redis\Cli\Widget\ModalDialog;
use LPhenom\Redis\Cli\Widget\StatusBar;
use LPhenom\Redis\Cli\Widget\TableWidget;
use LPhenom\Redis\Client\RedisClientInterface;
use LPhenom\Redis\Pipeline\PhpRedisPipelineDriver;
use LPhenom\Redis\Pipeline\RedisPipeline;

/**
 * Pipeline builder screen.
 *
 * Visual constructor for Redis batch commands.
 * Commands are queued and executed together.
 *
 * Keys: a Add command, d Delete, x Execute, Esc Back
 */
final class PipelineBuilderScreen implements ScreenInterface
{
    /** @var RedisClientInterface */
    private RedisClientInterface $client;

    /** @var \Redis */
    private \Redis $redis;

    /** @var TableWidget */
    private TableWidget $table;

    /** @var StatusBar */
    private StatusBar $status;

    /** @var ModalDialog */
    private ModalDialog $dialog;

    /** @var InputWidget */
    private InputWidget $keyInput;

    /** @var InputWidget */
    private InputWidget $valueInput;

    /** @var InputWidget */
    private InputWidget $ttlInput;

    /** @var array<int, string> */
    private array $cmdKeys;

    /** @var array<int, string> */
    private array $cmdValues;

    /** @var array<int, string> */
    private array $cmdTtls;

    /** @var array<int, string> */
    private array $cmdTypes;

    /** @var int */
    private int $addStep; // 0=not adding, 1=type, 2=key, 3=value, 4=ttl

    /** @var array<int, string> */
    private array $availableCommands;

    /** @var int */
    private int $cmdSelectCursor;

    /** @var string */
    private string $newCmdType;

    /** @var array<int, string> */
    private array $results;

    public function __construct(RedisClientInterface $client, \Redis $redis)
    {
        $this->client = $client;
        $this->redis  = $redis;
        $this->table  = new TableWidget(
            ['#', 'Command', 'Key', 'Value', 'TTL'],
            [3, 6, 25, 20, 6]
        );
        $this->status     = new StatusBar();
        $this->dialog     = new ModalDialog();
        $this->keyInput   = new InputWidget('Redis key', 40);
        $this->valueInput = new InputWidget('Value', 40);
        $this->ttlInput   = new InputWidget('TTL (0=none)', 10);

        $this->cmdKeys   = [];
        $this->cmdValues = [];
        $this->cmdTtls   = [];
        $this->cmdTypes  = [];
        $this->addStep   = 0;

        $this->availableCommands = ['SET', 'DEL', 'INCR', 'LPUSH', 'EXPIRE'];
        $this->cmdSelectCursor   = 0;
        $this->newCmdType        = '';
        $this->results           = [];
    }

    public function onActivate(): void
    {
        $this->status->info('Pipeline Builder — a=Add command, x=Execute, d=Delete, Esc=Back');
    }

    public function render(Renderer $r): void
    {
        $w = $r->getWidth();
        $h = $r->getHeight();

        // Header
        $r->moveTo(1, 1);
        $r->writeColored(
            str_pad(' redis-tui  ◆  Pipeline Builder', $w),
            Renderer::FG_BLACK,
            Renderer::BG_BRIGHT_MAGENTA,
            Renderer::ATTR_BOLD
        );

        // Command counter
        $count = count($this->cmdTypes);
        $r->moveTo(2, 1);
        $r->writeColored(' Queued commands: ' . $count . ' ', Renderer::FG_BRIGHT_CYAN);
        $r->clearLine();

        $r->moveTo(3, 1);
        $r->writeColored(str_pad('', $w, Renderer::BOX_H), Renderer::FG_BRIGHT_BLUE);

        // Table
        $visibleRows = max(5, $h - 14);
        $this->table->setVisibleRows($visibleRows);
        $this->syncTableRows();
        $this->table->render($r, 4, 1);

        // Results area
        $resultsRow = 4 + $this->table->getHeight() + 1;
        if (count($this->results) > 0 && $resultsRow < $h - 3) {
            $r->moveTo($resultsRow, 1);
            $r->writeColored(' Last execution results: ', Renderer::FG_BRIGHT_YELLOW);
            $displayCount = min(3, count($this->results));
            for ($i = 0; $i < $displayCount; $i++) {
                $r->moveTo($resultsRow + 1 + $i, 3);
                $r->writeColored($this->results[$i], Renderer::FG_BRIGHT_GREEN);
                $r->clearLine();
            }
        }

        // Add wizard
        if ($this->addStep === 1) {
            $this->renderCommandSelect($r);
        } elseif ($this->addStep === 2) {
            $r->moveTo($h - 5, 1);
            $r->writeColored(' Command: ', Renderer::FG_BRIGHT_YELLOW);
            $r->writeColored($this->newCmdType . ' ', Renderer::FG_BRIGHT_GREEN, Renderer::BG_DEFAULT, Renderer::ATTR_BOLD);
            $r->moveTo($h - 4, 1);
            $r->writeColored(' Key > ', Renderer::FG_BRIGHT_YELLOW);
            $this->keyInput->render($r, $h - 4, 8);
        } elseif ($this->addStep === 3) {
            $r->moveTo($h - 5, 1);
            $r->writeColored(' Key: ', Renderer::FG_BRIGHT_YELLOW);
            $r->writeColored($this->keyInput->getValue() . ' ', Renderer::FG_WHITE);
            $r->moveTo($h - 4, 1);
            $r->writeColored(' Value > ', Renderer::FG_BRIGHT_YELLOW);
            $this->valueInput->render($r, $h - 4, 10);
        } elseif ($this->addStep === 4) {
            $r->moveTo($h - 5, 1);
            $r->writeColored(' Key: ', Renderer::FG_BRIGHT_YELLOW);
            $r->writeColored($this->keyInput->getValue(), Renderer::FG_WHITE);
            $r->writeColored('  Value: ', Renderer::FG_BRIGHT_YELLOW);
            $r->writeColored($this->valueInput->getValue(), Renderer::FG_WHITE);
            $r->moveTo($h - 4, 1);
            $r->writeColored(' TTL > ', Renderer::FG_BRIGHT_YELLOW);
            $this->ttlInput->render($r, $h - 4, 8);
        }

        $this->status->setHints(['a Add', 'd Del', 'x Execute', 'c Clear', 'Esc Back', 'q Quit']);
        $this->status->render($r, $h);
        $this->dialog->render($r);
    }

    public function handleInput(KeyPress $key): ?string
    {
        if ($this->dialog->isVisible()) {
            $closed = $this->dialog->handleKey($key);
            if ($closed && $this->dialog->isConfirmed()) {
                // Clear all confirmed
                $this->cmdTypes  = [];
                $this->cmdKeys   = [];
                $this->cmdValues = [];
                $this->cmdTtls   = [];
                $this->status->info('Pipeline cleared');
            }
            return null;
        }

        // Wizard step 1: command type select
        if ($this->addStep === 1) {
            $this->handleCommandSelect($key);
            return null;
        }

        // Wizard step 2: enter key
        if ($this->addStep === 2) {
            $this->handleKeyInput($key);
            return null;
        }

        // Wizard step 3: enter value
        if ($this->addStep === 3) {
            $this->handleValueInput($key);
            return null;
        }

        // Wizard step 4: enter TTL
        if ($this->addStep === 4) {
            $this->handleTtlInput($key);
            return null;
        }

        // Normal mode
        if ($key->is(KeyPress::KEY_UP)) {
            $this->table->cursorUp();
            return null;
        }
        if ($key->is(KeyPress::KEY_DOWN)) {
            $this->table->cursorDown();
            return null;
        }

        if ($key->isKey('a') || $key->isKey('A')) {
            $this->addStep         = 1;
            $this->cmdSelectCursor = 0;
            $this->keyInput        = new InputWidget('Redis key', 40);
            $this->valueInput      = new InputWidget('Value', 40);
            $this->ttlInput        = new InputWidget('0', 10);
            $this->ttlInput->setValue('0');
            $this->status->setMode('ADD');
            return null;
        }

        if ($key->isKey('d') || $key->isKey('D')) {
            $this->removeSelected();
            return null;
        }

        if ($key->isKey('c') || $key->isKey('C')) {
            $this->dialog->showConfirm('Clear Pipeline', 'Clear all ' . count($this->cmdTypes) . ' commands?');
            return null;
        }

        if ($key->isKey('x') || $key->isKey('X')) {
            $this->executePipeline();
            return null;
        }

        if ($key->is(KeyPress::KEY_ESC) || $key->is(KeyPress::KEY_BACKSPACE)) {
            return 'keys';
        }

        if ($key->isKey('q') || $key->isKey('Q')) {
            return 'quit';
        }

        return null;
    }

    /**
     * Render command type selection UI.
     */
    private function renderCommandSelect(Renderer $r): void
    {
        $w = $r->getWidth();
        $h = $r->getHeight();

        $menuW = 24;
        $menuH = count($this->availableCommands) + 4;
        $menuR = (int) (($h - $menuH) / 2);
        $menuC = (int) (($w - $menuW) / 2);

        $r->drawBoxWithTitle($menuR, $menuC, $menuW, $menuH, 'Select Command', Renderer::FG_BRIGHT_MAGENTA);

        foreach ($this->availableCommands as $i => $cmd) {
            $r->moveTo($menuR + 2 + $i, $menuC + 2);
            if ($i === $this->cmdSelectCursor) {
                $r->writeFixed(' > ' . $cmd, $menuW - 4, Renderer::FG_BLACK, Renderer::BG_BRIGHT_CYAN);
            } else {
                $r->writeFixed('   ' . $cmd, $menuW - 4, Renderer::FG_WHITE);
            }
        }
    }

    /**
     * Handle command selection keyboard input.
     */
    private function handleCommandSelect(KeyPress $key): void
    {
        if ($key->is(KeyPress::KEY_UP)) {
            if ($this->cmdSelectCursor > 0) {
                $this->cmdSelectCursor--;
            }
            return;
        }
        if ($key->is(KeyPress::KEY_DOWN)) {
            if ($this->cmdSelectCursor < count($this->availableCommands) - 1) {
                $this->cmdSelectCursor++;
            }
            return;
        }
        if ($key->is(KeyPress::KEY_ENTER)) {
            $cmd = $this->availableCommands[$this->cmdSelectCursor] ?? 'SET';
            $this->newCmdType = $cmd;
            $this->addStep    = 2;
            $this->keyInput->setActive(true);
            return;
        }
        if ($key->is(KeyPress::KEY_ESC)) {
            $this->addStep = 0;
            $this->status->setMode('NORMAL');
        }
    }

    /**
     * Handle key input in wizard step 2.
     */
    private function handleKeyInput(KeyPress $key): void
    {
        if ($key->is(KeyPress::KEY_ENTER)) {
            $k = $this->keyInput->getValue();
            if ($k === '') {
                $this->status->error('Key cannot be empty');
                return;
            }

            $cmd = $this->newCmdType;
            if ($cmd === 'DEL' || $cmd === 'INCR') {
                $this->appendCommand($cmd, $k, '', '0');
                $this->addStep = 0;
                $this->keyInput->setActive(false);
                $this->status->setMode('NORMAL');
                $this->status->info($cmd . ' "' . $k . '" added to pipeline');
            } else {
                $this->addStep = 3;
                $this->keyInput->setActive(false);
                $this->valueInput->setActive(true);
            }
            return;
        }
        if ($key->is(KeyPress::KEY_ESC)) {
            $this->addStep = 0;
            $this->keyInput->setActive(false);
            $this->status->setMode('NORMAL');
            return;
        }
        $this->routeInputKey($key, $this->keyInput);
    }

    /**
     * Handle value input in wizard step 3.
     */
    private function handleValueInput(KeyPress $key): void
    {
        if ($key->is(KeyPress::KEY_ENTER)) {
            $cmd = $this->newCmdType;
            if ($cmd === 'SET') {
                $this->addStep = 4;
                $this->valueInput->setActive(false);
                $this->ttlInput->setActive(true);
            } else {
                $this->appendCommand(
                    $cmd,
                    $this->keyInput->getValue(),
                    $this->valueInput->getValue(),
                    '0'
                );
                $this->addStep = 0;
                $this->valueInput->setActive(false);
                $this->status->setMode('NORMAL');
                $this->status->info($cmd . ' added to pipeline');
            }
            return;
        }
        if ($key->is(KeyPress::KEY_ESC)) {
            $this->addStep = 0;
            $this->valueInput->setActive(false);
            $this->status->setMode('NORMAL');
            return;
        }
        $this->routeInputKey($key, $this->valueInput);
    }

    /**
     * Handle TTL input in wizard step 4.
     */
    private function handleTtlInput(KeyPress $key): void
    {
        if ($key->is(KeyPress::KEY_ENTER)) {
            $this->appendCommand(
                $this->newCmdType,
                $this->keyInput->getValue(),
                $this->valueInput->getValue(),
                $this->ttlInput->getValue()
            );
            $this->addStep = 0;
            $this->ttlInput->setActive(false);
            $this->status->setMode('NORMAL');
            $this->status->info('SET "' . $this->keyInput->getValue() . '" added to pipeline');
            return;
        }
        if ($key->is(KeyPress::KEY_ESC)) {
            $this->addStep = 0;
            $this->ttlInput->setActive(false);
            $this->status->setMode('NORMAL');
            return;
        }
        $this->routeInputKey($key, $this->ttlInput);
    }

    /**
     * Route keyboard input to an input widget.
     */
    private function routeInputKey(KeyPress $key, InputWidget $input): void
    {
        if ($key->is(KeyPress::KEY_BACKSPACE)) {
            $input->backspace();
        } elseif ($key->is(KeyPress::KEY_DELETE)) {
            $input->delete();
        } elseif ($key->is(KeyPress::KEY_LEFT)) {
            $input->moveCursorLeft();
        } elseif ($key->is(KeyPress::KEY_RIGHT)) {
            $input->moveCursorRight();
        } elseif ($key->isChar()) {
            $input->addChar($key->getChar());
        }
    }

    /**
     * Append a command to the pipeline queue.
     */
    private function appendCommand(string $type, string $key, string $value, string $ttl): void
    {
        $this->cmdTypes[]  = $type;
        $this->cmdKeys[]   = $key;
        $this->cmdValues[] = $value;
        $this->cmdTtls[]   = $ttl;
    }

    /**
     * Remove selected row from pipeline queue.
     */
    private function removeSelected(): void
    {
        $cursor = $this->table->getCursor();
        $count  = count($this->cmdTypes);

        if ($cursor < 0 || $cursor >= $count) {
            return;
        }

        $newTypes  = [];
        $newKeys   = [];
        $newValues = [];
        $newTtls   = [];

        for ($i = 0; $i < $count; $i++) {
            if ($i !== $cursor) {
                $newTypes[]  = $this->cmdTypes[$i];
                $newKeys[]   = $this->cmdKeys[$i];
                $newValues[] = $this->cmdValues[$i];
                $newTtls[]   = $this->cmdTtls[$i];
            }
        }

        $this->cmdTypes  = $newTypes;
        $this->cmdKeys   = $newKeys;
        $this->cmdValues = $newValues;
        $this->cmdTtls   = $newTtls;
        $this->status->info('Command removed from pipeline');
    }

    /**
     * Execute all queued pipeline commands.
     */
    private function executePipeline(): void
    {
        $count = count($this->cmdTypes);
        if ($count === 0) {
            $this->status->error('Pipeline is empty — add commands with "a"');
            return;
        }

        $ex      = null;
        $results = [];

        try {
            $pipelineRedis = $this->redis->multi(\Redis::PIPELINE);
            if ($pipelineRedis === false) {
                throw new \RuntimeException('Failed to start Redis pipeline (multi returned false)');
            }
            $driver   = new PhpRedisPipelineDriver($pipelineRedis);
            $pipeline = new RedisPipeline($driver);

            for ($i = 0; $i < $count; $i++) {
                $type  = $this->cmdTypes[$i];
                $key   = $this->cmdKeys[$i];
                $value = $this->cmdValues[$i];
                $ttl   = (int) ($this->cmdTtls[$i] ?? '0');

                if ($type === 'SET') {
                    $pipeline->set($key, $value, $ttl);
                    $results[] = 'SET ' . $key . ' = ' . $value . ($ttl > 0 ? ' (TTL: ' . $ttl . 's)' : '');
                } elseif ($type === 'DEL') {
                    $this->client->del($key);
                    $results[] = 'DEL ' . $key;
                } elseif ($type === 'INCR') {
                    $pipeline->incr($key);
                    $results[] = 'INCR ' . $key;
                } elseif ($type === 'LPUSH') {
                    $this->client->lpush($key, $value);
                    $results[] = 'LPUSH ' . $key . ' ' . $value;
                } elseif ($type === 'EXPIRE') {
                    $this->client->expire($key, (int) $value);
                    $results[] = 'EXPIRE ' . $key . ' ' . $value . 's';
                }
            }

            $pipeline->execute();
            $this->results = $results;
            $this->status->success('Pipeline executed: ' . $count . ' commands');
        } catch (\Throwable $e) {
            $ex = $e;
        }

        if ($ex !== null) {
            $this->status->error('Pipeline failed: ' . $ex->getMessage());
        }
    }

    /**
     * Sync internal data to table widget rows.
     */
    private function syncTableRows(): void
    {
        $rows  = [];
        $count = count($this->cmdTypes);

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                (string) ($i + 1),
                $this->cmdTypes[$i],
                $this->cmdKeys[$i],
                $this->cmdValues[$i],
                $this->cmdTtls[$i],
            ];
        }

        $this->table->setRows($rows);
    }
}
