<?php
/**
 * API Endpoint untuk mengambil log transaksi saldo admin untuk pengguna tertentu.
 * Mengembalikan data dalam format JSON.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../core/database.php';

$telegram_id = isset($_GET['telegram_id']) ? (int)$_GET['telegram_id'] : 0;

if (!$telegram_id) {
    echo json_encode(['error' => 'Telegram ID tidak valid.']);
    exit;
}

$pdo = get_db_connection();
if (!$pdo) {
    echo json_encode(['error' => 'Koneksi database gagal.']);
    exit;
}

try {
    // Cari user_id internal berdasarkan telegram_id
    $stmt_user = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
    $stmt_user->execute([$telegram_id]);
    $user_id = $stmt_user->fetchColumn();

    if (!$user_id) {
        echo json_encode(['error' => 'Pengguna tidak ditemukan.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT amount, type, description, created_at
         FROM balance_transactions
         WHERE user_id = ?
         ORDER BY created_at DESC"
    );
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($logs);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Gagal mengambil data transaksi.']);
}
?>
