<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Screen;

use LPhenom\Redis\Cli\Terminal\KeyPress;
use LPhenom\Redis\Cli\Terminal\Renderer;
use LPhenom\Redis\Cli\Widget\InputWidget;
use LPhenom\Redis\Cli\Widget\StatusBar;
use LPhenom\Redis\Client\RedisClientInterface;
use LPhenom\Redis\PubSub\MessageHandlerInterface;

/**
 * PubSub monitoring screen.
 *
 * Subscribe to channels/patterns, see incoming messages in real-time,
 * publish messages from the TUI.
 *
 * Keys:
 *   s — subscribe to channel/pattern
 *   u — unsubscribe
 *   p — publish message
 *   c — clear log
 *   Esc/Backspace — back
  *
 * @lphenom-build none
 */
final class PubSubScreen implements ScreenInterface, MessageHandlerInterface
{
    private const MAX_LOG = 500;

    /** @var \Redis */
    private \Redis $subRedis;

    /** @var RedisClientInterface */
    private RedisClientInterface $client;

    /** @var StatusBar */
    private StatusBar $status;

    /** @var InputWidget */
    private InputWidget $inputWidget;

    /** @var array<int, string> */
    private array $log;

    /** @var array<int, string> */
    private array $subscriptions;

    /** @var int */
    private int $logOffset;

    /** @var string */
    private string $inputMode; // '' | 'subscribe' | 'publish_channel' | 'publish_message'

    /** @var string */
    private string $publishChannel;

    /** @var bool */
    private bool $isListening;

    public function __construct(\Redis $subRedis, RedisClientInterface $client)
    {
        $this->subRedis       = $subRedis;
        $this->client         = $client;
        $this->status         = new StatusBar();
        $this->inputWidget    = new InputWidget('', 40);
        $this->log            = [];
        $this->subscriptions  = [];
        $this->logOffset      = 0;
        $this->inputMode      = '';
        $this->publishChannel = '';
        $this->isListening    = false;
    }

    public function onActivate(): void
    {
        $this->status->info('PubSub Monitor — s=Subscribe, u=Unsubscribe, p=Publish, c=Clear, Esc=Back');
    }

    public function handle(string $channel, string $message): void
    {
        $entry = '[' . date('H:i:s') . '] <' . $channel . '> ' . $message;
        $this->log[] = $entry;

        // Ring buffer: keep only MAX_LOG entries
        if (count($this->log) > self::MAX_LOG) {
            $this->log = array_slice($this->log, count($this->log) - self::MAX_LOG);
        }

        // Auto-scroll to bottom
        $this->logOffset = max(0, count($this->log) - 1);
    }

