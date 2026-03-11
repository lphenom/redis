<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Screen;

use LPhenom\Redis\Cli\Terminal\InputReader;
use LPhenom\Redis\Cli\Terminal\KeyPress;
use LPhenom\Redis\Cli\Terminal\Renderer;

/**
 * Main event loop and screen router.
 *
 * Manages screen registry and routes keyboard input
 * to the currently active screen.
 */
final class ScreenRouter
{
    /** @var array<string, ScreenInterface> */
    private array $screens;

    /** @var string */
    private string $current;

    /** @var Renderer */
    private Renderer $renderer;

    /** @var InputReader */
    private InputReader $input;

    /** @var bool */
    private bool $running;

    public function __construct(Renderer $renderer, InputReader $input)
    {
        $this->renderer = $renderer;
        $this->input    = $input;
        $this->screens  = [];
        $this->current  = '';
        $this->running  = false;
    }

    /**
     * Register a screen by name.
     */
    public function register(string $name, ScreenInterface $screen): void
    {
        $this->screens[$name] = $screen;
    }

    /**
     * Start the event loop on a given screen.
     */
    public function run(string $initialScreen): void
    {
        $this->running = true;
        $this->navigateTo($initialScreen);

        while ($this->running) {
            // Render current screen
            $screen = $this->screens[$this->current] ?? null;
            if ($screen === null) {
                break;
            }

            $this->renderer->clear();
            $screen->render($this->renderer);
            $this->renderer->flush();

            // Read input
            $key = $this->input->read(100);

            if ($key === null) {
                continue;
            }

            // Global quit
            if ($key->is(KeyPress::KEY_CTRL_C) || $key->is(KeyPress::KEY_CTRL_D)) {
                $this->running = false;
                break;
            }

            $next = $screen->handleInput($key);

            if ($next === 'quit') {
                $this->running = false;
                break;
            }

            if ($next !== null && isset($this->screens[$next])) {
                $this->navigateTo($next);
            }
            // Unknown screen name is silently ignored
        }
    }

    /**
     * Navigate to a named screen.
     */
    private function navigateTo(string $name): void
    {
        $this->current = $name;
        $screen = $this->screens[$name] ?? null;
        if ($screen !== null) {
            $screen->onActivate();
        }
    }

    /**
     * Externally navigate to a named screen (e.g. from within a screen handler).
     */
    public function goto(string $name): void
    {
        if (isset($this->screens[$name])) {
            $this->navigateTo($name);
        }
    }

    public function getCurrent(): string
    {
        return $this->current;
    }
}
