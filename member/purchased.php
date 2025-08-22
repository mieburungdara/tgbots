<?php
/**
 * Halaman Konten Dibeli (Panel Anggota).
 *
 * Halaman ini menampilkan daftar semua paket konten yang telah dibeli
 * oleh pengguna yang sedang login.
 *
 * Logika:
 * - Memastikan pengguna sudah login, jika tidak, alihkan ke halaman login.
 * - Mengambil semua data penjualan yang terkait dengan `user_id` pengguna
 *   dari `SaleRepository`.
 * - Menampilkan setiap paket yang dibeli dalam format kartu (card).
 * - Tombol "Unduh Konten" memberikan instruksi kepada pengguna untuk menggunakan
 *   perintah `/konten` di bot.
 */
session_start();

// Jika belum login, redirect ke halaman login
if (!isset($_SESSION['member_user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/database/SaleRepository.php';

$pdo = get_db_connection();
$saleRepo = new SaleRepository($pdo);

$user_id = $_SESSION['member_user_id'];
$purchased_packages = $saleRepo->findPackagesByBuyerId($user_id);

$page_title = 'Konten Dibeli';
require_once __DIR__ . '/../partials/header.php';
?>

<style>
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
    .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
    .card-thumbnail { width: 100%; height: 180px; background-color: #eee; text-align: center; line-height: 180px; font-size: 2rem; color: #ccc; }
    .card-body { padding: 1rem; }
    .card-title { font-size: 1.1rem; font-weight: bold; margin: 0 0 0.5rem 0; }
    .card-text { font-size: 0.9rem; color: #606770; margin-bottom: 0.5rem; }
    .card-price { font-size: 1rem; font-weight: bold; color: #28a745; }
    .no-content { background: white; padding: 2rem; text-align: center; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .btn-download { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9rem; }
    .btn-download:hover { background-color: #0056b3; }
</style>

<h1>Konten yang Anda Beli</h1>

<?php if (empty($purchased_packages)): ?>
            <div class="no-content">
                <p>Anda belum membeli konten apapun.</p>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($purchased_packages as $package): ?>
                    <div class="card">
                        <div class="card-thumbnail">
                            <?php if (!empty($package['thumbnail_file_id'])): ?>
                                <span>🖼️</span>
                            <?php else: ?>
                                <span>❔</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <p class="card-text">Dibeli pada: <?= htmlspecialchars(date('d F Y', strtotime($package['purchased_at']))) ?></p>
                            <h2 class="card-title"><?= htmlspecialchars($package['description'] ?: 'Tanpa deskripsi') ?></h2>
                            <p class="card-price">Harga Beli: Rp <?= number_format($package['price'], 0, ',', '.') ?></p>
                            <a href="#" class="btn-download" onclick="alert('Gunakan perintah /konten <?= $package['public_id'] ?> di bot untuk mengunduh.'); return false;">
                                Unduh Konten
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
