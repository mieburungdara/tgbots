<?php

namespace TGBot\Handlers;

use TGBot\Handlers\States\StateInterface;
use TGBot\Handlers\States\SellingProcessState;
use TGBot\Handlers\States\AwaitingGiftRecipientState;
use TGBot\Handlers\States\AwaitingOfferPriceState;
use TGBot\Handlers\States\AwaitingGiftTypeSelectionState;

class StateRouter
{
    private $states = [];

    public function __construct()
    {
        $this->states = [
            'selling_process' => new SellingProcessState(),
            'awaiting_gift_recipient' => new AwaitingGiftRecipientState(),
            'awaiting_offer_price' => new AwaitingOfferPriceState(),
            'awaiting_gift_type_selection' => new AwaitingGiftTypeSelectionState(),
            // Add other states here as they are created
        ];
    }

    public function getState(string $state_name): ?StateInterface
    {
        return $this->states[$state_name] ?? null;
    }
}
