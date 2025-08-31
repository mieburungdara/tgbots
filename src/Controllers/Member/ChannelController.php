<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot\Controllers\Member;

use Exception;
use TGBot\Controllers\Member\MemberBaseController;
use TGBot\Database\SellerSalesChannelRepository;
use TGBot\TelegramAPI;

/**
 * Class ChannelController
 * @package TGBot\Controllers\Member
 *
 * Kontroler ini menangani semua logika yang terkait dengan manajemen channel penjualan oleh member.
 * Ini memungkinkan member untuk mendaftarkan, melihat, dan mengelola channel penjualan mereka.
 */
class ChannelController extends MemberBaseController
{
    /**
     * Menampilkan halaman manajemen channel.
     *
     * Metode ini mengambil informasi tentang channel penjualan yang saat ini terdaftar untuk member,
     * serta daftar bot yang tersedia. Jika channel sudah terdaftar, metode ini juga mencoba mengambil
     * nama channel dan grup diskusi dari Telegram API untuk ditampilkan kepada pengguna.
     *
     * @return void
     */
    public function index(): void
    {
        try {
            $pdo = \get_db_connection();
            $channelRepo = new SellerSalesChannelRepository($pdo);
            $user_id = $_SESSION['member_user_id'];

            $error_message = $_SESSION['flash_error'] ?? null;
            $success_message = $_SESSION['flash_success'] ?? null;
            unset($_SESSION['flash_error'], $_SESSION['flash_success']);

            $all_bots = \get_all_bots($pdo);
            $current_channel = $channelRepo->findBySellerId($user_id);
            $bot_details = null;

            if ($current_channel && !empty($current_channel['bot_id'])) {
                $bot_details = \get_bot_details($pdo, $current_channel['bot_id']);
                try {
                    if ($bot_details) {
                        $telegram_api_for_info = new TelegramAPI($bot_details['token']);
                        $chat_info = $telegram_api_for_info->getChat($current_channel['channel_id']);
                        $current_channel['title'] = ($chat_info && $chat_info['ok']) ? $chat_info['result']['title'] : 'Info tidak tersedia';
                        if (!empty($current_channel['discussion_group_id'])) {
                             $group_info = $telegram_api_for_info->getChat($current_channel['discussion_group_id']);
                             $current_channel['group_title'] = ($group_info && $group_info['ok']) ? $group_info['result']['title'] : 'Info tidak tersedia';
                        }
                    }
                } catch (Exception $e) {
                    $error_message = $error_message ?? "Gagal menghubungi Telegram untuk memperbarui info: " . $e->getMessage();
                }
            }

            $this->view('member/channels/index', [
                'page_title' => 'Manajemen Channel',
                'all_bots' => $all_bots,
                'current_channel' => $current_channel,
                'bot_details' => $bot_details,
                'error_message' => $error_message,
                'success_message' => $success_message
            ], 'member_layout');
        } catch (Exception $e) {
            \app_log('Error in ChannelController/index: ' . $e->getMessage(), 'error');
            $this->view('member/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the channel management page.'
            ], 'member_layout');
        }
    }

    /**
     * Mendaftarkan channel penjualan baru.
     *
     * Metode ini memvalidasi input dari form pendaftaran, memeriksa apakah bot adalah admin di channel dan grup diskusi,
     * dan menyimpan informasi channel ke database jika semua validasi berhasil.
     *
     * @return void
     */
    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /member/channels');
            exit();
        }

        $last_check_time = $_SESSION['last_channel_check'] ?? 0;
        if (time() - $last_check_time < 60) {
            $_SESSION['flash_error'] = "Anda terlalu cepat mencoba. Harap tunggu 60 detik sebelum mencoba lagi.";
            header('Location: /member/channels');
            exit();
        }
        $_SESSION['last_channel_check'] = time();

        $pdo = \get_db_connection();
        $channelRepo = new SellerSalesChannelRepository($pdo);
        $user_id = $_SESSION['member_user_id'];

        $selected_bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);
        $channel_identifier = trim($_POST['channel_identifier']);
        $group_identifier = trim($_POST['group_identifier'] ?? '');

        if (empty($channel_identifier) || !$selected_bot_id || empty($group_identifier)) {
            $_SESSION['flash_error'] = "Harap pilih bot, isi ID/username channel, dan ID/username grup diskusi.";
            header('Location: /member/channels');
            exit();
        }

        try {
            $bot_token = \get_bot_token($pdo, $selected_bot_id);
            if (!$bot_token) throw new Exception("Bot yang dipilih tidak valid.");

            $telegram_api = new TelegramAPI($bot_token);

            $bot_member_channel = $telegram_api->getChatMember($channel_identifier, $selected_bot_id);
            if (!$bot_member_channel['ok'] || !in_array($bot_member_channel['result']['status'], ['administrator', 'creator'])) {
                throw new Exception("Verifikasi Channel Gagal: Pastikan bot adalah admin di channel.");
            }
            if (!($bot_member_channel['result']['can_post_messages'] ?? false)) {
                throw new Exception("Verifikasi Channel Gagal: Bot memerlukan izin 'Post Messages'.");
            }
            $channel_info = $telegram_api->getChat($channel_identifier);
            if (!$channel_info['ok']) throw new Exception("Gagal mengambil info channel.");

            $numeric_channel_id = $channel_info['result']['id'];
            $channel_title = $channel_info['result']['title'];
            $linked_chat_id = $channel_info['result']['linked_chat_id'] ?? null;
            if (!$linked_chat_id) throw new Exception("Channel tidak memiliki grup diskusi yang terhubung.");

            $group_info = $telegram_api->getChat($group_identifier);
            if (!$group_info['ok']) throw new Exception("Gagal mengambil info grup.");

            $numeric_group_id = $group_info['result']['id'];
            if ($numeric_group_id != $linked_chat_id) {
                throw new Exception("Grup diskusi tidak cocok dengan yang terhubung ke channel.");
            }

            $bot_member_group = $telegram_api->getChatMember($numeric_group_id, $selected_bot_id);
            if (!$bot_member_group['ok'] || !in_array($bot_member_group['result']['status'], ['administrator', 'creator'])) {
                throw new Exception("Bot harus menjadi admin di grup diskusi juga.");
            }

            if ($channelRepo->createOrUpdate($user_id, $selected_bot_id, $numeric_channel_id, $numeric_group_id)) {
                $_SESSION['flash_success'] = "Selamat! Channel '{$channel_title}' berhasil dikonfigurasi.";
            } else {
                throw new Exception("Gagal menyimpan ke database.");
            }

        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Error: " . $e->getMessage();
        }

        header('Location: /member/channels');
        exit();
    }
}
