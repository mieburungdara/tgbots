<?php
/**
 * Halaman untuk Mengedit Paket Konten.
 *
 * Halaman ini menangani dua hal:
 * 1. Menampilkan form untuk mengedit detail paket (deskripsi, harga) saat diakses dengan metode GET.
 * 2. Memproses data dari form tersebut saat dikirim dengan metode POST.
 *
 * Pencarian paket dilakukan menggunakan public_id.
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
$error_message = null;
$success_message = null;

// Ambil ID publik paket dari URL
$public_id = $_GET['id'] ?? null;
if (!$public_id) {
    header("Location: my_content.php"); // Redirect jika ID tidak valid
    exit;
}

// Ambil data paket menggunakan public_id untuk memastikan pemiliknya adalah pengguna yang sedang login
try {
    $package = $packageRepo->findByPublicId($public_id);
    if (!$package || $package['seller_user_id'] != $user_id) {
        // Jika paket tidak ada atau bukan milik user, redirect
        $_SESSION['flash_message'] = "Error: Paket tidak ditemukan atau Anda tidak memiliki izin.";
        header("Location: my_content.php");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    header("Location: my_content.php");
    exit;
}

// Simpan ID internal untuk proses update
$package_id = $package['id'];

// Handle POST request (form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    // Harga bisa jadi null jika tidak diisi, repository menangani ini
    $price = !empty($_POST['price']) ? filter_input(INPUT_POST, 'price', FILTER_VALIDATE_INT) : null;

    if (empty($description)) {
        $error_message = "Deskripsi tidak boleh kosong.";
    } else {
        try {
            // Gunakan ID internal untuk update
            $result = $packageRepo->updatePackageDetails($package_id, $user_id, $description, $price);
            if ($result) {
                $_SESSION['flash_message'] = "Paket '" . htmlspecialchars($package['public_id']) . "' berhasil diperbarui.";
                header("Location: my_content.php");
                exit;
            } else {
                $error_message = "Gagal memperbarui paket. Silakan coba lagi.";
            }
        } catch (Exception $e) {
            $error_message = "Terjadi error: " . $e->getMessage();
        }
    }
}

$page_title = 'Edit Konten: ' . htmlspecialchars($package['public_id']);
require_once __DIR__ . '/../partials/header.php';
?>

<h2>Edit Konten: <?= htmlspecialchars($package['public_id']) ?></h2>
<p>Gunakan form di bawah ini untuk mengubah detail konten Anda.</p>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?= $error_message ?></div>
<?php endif; ?>

<form action="edit_package.php?id=<?= htmlspecialchars($public_id) ?>" method="POST">
    <div style="margin-bottom: 15px;">
        <label for="description"><strong>Deskripsi</strong></label>
        <textarea id="description" name="description" rows="4" required><?= htmlspecialchars($package['description']) ?></textarea>
    </div>
    <div style="margin-bottom: 15px;">
        <label for="price"><strong>Harga (Rp)</strong></label>
        <input type="number" id="price" name="price" value="<?= htmlspecialchars($package['price']) ?>" placeholder="Kosongkan jika tidak ingin dijual">
    </div>

    <div>
        <button type="submit" class="btn">Simpan Perubahan</button>
        <a href="my_content.php" class="btn" style="background-color: #6c757d;">Batal</a>
    </div>
</form>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
