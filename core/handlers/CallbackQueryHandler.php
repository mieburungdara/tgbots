<?php

namespace TGBot\Handlers;

use TGBot\App;

class CallbackQueryHandler implements HandlerInterface
{
    private $router;

    public function __construct()
    {
        $this->router = new CallbackRouter();
    }

    public function handle(App $app, array $callback_query): void
    {
        $callback_data = $callback_query['data'];

        // Handle no-op callbacks
        if ($callback_data === 'noop') {
            $app->telegram_api->answerCallbackQuery($callback_query['id']);
            return;
        }

        $route = $this->router->route($callback_data);

        if ($route) {
            $handler = $route['handler'];
            $params = $route['params'];
            $handler->execute($app, $callback_query, $params);
        } else {
            // Optionally, log or handle unknown callbacks
            $app->telegram_api->answerCallbackQuery($callback_query['id'], '⚠️ Perintah tidak dikenal.', true);
            app_log("Unknown callback query received: " . $callback_data, 'warning');
        }
    }
}
