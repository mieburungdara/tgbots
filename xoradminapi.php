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

$handler_response = ['status' => 'error', 'message' => 'Aksi tidak diketahui atau input tidak valid.'];
$post_action = $_POST['action'] ?? null;

if ($post_action === 'get_user_roles' && isset($_POST['user_id'])) {
    try {
        $user_id = (int)$_POST['user_id'];
        $stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $role_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $handler_response = ['status' => 'success', 'role_ids' => $role_ids];
    } catch (Exception $e) {
        $handler_response['message'] = 'Database error: ' . $e->getMessage();
    }
} elseif ($post_action === 'update_user_roles' && isset($_POST['user_id'], $_POST['role_ids'])) {
    try {
        $user_id = (int)$_POST['user_id'];
        $role_ids = json_decode($_POST['role_ids']);
        if (!is_array($role_ids)) {
            throw new Exception("Format role_ids tidak valid.");
        }

        $pdo->beginTransaction();

        // 1. Hapus semua peran lama pengguna
        $stmt_delete = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $stmt_delete->execute([$user_id]);

        // 2. Masukkan peran baru
        if (!empty($role_ids)) {
            $stmt_insert = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            foreach ($role_ids as $role_id) {
                $stmt_insert->execute([$user_id, (int)$role_id]);
            }
        }

        $pdo->commit();
        $handler_response = ['status' => 'success', 'message' => 'Peran berhasil diperbarui.'];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $handler_response['message'] = 'Database error: ' . $e->getMessage();
    }
} elseif (isset($_POST['bot_id']) && in_array($post_action, ['get-me', 'set-webhook', 'check-webhook', 'delete-webhook'])) {
    $bot_id = (int)$_POST['bot_id'];
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
