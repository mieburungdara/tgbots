<?php

namespace TGBot\Handlers\Callbacks;

use TGBot\App;

interface CallbackCommandInterface
{
    public function execute(App $app, array $callback_query, string $params): void;
}
