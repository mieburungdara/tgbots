<?php

namespace TGBot\Handlers;

use TGBot\App;
use TGBot\Database\UserRepository;

class StateHandler
{
    private $router;

    public function __construct()
    {
        $this->router = new StateRouter();
    }

    public function handle(App $app, array $message): void
    {
        $text = $message['text'];

        // The /cancel command is universal and can be handled here
        if (strpos($text, '/cancel') === 0) {
            $user_repo = new UserRepository($app->pdo, $app->bot['id']);
            $user_repo->setUserState($app->user['id'], null, null);
            $app->telegram_api->sendMessage($app->chat_id, "Operasi dibatalkan.");
            return;
        }

        $state_name = $app->user['state'];
        $state_handler = $this->router->getState($state_name);

        if ($state_handler) {
            $state_context = json_decode($app->user['state_context'] ?? '{}', true);
            $state_handler->handle($app, $message, $state_context);
        } else {
            // Optional: Log or handle unknown states
            app_log("Unknown state encountered: " . $state_name, 'warning');
        }
    }
}
