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
        ];
    }

    public function route(string $callback_data): ?array
    {
        foreach ($this->routes as $prefix => $handler) {
            if ($callback_data === $prefix) {
                // Exact match
                return ['handler' => $handler, 'params' => ''];
            }
            if (strpos($callback_data, $prefix) === 0) {
                $params = substr($callback_data, strlen($prefix));
                return ['handler' => $handler, 'params' => $params];
            }
        }

        return null;
    }
}