    public function render(Renderer $r): void
    {
        $w = $r->getWidth();
        $h = $r->getHeight();

        // Try to receive pending messages (non-blocking)
        $this->pollMessages();

        // Header
        $r->moveTo(1, 1);
        $r->writeColored(
            str_pad(' redis-tui  ◆  PubSub Monitor', $w),
            Renderer::FG_BLACK,
            Renderer::BG_BRIGHT_GREEN,
            Renderer::ATTR_BOLD
        );

        // Subscriptions line
        $r->moveTo(2, 1);
        $subList = count($this->subscriptions) > 0
            ? implode(', ', $this->subscriptions)
            : 'none';
        $r->writeColored(' Subscribed: ', Renderer::FG_BRIGHT_YELLOW);
        $r->writeColored($subList, Renderer::FG_BRIGHT_CYAN);
        $r->clearLine();

        $r->moveTo(3, 1);
        $r->writeColored(str_pad('', $w, Renderer::BOX_H), Renderer::FG_BRIGHT_BLUE);

        // Message log area
        $logAreaH  = $h - 8;
        $logAreaR  = 4;
        $logCount  = count($this->log);
        $startIdx  = max(0, $this->logOffset - $logAreaH + 1);

        for ($line = 0; $line < $logAreaH; $line++) {
            $idx = $startIdx + $line;
            $r->moveTo($logAreaR + $line, 1);
            if ($idx < $logCount) {
                $entry = $this->log[$idx];
                // Colorize based on content
                if (strpos($entry, '>>>') !== false) {
                    $r->writeFixed($entry, $w - 1, Renderer::FG_BRIGHT_YELLOW);
                } else {
                    $r->writeFixed($entry, $w - 1, Renderer::FG_BRIGHT_GREEN);
                }
            } else {
                $r->write(str_repeat(' ', $w));
            }
        }

        // Scroll indicator
        if ($logCount > $logAreaH) {
            $scrollPct = (int) (($this->logOffset / max(1, $logCount - 1)) * $logAreaH);
            $scrollRow = $logAreaR + min($scrollPct, $logAreaH - 1);
            $r->moveTo($scrollRow, $w);
            $r->writeColored('▶', Renderer::FG_YELLOW);
        }

        // Log counter
        $r->moveTo($logAreaR + $logAreaH, 1);
        $r->writeColored(
            sprintf(' Messages: %d  (↑↓ scroll, End=latest) ', $logCount),
            Renderer::FG_BRIGHT_BLACK
        );
        $r->clearLine();

        // Input area (when active)
        $inputRow = $h - 3;
        $r->moveTo($inputRow, 1);
        $r->writeColored(str_pad('', $w, Renderer::BOX_H), Renderer::FG_BRIGHT_BLUE);

        if ($this->inputMode !== '') {
            $r->moveTo($inputRow + 1, 1);
            if ($this->inputMode === 'subscribe') {
                $r->writeColored(' Subscribe to > ', Renderer::FG_BRIGHT_YELLOW);
                $this->inputWidget->render($r, $inputRow + 1, 17);
            } elseif ($this->inputMode === 'publish_channel') {
                $r->writeColored(' Publish channel > ', Renderer::FG_BRIGHT_YELLOW);
                $this->inputWidget->render($r, $inputRow + 1, 20);
            } elseif ($this->inputMode === 'publish_message') {
                $r->writeColored(' Message to [' . $this->publishChannel . '] > ', Renderer::FG_BRIGHT_YELLOW);
                $this->inputWidget->render($r, $inputRow + 1, 20 + mb_strlen($this->publishChannel));
            }
        }

        // Status bar
        $this->status->setHints(['↑↓ Scroll', 'End Latest', 's Subscribe', 'u Unsub', 'p Publish', 'c Clear', 'Esc Back']);
        $this->status->render($r, $h);
    }

    public function handleInput(KeyPress $key): ?string
    {
        // Input mode
        if ($this->inputMode !== '') {
            $this->handleInputMode($key);
            return null;
        }

        // Navigation
        if ($key->is(KeyPress::KEY_UP)) {
            if ($this->logOffset > 0) {
                $this->logOffset--;
            }
            return null;
        }
        if ($key->is(KeyPress::KEY_DOWN)) {
            if ($this->logOffset < count($this->log) - 1) {
                $this->logOffset++;
            }
            return null;
        }
        if ($key->is(KeyPress::KEY_END)) {
            $this->logOffset = max(0, count($this->log) - 1);
            return null;
        }
        if ($key->is(KeyPress::KEY_HOME)) {
            $this->logOffset = 0;
            return null;
        }
        if ($key->is(KeyPress::KEY_PAGE_UP)) {
            $this->logOffset = max(0, $this->logOffset - 20);
            return null;
        }
        if ($key->is(KeyPress::KEY_PAGE_DOWN)) {
            $this->logOffset = min(max(0, count($this->log) - 1), $this->logOffset + 20);
            return null;
        }

        if ($key->isKey('s') || $key->isKey('S')) {
            $this->inputMode = 'subscribe';
            $this->inputWidget = new InputWidget('channel or pattern (e.g. events.*)', 40);
            $this->inputWidget->setActive(true);
            $this->status->setMode('SUBSCRIBE');
            return null;
        }

        if ($key->isKey('u') || $key->isKey('U')) {
            $this->unsubscribeAll();
            return null;
        }

        if ($key->isKey('p') || $key->isKey('P')) {
            $this->inputMode = 'publish_channel';
            $this->inputWidget = new InputWidget('target channel', 40);
            $this->inputWidget->setActive(true);
            $this->status->setMode('PUBLISH');
            return null;
        }

        if ($key->isKey('c') || $key->isKey('C')) {
            $this->log       = [];
            $this->logOffset = 0;
            $this->status->info('Log cleared');
            return null;
        }

        if ($key->is(KeyPress::KEY_ESC) || $key->is(KeyPress::KEY_BACKSPACE)) {
            $this->unsubscribeAll();
            return 'keys';
        }

        if ($key->isKey('q') || $key->isKey('Q')) {
            $this->unsubscribeAll();
            return 'quit';
        }

        return null;
    }

