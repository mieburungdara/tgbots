<?php
/**
 * Halaman "Konten Saya" untuk Anggota.
 *
 * Halaman ini memungkinkan anggota (penjual) untuk melihat dan mengelola
 * semua paket konten yang telah mereka buat.
 */
session_start();

// Jika belum login, redirect ke halaman login
if (!isset($_SESSION['member_user_id'])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/database/PackageRepository.php';

$pdo = get_db_connection();
$packageRepo = new PackageRepository($pdo);
$user_id = $_SESSION['member_user_id'];

// Ambil semua paket milik pengguna yang sedang login
$my_packages = $packageRepo->findAllBySellerId($user_id);

$page_title = 'Konten Saya';
require_once __DIR__ . '/../partials/header.php';
?>

<h2>Konten Saya</h2>
<p>Di bawah ini adalah daftar semua konten yang telah Anda buat. Anda dapat mengelola setiap item dari sini.</p>

<div class="table-responsive" style="margin-top: 20px;">
    <table class="list-table">
        <thead>
            <tr>
                <th>ID Konten</th>
                <th>Deskripsi</th>
                <th>Harga</th>
                <th>Status</th>
                <th>Dibuat pada</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($my_packages)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">Anda belum membuat konten apa pun.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($my_packages as $package): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($package['public_id']) ?></strong></td>
                        <td><?= htmlspecialchars($package['description']) ?></td>
                        <td>Rp <?= number_format($package['price'] ?? 0, 0, ',', '.') ?></td>
                        <td><span class="status-badge status-<?= htmlspecialchars($package['status']) ?>"><?= ucfirst(htmlspecialchars($package['status'])) ?></span></td>
                        <td><?= htmlspecialchars(date('d M Y H:i', strtotime($package['created_at']))) ?></td>
                        <td>
                            <a href="#" class="btn btn-edit btn-sm">Edit</a>
                            <a href="#" class="btn btn-delete btn-sm">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- CSS untuk status badge, bisa dipindahkan ke header.php nanti jika diperlukan di tempat lain -->
<style>
.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
    color: #fff;
    text-transform: uppercase;
}
.status-available { background-color: #28a745; }
.status-pending { background-color: #ffc107; color: #333; }
.status-sold { background-color: #dc3545; }
.status-deleted { background-color: #6c757d; }
</style>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
