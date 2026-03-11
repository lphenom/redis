<?php

declare(strict_types=1);

namespace LPhenom\Redis\Cli\Screen;

use LPhenom\Redis\Cli\Terminal\KeyPress;
use LPhenom\Redis\Cli\Terminal\Renderer;

/**
 * Contract for all TUI screens.
 *
 * Each screen handles rendering and keyboard input.
 * Returning a screen name from handleInput() triggers navigation.
  *
 * @lphenom-build none
 */
interface ScreenInterface
{
    /**
     * Render the full screen.
     */
    public function render(Renderer $r): void;

    /**
     * Handle a key press.
     *
     * Returns a screen name to navigate to, 'quit' to exit,
     * or null to stay on current screen.
     */
    public function handleInput(KeyPress $key): ?string;

    /**
     * Called when this screen becomes active.
     * Use to refresh data, reset state, etc.
     */
    public function onActivate(): void;
}
