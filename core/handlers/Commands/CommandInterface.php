<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;

interface CommandInterface
{
    public function execute(App $app, array $message, array $parts): void;
}
