<?php

namespace TGBot\Handlers\States;

use TGBot\App;

interface StateInterface
{
    public function handle(App $app, array $message, array $state_context): void;
}
