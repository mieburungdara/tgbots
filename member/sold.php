<?php
/**
 * Halaman Konten Dijual (Panel Anggota).
 *
 * Halaman ini menampilkan daftar semua paket konten yang dijual oleh pengguna
 * yang sedang login.
 *
 * Fitur:
 * - Menampilkan semua paket yang dimiliki pengguna dalam format kartu.
 * - Tombol untuk menghapus (soft delete) paket yang belum terjual.
 * - Toggle switch (AJAX) untuk mengaktifkan/menonaktifkan proteksi konten (`protect_content`).
 * - Menampilkan status setiap paket (misal: 'available', 'sold').
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
$message = null;

// Menangani permintaan POST untuk menghapus (soft delete) sebuah paket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_package') {
    $package_id_to_delete = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
    if ($package_id_to_delete) {
        try {
            // Memanggil metode repository yang juga memverifikasi kepemilikan
            if ($packageRepo->softDeletePackage($package_id_to_delete, $user_id)) {
                $_SESSION['message'] = "Konten berhasil dihapus.";
            } else {
                $_SESSION['message'] = "Gagal menghapus konten.";
            }
        } catch (Exception $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
        }
    }
    // Redirect untuk menghindari pengiriman ulang formulir (pola PRG)
    header("Location: sold.php");
    exit;
}

// Periksa pesan status dari session setelah redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Ambil semua paket yang dijual oleh pengguna ini (yang belum di-soft-delete)
$sold_packages = $packageRepo->findAllBySellerId($user_id);

$page_title = 'Konten Dijual';
require_once __DIR__ . '/../partials/header.php';
?>

<style>
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
    .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; }
    .card-thumbnail { width: 100%; height: 180px; background-color: #eee; text-align: center; line-height: 180px; font-size: 2rem; color: #ccc; }
    .card-body { padding: 1rem; flex-grow: 1; }
    .card-footer { padding: 0 1rem 1rem 1rem; border-top: 1px solid #eee; margin-top: auto;}
    .card-title { font-size: 1.1rem; font-weight: bold; margin: 0 0 0.5rem 0; }
    .card-text { font-size: 0.9rem; color: #606770; margin-bottom: 0.5rem; }
    .card-price { font-size: 1rem; font-weight: bold; color: #28a745; }
    .status { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: bold; display: inline-block; }
    .status-available { background-color: #d4edda; color: #155724; }
    .status-pending { background-color: #fff3cd; color: #856404; }
    .status-sold { background-color: #e2e3e5; color: #383d41; }
    .no-content { background: white; padding: 2rem; text-align: center; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .btn-delete { width: 100%; padding: 0.5rem 1rem; background-color: #dc3545; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9rem; border: none; cursor: pointer; margin-top: 0.5rem; }
    .btn-delete:hover { background-color: #c82333; }
    .protection-toggle { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; }
    .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 28px; }
    .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: #28a745; }
    input:checked + .slider:before { transform: translateX(22px); }
</style>

<h1>Konten yang Anda Jual</h1>

<?php if ($message): ?>
            <div class="alert" id="status-message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="alert" id="ajax-message" style="display: none;"></div>


        <?php if (empty($sold_packages)): ?>
            <div class="no-content">
                <p>Anda tidak memiliki konten untuk dijual saat ini.</p>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($sold_packages as $package): ?>
                    <div class="card">
                        <div class="card-thumbnail">
                             <span>🖼️</span>
                        </div>
                        <div class="card-body">
                            <p class="card-text">ID: <?= htmlspecialchars($package['public_id']) ?></p>
                            <h2 class="card-title"><?= htmlspecialchars($package['description'] ?: 'Tanpa deskripsi') ?></h2>
                            <p class="card-price">Rp <?= number_format($package['price'], 0, ',', '.') ?></p>
                            <p class="card-text">
                                <?php
                                    $status_class = 'status-pending';
                                    if ($package['status'] === 'available') $status_class = 'status-available';
                                    if ($package['status'] === 'sold') $status_class = 'status-sold';
                                ?>
                                Status: <span class="status <?= $status_class ?>"><?= htmlspecialchars(ucfirst($package['status'])) ?></span>
                            </p>
                        </div>
                        <div class="card-footer">
                            <div class="protection-toggle">
                                <label for="protect-toggle-<?= $package['id'] ?>">Proteksi Konten</label>
                                <label class="switch">
                                    <input type="checkbox" class="protect-toggle-checkbox" id="protect-toggle-<?= $package['id'] ?>" data-package-id="<?= $package['id'] ?>" <?= !empty($package['protect_content']) ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <?php if ($package['status'] !== 'sold'): ?>
                                <form action="sold.php" method="post" onsubmit="return confirm('Anda yakin ingin menghapus konten ini?');">
                                    <input type="hidden" name="action" value="delete_package">
                                    <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                    <button type="submit" class="btn-delete">Hapus</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sembunyikan pesan status dari server setelah beberapa detik
    const statusMessage = document.getElementById('status-message');
    if (statusMessage) {
        setTimeout(() => {
            statusMessage.style.display = 'none';
        }, 3000);
    }

    // Handler AJAX untuk toggle proteksi konten
    const ajaxMessage = document.getElementById('ajax-message');
    document.querySelectorAll('.protect-toggle-checkbox').forEach(toggle => {
        toggle.addEventListener('change', async function() {
            const packageId = this.dataset.packageId;
            const formData = new FormData();
            formData.append('package_id', packageId);

            try {
                const response = await fetch('package_manager.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                // Tampilkan pesan hasil dari AJAX
                ajaxMessage.textContent = result.message;
                ajaxMessage.style.display = 'block';

                if (result.status !== 'success') {
                    // Kembalikan posisi toggle jika terjadi error
                    this.checked = !this.checked;
                }

                // Sembunyikan pesan setelah beberapa detik
                setTimeout(() => {
                    ajaxMessage.style.display = 'none';
                }, 3000);

            } catch (error) {
                ajaxMessage.textContent = 'Terjadi kesalahan jaringan.';
                ajaxMessage.style.display = 'block';
                this.checked = !this.checked; // Kembalikan posisi jika error jaringan
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
