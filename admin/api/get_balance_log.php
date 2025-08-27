<?php
/**
 * API Endpoint untuk mengambil log transaksi saldo admin untuk pengguna tertentu.
 * Mengembalikan data dalam format JSON.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../core/database.php';

$user_id = isset($_GET['telegram_id']) ? (int)$_GET['telegram_id'] : 0;

if (!$user_id) {
    echo json_encode(['error' => 'Telegram ID tidak valid.']);
    exit;
}

$pdo = get_db_connection();
if (!$pdo) {
    echo json_encode(['error' => 'Koneksi database gagal.']);
    exit;
}

try {
    // Langsung gunakan telegram_id untuk query
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
