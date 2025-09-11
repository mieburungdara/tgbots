<?php

namespace TGBot\Handlers;

use TGBot\Handlers\Callbacks\CallbackCommandInterface;
use TGBot\Handlers\Callbacks\ViewPageCallback;
use TGBot\Handlers\Callbacks\BuyCallback;
use TGBot\Handlers\Callbacks\RegisterSellerCallback;
use TGBot\Handlers\Callbacks\ShowChannelSelectionCallback;
use TGBot\Handlers\Callbacks\PostToChannelCallback;
use TGBot\Handlers\Callbacks\RetractPostCallback;
use TGBot\Handlers\Callbacks\AdminApprovalCallback;
use TGBot\Handlers\Callbacks\AdminRejectionCallback;
use TGBot\Handlers\Callbacks\AdminBanCallback;
use TGBot\Handlers\Callbacks\RateCallback;
use TGBot\Handlers\Callbacks\TanyaCallback;
use TGBot\Handlers\Callbacks\SellConfirmCallback;

class CallbackRouter
{
    private $routes = [];

    public function __construct()
    {
        $this->routes = [
            'view_page_' => new ViewPageCallback(),
            'buy_' => new BuyCallback(),
            'register_seller' => new RegisterSellerCallback(),
            'post_channel_' => new ShowChannelSelectionCallback(),
            'post_to_' => new PostToChannelCallback(),
            'retract_post_' => new RetractPostCallback(),
            'admin_approve_' => new AdminApprovalCallback(),
            'admin_reject_' => new AdminRejectionCallback(),
            'admin_ban_' => new AdminBanCallback(),
            'rate_' => new RateCallback(),
            'tanya_' => new TanyaCallback(),
            'sell_confirm_' => new SellConfirmCallback(),
        ];
    }

    public function route(string $callback_data): ?array
    {
        foreach ($this->routes as $prefix => $handler) {
            if (substr($prefix, -1) === '_') {
                // Prefix route
                if (strpos($callback_data, $prefix) === 0) {
                    $params = substr($callback_data, strlen($prefix));
                    return ['handler' => $handler, 'params' => $params];
                }
            } else {
                // Exact match route
                if ($callback_data === $prefix) {
                    return ['handler' => $handler, 'params' => ''];
                }
            }
        }

        return null;
    }
}
