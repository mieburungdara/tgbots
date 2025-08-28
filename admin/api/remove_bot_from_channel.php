<?php
/**
 * API Endpoint untuk menghapus hubungan bot dari sebuah channel.
 */
session_start();
header('Content-Type: application/json');

// --- Dependensi ---
require_once __DIR__ . '/../../core/database.php';
require_once __DIR__ . '/../../core/database/PrivateChannelBotRepository.php';

// --- Keamanan & Inisialisasi ---
if (!isset($_SESSION['is_authenticated']) || !$_SESSION['is_authenticated']) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Sesi tidak valid.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode permintaan tidak valid.']);
    exit;
}

$pdo = get_db_connection();
if (!$pdo) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    exit;
}

// --- Input ---
$channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
$bot_id = filter_input(INPUT_POST, 'bot_id', FILTER_VALIDATE_INT);

if (!$channel_id || !$bot_id) {
    echo json_encode(['status' => 'error', 'message' => 'Input tidak valid: channel_id dan bot_id diperlukan.']);
    exit;
}

// --- Logika ---
try {
    $pcBotRepo = new PrivateChannelBotRepository($pdo);

    if ($pcBotRepo->removeBotFromChannel($channel_id, $bot_id)) {
        echo json_encode(['status' => 'success', 'message' => 'Bot berhasil dihapus dari channel.']);
    } else {
        throw new Exception("Gagal menghapus bot dari channel di database.");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

exit;
