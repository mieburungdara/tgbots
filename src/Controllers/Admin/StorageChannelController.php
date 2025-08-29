<?php

require_once __DIR__ . '/../BaseController.php';
require_once __DIR__ . '/../../../core/database/PrivateChannelRepository.php';
require_once __DIR__ . '/../../../core/database/BotRepository.php';
require_once __DIR__ . '/../../../core/TelegramAPI.php';

class StorageChannelController extends BaseController {

    public function index() {
        $pdo = get_db_connection();
        $channelRepo = new PrivateChannelRepository($pdo);
        $botRepo = new BotRepository($pdo);

        $message = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);

        $private_channels = $channelRepo->getAllChannels();
        $all_bots = $botRepo->getAllBots();

        $this->view('admin/storage_channels/index', [
            'page_title' => 'Kelola Channel Penyimpanan',
            'private_channels' => $private_channels,
            'all_bots' => $all_bots,
            'message' => $message
        ], 'admin_layout');
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/storage_channels');
            exit();
        }

        $pdo = get_db_connection();
        $channelRepo = new PrivateChannelRepository($pdo);
        $channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);

        if ($channel_id && $name) {
            if ($channelRepo->addChannel($channel_id, $name)) {
                $_SESSION['flash_message'] = "Channel '{$name}' berhasil ditambahkan.";
            } else {
                $_SESSION['flash_message'] = "Gagal menambahkan channel. Mungkin ID sudah ada.";
            }
        } else {
            $_SESSION['flash_message'] = "Data channel tidak valid.";
        }

        header("Location: /admin/storage_channels");
        exit;
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/storage_channels');
            exit();
        }

        $pdo = get_db_connection();
        $channelRepo = new PrivateChannelRepository($pdo);
        $channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
        $new_name = filter_input(INPUT_POST, 'new_name', FILTER_SANITIZE_STRING);
        $new_channel_id = filter_input(INPUT_POST, 'new_channel_id', FILTER_VALIDATE_INT);

        if ($channel_id && $new_name && $new_channel_id) {
            if ($channelRepo->updateChannel($channel_id, $new_name, $new_channel_id)) {
                $_SESSION['flash_message'] = "Channel berhasil diperbarui.";
            } else {
                $_SESSION['flash_message'] = "Gagal memperbarui channel.";
            }
        } else {
            $_SESSION['flash_message'] = "Data untuk pembaruan channel tidak valid.";
        }

        header("Location: /admin/storage_channels");
        exit;
    }

    public function setDefault() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/storage_channels');
            exit();
        }

        $pdo = get_db_connection();
        $channelRepo = new PrivateChannelRepository($pdo);
        $channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);

        if ($channel_id) {
            if ($channelRepo->setDefaultChannel($channel_id)) {
                $_SESSION['flash_message'] = "Channel default berhasil diatur.";
            } else {
                $_SESSION['flash_message'] = "Gagal mengatur channel default.";
            }
        } else {
            $_SESSION['flash_message'] = "ID Channel tidak valid.";
        }

        header("Location: /admin/storage_channels");
        exit;
    }

    public function destroy() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/storage_channels');
            exit();
        }

        $pdo = get_db_connection();
        $channelRepo = new PrivateChannelRepository($pdo);
        $channel_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($channel_id) {
            if ($channelRepo->deleteChannel($channel_id)) {
                $_SESSION['flash_message'] = "Channel berhasil dihapus.";
            } else {
                $_SESSION['flash_message'] = "Gagal menghapus channel.";
            }
        } else {
            $_SESSION['flash_message'] = "ID Channel tidak valid untuk penghapusan.";
        }

        header("Location: /admin/storage_channels");
        exit;
    }

    // --- API Methods ---

    public function getBots() {
        if (!isset($_GET['channel_id']) || !filter_var($_GET['channel_id'], FILTER_VALIDATE_INT)) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Input tidak valid: channel_id diperlukan.'], 400);
        }
        $telegram_channel_id = (int)$_GET['channel_id'];
        $pdo = get_db_connection();
        require_once __DIR__ . '/../../../core/database/PrivateChannelBotRepository.php';
        try {
            $channelRepo = new PrivateChannelRepository($pdo);
            $channel = $channelRepo->findByTelegramId($telegram_channel_id);
            if (!$channel) throw new Exception("Channel tidak ditemukan.");

            $pcBotRepo = new PrivateChannelBotRepository($pdo);
            $bots = $pcBotRepo->getBotsForChannel($channel['id']);
            return $this->jsonResponse(['status' => 'success', 'bots' => $bots]);
        } catch (Exception $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function addBot() {
        $telegram_channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
        $bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);
        if (!$telegram_channel_id || !$bot_id) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Input tidak valid.'], 400);
        }
        $pdo = get_db_connection();
        require_once __DIR__ . '/../../../core/database/PrivateChannelBotRepository.php';
        try {
            $channelRepo = new PrivateChannelRepository($pdo);
            $channel = $channelRepo->findByTelegramId($telegram_channel_id);
            if (!$channel) throw new Exception("Channel tidak ditemukan.");

            $pcBotRepo = new PrivateChannelBotRepository($pdo);
            if ($pcBotRepo->isBotInChannel($channel['id'], $bot_id)) {
                throw new Exception("Bot ini sudah ditambahkan ke channel tersebut.");
            }
            if ($pcBotRepo->addBotToChannel($channel['id'], $bot_id)) {
                return $this->jsonResponse(['status' => 'success', 'message' => 'Bot berhasil ditambahkan ke channel.']);
            }
            throw new Exception("Gagal menambahkan bot ke channel di database.");
        } catch (Exception $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function removeBot() {
        $telegram_channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
        $bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);
        if (!$telegram_channel_id || !$bot_id) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Input tidak valid.'], 400);
        }
        $pdo = get_db_connection();
        require_once __DIR__ . '/../../../core/database/PrivateChannelBotRepository.php';
        try {
            $channelRepo = new PrivateChannelRepository($pdo);
            $channel = $channelRepo->findByTelegramId($telegram_channel_id);
            if (!$channel) throw new Exception("Channel tidak ditemukan.");

            $pcBotRepo = new PrivateChannelBotRepository($pdo);
            if ($pcBotRepo->removeBotFromChannel($channel['id'], $bot_id)) {
                return $this->jsonResponse(['status' => 'success', 'message' => 'Bot berhasil dihapus dari channel.']);
            }
            throw new Exception("Gagal menghapus bot dari channel di database.");
        } catch (Exception $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function verifyBot() {
        $telegram_channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
        $bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);
        if (!$telegram_channel_id || !$bot_id) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Input tidak valid.'], 400);
        }
        $pdo = get_db_connection();
        require_once __DIR__ . '/../../../core/database/PrivateChannelBotRepository.php';
        try {
            $botRepo = new BotRepository($pdo);
            $channelRepo = new PrivateChannelRepository($pdo);
            $pcBotRepo = new PrivateChannelBotRepository($pdo);

            $bot = $botRepo->findBotByTelegramId($bot_id);
            if (!$bot) throw new Exception("Bot tidak ditemukan.");
            $channel = $channelRepo->findByTelegramId($telegram_channel_id);
            if (!$channel) throw new Exception("Channel tidak ditemukan.");

            $telegram_api = new TelegramAPI($bot['token']);
            $member_info = $telegram_api->getChatMember($telegram_channel_id, $bot_id);

            if (!$member_info['ok']) {
                throw new Exception("Gagal memeriksa status bot. Pesan dari Telegram: " . ($member_info['description'] ?? 'Tidak ada info'));
            }
            $status = $member_info['result']['status'];
            if (in_array($status, ['creator', 'administrator'])) {
                if (!$pcBotRepo->isBotInChannel($channel['id'], $bot_id)) {
                    $pcBotRepo->addBotToChannel($channel['id'], $bot_id);
                }
                if ($pcBotRepo->verifyBotInChannel($channel['id'], $bot_id)) {
                    return $this->jsonResponse(['status' => 'success', 'message' => "Verifikasi berhasil! Bot adalah '{$status}'."]);
                }
                throw new Exception("Gagal memperbarui status verifikasi di database.");
            } else {
                return $this->jsonResponse(['status' => 'error', 'message' => "Verifikasi Gagal. Bot bukan admin (status: {$status})."]);
            }
        } catch (Exception $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
