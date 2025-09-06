<?php

namespace TGBot\Handlers;

use TGBot\App;

class MessageHandler implements HandlerInterface
{
    private $commandRouter;
    private $stateHandler;
    private $automaticForwardHandler;

    public function __construct()
    {
        // In a real DI container, these would be injected.
        // For this refactoring, we'll instantiate them here for simplicity.
        $this->commandRouter = new CommandRouter();
        $this->stateHandler = new StateHandler();
        $this->automaticForwardHandler = new AutomaticForwardHandler();
    }

    public function handle(App $app, array $message): void
    {
        // 1. Handle automatic forwards
        if (isset($message['is_automatic_forward']) && $message['is_automatic_forward'] === true) {
            $this->automaticForwardHandler->handle($app, $message);
            return;
        }

        // 2. Handle commands
        if (isset($message['text']) && strpos($message['text'], '/') === 0) {
            $parts = explode(' ', $message['text']);
            $command_full = $parts[0];
            $command_parts = explode('@', $command_full);
            $command_name = $command_parts[0];

            $command = $this->commandRouter->getCommand($command_name);

            if ($command) {
                $command->execute($app, $message, $parts);
            }
            return;
        }

        // 3. Handle stateful messages (that are not commands)
        if (isset($app->user['state']) && $app->user['state'] !== null && isset($message['text'])) {
            $this->stateHandler->handle($app, $message);
            return;
        }
    }
}
