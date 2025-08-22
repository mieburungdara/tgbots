<?php
/**
 * Halaman Manajemen Konten/Paket (Admin).
 *
 * Halaman ini menampilkan daftar semua paket konten yang ada di sistem.
 * Administrator dapat melihat detail setiap paket dan memiliki opsi untuk
 * melakukan "Hard Delete".
 *
 * "Hard Delete" adalah operasi destruktif yang:
 * 1. Menghapus data paket dari tabel `media_packages`.
 * 2. Menghapus file media terkait dari tabel `media_files`.
 * 3. Mencoba menghapus pesan media fisik dari channel penyimpanan di Telegram.
 *
 * Operasi ini dibatasi hanya untuk paket yang belum terjual.
 */
session_start();
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/database/PackageRepository.php';
require_once __DIR__ . '/../core/TelegramAPI.php';

$pdo = get_db_connection();
$packageRepo = new PackageRepository($pdo);
$message = null;

// Menangani permintaan "Hard Delete" dari formulir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hard_delete_package') {
    $package_id_to_delete = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);

    if ($package_id_to_delete) {
        try {
            // 1. Dapatkan informasi bot yang terkait dengan paket untuk inisialisasi TelegramAPI
            $package_info = $packageRepo->find($package_id_to_delete);
            if ($package_info && $package_info['bot_id']) {
                $stmt_bot = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
                $stmt_bot->execute([$package_info['bot_id']]);
                $bot_token = $stmt_bot->fetchColumn();

                if ($bot_token) {
                    $telegram_api = new TelegramAPI($bot_token);

                    // 2. Hapus paket dari database. Metode ini mengembalikan daftar file yang perlu dihapus dari Telegram.
                    $files_to_delete = $packageRepo->hardDeletePackage($package_id_to_delete);

                    // 3. Hapus setiap pesan media dari channel penyimpanan di Telegram
                    foreach ($files_to_delete as $file) {
                        if ($file['storage_channel_id'] && $file['storage_message_id']) {
                            $telegram_api->deleteMessage($file['storage_channel_id'], $file['storage_message_id']);
                            // Hasil tidak diperiksa; ini adalah operasi "best effort"
                        }
                    }
                    $_SESSION['message'] = "Paket #{$package_id_to_delete} berhasil dihapus permanen.";
                } else {
                    throw new Exception("Token bot tidak ditemukan, tidak dapat menghapus pesan dari Telegram.");
                }
            } else {
                 // Fallback: Jika info bot tidak ditemukan, coba hapus dari DB saja.
                 $packageRepo->hardDeletePackage($package_id_to_delete);
                 $_SESSION['message'] = "Paket #{$package_id_to_delete} berhasil dihapus dari database (pesan di Telegram tidak dapat dihapus).";
            }
        } catch (Exception $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
        }
    }
    // Redirect setelah proses untuk mencegah pengiriman ulang formulir (pola PRG)
    header("Location: packages.php");
    exit;
}


if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Ambil semua paket
$packages = $packageRepo->findAll();

$page_title = 'Manajemen Konten';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Manajemen Konten</h1>
        <p>Halaman ini menampilkan semua paket konten dalam sistem. Admin dapat menghapus konten secara permanen jika diperlukan.</p>

        <?php if ($message): ?>
            <div class="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID Publik</th>
                    <th>Deskripsi</th>
                    <th>Penjual</th>
                    <th>Harga</th>
                    <th>Status</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($packages)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">Tidak ada konten.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($packages as $package): ?>
                        <tr>
                            <td><?= htmlspecialchars($package['public_id'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($package['description'] ?: 'N/A') ?></td>
                            <td>@<?= htmlspecialchars($package['seller_username'] ?? 'N/A') ?></td>
                            <td>Rp <?= number_format($package['price'] ?? 0, 0, ',', '.') ?></td>
                            <td>
                                <span class="status status-<?= strtolower($package['status']) ?>">
                                    <?= htmlspecialchars(ucfirst($package['status'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($package['created_at']) ?></td>
                            <td>
                                <form action="packages.php" method="post" onsubmit="return confirm('PERINGATAN: Aksi ini akan menghapus data dari database dan channel secara permanen. Anda yakin?');">
                                    <input type="hidden" name="action" value="hard_delete_package">
                                    <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                    <button type="submit" class="btn-delete" <?= $package['status'] === 'sold' ? 'disabled' : '' ?>>
                                        Hard Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
