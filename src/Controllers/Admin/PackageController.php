<?php

require_once __DIR__ . '/../BaseController.php';
require_once __DIR__ . '/../../../core/database/PackageRepository.php';
require_once __DIR__ . '/../../../core/TelegramAPI.php';

class PackageController extends BaseController {

    public function index() {
        $pdo = get_db_connection();
        $packageRepo = new PackageRepository($pdo);

        if (session_status() == PHP_SESSION_NONE) session_start();
        $message = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);

        $packages = $packageRepo->findAll();

        $this->view('admin/packages/index', [
            'page_title' => 'Manajemen Konten',
            'packages' => $packages,
            'message' => $message
        ], 'admin_layout');
    }

    public function hardDelete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/packages');
            exit();
        }

        $pdo = get_db_connection();
        $packageRepo = new PackageRepository($pdo);
        $package_id_to_delete = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);

        if (session_status() == PHP_SESSION_NONE) session_start();

        if ($package_id_to_delete) {
            try {
                $package_info = $packageRepo->find($package_id_to_delete);
                if ($package_info && $package_info['bot_id']) {
                    $stmt_bot = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
                    $stmt_bot->execute([$package_info['bot_id']]);
                    $bot_token = $stmt_bot->fetchColumn();

                    if ($bot_token) {
                        $telegram_api = new TelegramAPI($bot_token);
                        $files_to_delete = $packageRepo->hardDeletePackage($package_id_to_delete);
                        foreach ($files_to_delete as $file) {
                            if ($file['storage_channel_id'] && $file['storage_message_id']) {
                                $telegram_api->deleteMessage($file['storage_channel_id'], $file['storage_message_id']);
                            }
                        }
                        $_SESSION['flash_message'] = "Paket #{$package_id_to_delete} berhasil dihapus permanen.";
                    } else {
                        throw new Exception("Token bot tidak ditemukan, tidak dapat menghapus pesan dari Telegram.");
                    }
                } else {
                     $packageRepo->hardDeletePackage($package_id_to_delete);
                     $_SESSION['flash_message'] = "Paket #{$package_id_to_delete} berhasil dihapus dari database (pesan di Telegram mungkin tidak terhapus).";
                }
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Error: " . $e->getMessage();
            }
        }

        header("Location: /admin/packages");
        exit;
    }
}
