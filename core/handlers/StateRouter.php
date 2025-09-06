<?php

namespace TGBot\Handlers;

use TGBot\Handlers\States\StateInterface;
use TGBot\Handlers\States\AwaitingPriceState;

class StateRouter
{
    private $states = [];

    public function __construct()
    {
        $this->states = [
            'awaiting_price' => new AwaitingPriceState(),
            // Add other states here as they are created
        ];
    }

    public function getState(string $state_name): ?StateInterface
    {
        return $this->states[$state_name] ?? null;
    }
}