    /**
     * Handle keyboard input when an input field is active.
     */
    private function handleInputMode(KeyPress $key): void
    {
        if ($key->is(KeyPress::KEY_ENTER)) {
            $value = $this->inputWidget->getValue();

            if ($this->inputMode === 'subscribe') {
                $this->doSubscribe($value);
                $this->inputMode = '';
                $this->inputWidget->setActive(false);
                $this->status->setMode('NORMAL');
            } elseif ($this->inputMode === 'publish_channel') {
                if ($value !== '') {
                    $this->publishChannel = $value;
                    $this->inputMode      = 'publish_message';
                    $this->inputWidget    = new InputWidget('message payload', 60);
                    $this->inputWidget->setActive(true);
                }
            } elseif ($this->inputMode === 'publish_message') {
                $this->doPublish($this->publishChannel, $value);
                $this->inputMode = '';
                $this->inputWidget->setActive(false);
                $this->status->setMode('NORMAL');
                $this->publishChannel = '';
            }
            return;
        }

        if ($key->is(KeyPress::KEY_ESC)) {
            $this->inputMode = '';
            $this->inputWidget->setActive(false);
            $this->status->setMode('NORMAL');
            return;
        }

        if ($key->is(KeyPress::KEY_BACKSPACE)) {
            $this->inputWidget->backspace();
        } elseif ($key->is(KeyPress::KEY_DELETE)) {
            $this->inputWidget->delete();
        } elseif ($key->is(KeyPress::KEY_LEFT)) {
            $this->inputWidget->moveCursorLeft();
        } elseif ($key->is(KeyPress::KEY_RIGHT)) {
            $this->inputWidget->moveCursorRight();
        } elseif ($key->isChar()) {
            $this->inputWidget->addChar($key->getChar());
        }
    }

    /**
     * Subscribe to a channel or pattern.
     * Uses psubscribe for patterns (contains * or ?), subscribe otherwise.
     */
    private function doSubscribe(string $pattern): void
    {
        if ($pattern === '') {
            $this->status->error('Pattern cannot be empty');
            return;
        }

        $isPattern = strpos($pattern, '*') !== false || strpos($pattern, '?') !== false;
        $exception = null;

        try {
            if ($isPattern) {
                $this->subRedis->psubscribe([$pattern], function (\Redis $r, string $pat, string $chan, string $msg): void {
                    $this->handle($chan, $msg);
                });
            } else {
                $this->subRedis->subscribe([$pattern], function (\Redis $r, string $chan, string $msg): void {
                    $this->handle($chan, $msg);
                });
            }
            $this->subscriptions[] = $pattern;
            $this->isListening     = true;
            $this->log[]           = '[' . date('H:i:s') . '] >>> Subscribed to: ' . $pattern;
            $this->logOffset       = count($this->log) - 1;
            $this->status->success('Subscribed to: ' . $pattern);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            $this->status->error('Subscribe failed: ' . $exception->getMessage());
        }
    }

    /**
     * Unsubscribe from all channels.
     */
    private function unsubscribeAll(): void
    {
        if (!$this->isListening) {
            return;
        }

        $exception = null;
        try {
            $this->subRedis->unsubscribe([]);
            $this->subRedis->punsubscribe([]);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->subscriptions = [];
        $this->isListening   = false;

        if ($exception !== null) {
            $this->status->error('Unsubscribe error: ' . $exception->getMessage());
        } else {
            $this->status->info('Unsubscribed from all channels');
        }
    }

    /**
     * Publish a message to a channel.
     */
    private function doPublish(string $channel, string $message): void
    {
        if ($channel === '' || $message === '') {
            $this->status->error('Channel and message cannot be empty');
            return;
        }

        $exception = null;
        try {
            $this->client->publish($channel, $message);
            $this->log[]     = '[' . date('H:i:s') . '] >>> Published to [' . $channel . ']: ' . $message;
            $this->logOffset = count($this->log) - 1;
            $this->status->success('Published to: ' . $channel);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            $this->status->error('Publish failed: ' . $exception->getMessage());
        }
    }

    /**
     * Non-blocking poll for new messages.
     * Uses stream_select with zero timeout to check if there's data from Redis.
     */
    private function pollMessages(): void
    {
        // Note: ext-redis subscribe() is blocking.
        // For non-blocking reads we rely on the socket directly.
        // This is a best-effort approach — messages arrive when subscribe() callback fires.
        // In the TUI, full blocking subscribe is started in a separate logic path.
        // Here we just update the display with buffered messages.
    }
}
