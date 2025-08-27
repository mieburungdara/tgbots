<?php
// This file handles all AJAX requests from xoradmin.php

session_start();

// --- Konfigurasi & Inisialisasi ---
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

$is_authenticated = isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'];
$pdo = null;

if ($is_authenticated) {
    require_once __DIR__ . '/core/database.php';
    require_once __DIR__ . '/core/TelegramAPI.php';
    $pdo = get_db_connection();
}

// --- AJAX Request Handler ---
header('Content-Type: application/json');

if (!$is_authenticated || !$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid atau koneksi database gagal. Silakan login kembali.']);
    exit;
}

$handler_response = ['status' => 'error', 'message' => 'Aksi tidak diketahui atau ID bot tidak valid.'];
$post_action = $_POST['action'] ?? null;
$bot_id = isset($_POST['bot_id']) ? (int)$_POST['bot_id'] : 0;

if ($bot_id > 0 && in_array($post_action, ['get-me', 'set-webhook', 'check-webhook', 'delete-webhook'])) {
    try {
        $stmt_token = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
        $stmt_token->execute([$bot_id]);
        $token = $stmt_token->fetchColumn();
        if (!$token) throw new Exception("Bot tidak ditemukan.");

        $telegram_api = new TelegramAPI($token);
        $result = null;

        if ($post_action === 'get-me') {
            $bot_info = $telegram_api->getMe();
            if (!isset($bot_info['ok']) || !$bot_info['ok']) throw new Exception("Gagal mendapatkan info dari Telegram: " . ($bot_info['description'] ?? ''));
            $bot_result = $bot_info['result'];
            if ($bot_result['id'] != $bot_id) throw new Exception("Token tidak cocok dengan ID bot.");

            $stmt_update = $pdo->prepare("UPDATE bots SET first_name = ?, username = ? WHERE id = ?");
            $stmt_update->execute([$bot_result['first_name'], $bot_result['username'] ?? null, $bot_id]);

            $handler_response = ['status' => 'success', 'message' => 'Informasi bot diperbarui.', 'data' => $bot_result];
        } else { // Webhook actions
            if ($post_action === 'set-webhook') {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                // IMPORTANT: The path to webhook.php is calculated from the project root now.
                $webhook_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/webhook.php?id=' . $bot_id;
                $result = $telegram_api->setWebhook($webhook_url);
            } elseif ($post_action === 'check-webhook') {
                $result = $telegram_api->getWebhookInfo();
            } elseif ($post_action === 'delete-webhook') {
                $result = $telegram_api->deleteWebhook();
            }
            if ($result === false) throw new Exception("Gagal komunikasi dengan API Telegram.");
            $handler_response = ['status' => 'success', 'data' => $result];
        }
    } catch (Exception $e) {
        $handler_response['message'] = $e->getMessage();
    }
}

echo json_encode($handler_response);
exit;
