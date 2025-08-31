<?php

namespace TGBot\Controllers\Admin;



use Exception;
use TGBot\Controllers\BaseController;
use TGBot\Database\SellerSalesChannelRepository;
use TGBot\TelegramAPI;

class SalesChannelController extends BaseController
{
    public function index()
    {
        try {
            $pdo = \get_db_connection();
            $repo = new SellerSalesChannelRepository($pdo);
            $sales_channels = $repo->getAllSalesChannelsForAdmin();

            // Ambil Nama Channel/Grup dari Telegram API
            // Ini bisa lambat jika ada banyak channel. Caching akan menjadi ide bagus di masa depan.
            foreach ($sales_channels as $key => $channel) {
                $bot_token = \get_bot_token($pdo, $channel['bot_id']);
                if ($bot_token) {
                    try {
                        $telegram_api = new TelegramAPI($bot_token);

                        // Ambil nama channel
                        $chat_info = $telegram_api->getChat($channel['channel_id']);
                        $sales_channels[$key]['channel_title'] = ($chat_info && $chat_info['ok']) ? $chat_info['result']['title'] : 'Tidak Ditemukan';

                        // Ambil nama grup diskusi
                        $group_info = $telegram_api->getChat($channel['discussion_group_id']);
                        $sales_channels[$key]['group_title'] = ($group_info && $group_info['ok']) ? $group_info['result']['title'] : 'Tidak Ditemukan';

                    } catch (Exception $e) {
                        $sales_channels[$key]['channel_title'] = 'Error API';
                        $sales_channels[$key]['group_title'] = 'Error API';
                    }
                } else {
                    $sales_channels[$key]['channel_title'] = 'Bot Tidak Valid';
                    $sales_channels[$key]['group_title'] = 'Bot Tidak Valid';
                }
            }

            $this->view('admin/sales_channels/index', [
                'page_title' => 'Manajemen Channel Jualan',
                'sales_channels' => $sales_channels
            ], 'admin_layout');
        } catch (Exception $e) {
            \app_log('Error in SalesChannelController/index: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the sales channel management page.'
            ], 'admin_layout');
        }
    }
}
