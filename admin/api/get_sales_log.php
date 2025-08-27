<?php
/**
 * API Endpoint untuk mengambil log penjualan (pemasukan) untuk pengguna tertentu.
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
        "SELECT s.price, s.purchased_at, mp.title as package_title, u_buyer.first_name as buyer_name
         FROM sales s
         JOIN media_packages mp ON s.package_id = mp.id
         JOIN users u_buyer ON s.buyer_user_id = u_buyer.id
         WHERE s.seller_user_id = ?
         ORDER BY s.purchased_at DESC"
    );
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($logs);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Gagal mengambil data penjualan.']);
}
?>
