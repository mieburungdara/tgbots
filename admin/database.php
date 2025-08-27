<?php
/**
 * Halaman Manajemen Database (Admin).
 *
 * Halaman ini menyediakan antarmuka untuk melakukan tugas-tugas administrasi
 * database tingkat lanjut, seperti migrasi dan pembersihan data.
 */
session_start();
require_once __DIR__ . '/auth.php'; // Otentikasi
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Koneksi database gagal.");
}

$message = null;

// Menangani permintaan POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reset_with_file') {
        $allowed_files = ['updated_schema.sql', 'setup.sql'];
        $selected_file = $_POST['sql_file'] ?? '';

        if (in_array($selected_file, $allowed_files) && file_exists(__DIR__ . '/../' . $selected_file)) {
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    $pdo->exec("DROP TABLE IF EXISTS `$table`");
                }

                $sql_script = file_get_contents(__DIR__ . '/../' . $selected_file);
                $pdo->exec($sql_script);

                $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
                $_SESSION['message'] = "Database berhasil di-reset menggunakan file '{$selected_file}'.";
            } catch (Exception $e) {
                $_SESSION['message'] = "Gagal me-reset database: " . $e->getMessage();
            }
        } else {
            $_SESSION['message'] = "Error: File SQL tidak valid atau tidak ditemukan.";
        }
    } elseif ($_POST['action'] === 'clean_database') { // Keep old action for now, can be removed later
        try {
            clean_transactional_data($pdo); // Fungsi ini ada di core/database.php
            $_SESSION['message'] = "Berhasil! Semua data pengguna, konten, dan penjualan telah dihapus.";
        } catch (Exception $e) {
            $_SESSION['message'] = "Gagal membersihkan database: " . $e->getMessage();
        }
    }
    // Redirect setelah proses untuk mencegah resubmit (pola PRG)
    header("Location: database.php");
    exit;
}

// Menampilkan pesan dari session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$page_title = 'Manajemen Database';
require_once __DIR__ . '/../partials/header.php';
?>

<h1>Manajemen Database</h1>

<?php if ($message): ?>
    <div class="alert alert-info">
        <?= nl2br(htmlspecialchars($message)); ?>
    </div>
<?php endif; ?>

<h2>Migrasi Database</h2>
<p class="description">
    Gunakan tombol di bawah ini untuk menjalankan pembaruan skema database.
    Sistem akan secara otomatis menerapkan file migrasi baru dari direktori <code>/migrations</code>
    tanpa menghapus data yang ada di tabel yang tidak terpengaruh.
</p>
<form id="migration-form">
    <button type="submit" id="migration-button" class="btn">Jalankan Migrasi Database</button>
</form>

<div id="migration-results-container" style="margin-top: 20px; display: none;">
    <h3>Hasil Migrasi:</h3>
    <pre id="migration-results" style="background-color: #2d2d2d; color: #f1f1f1; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word;"></pre>
</div>


<div class="danger-zone" style="margin-top: 40px;">
    <h2>Zona Berbahaya</h2>
    <p class="description">
        <strong>PERINGATAN:</strong> Aksi di bawah ini bersifat destruktif dan akan menghapus semua tabel yang ada sebelum membuat ulang skema dari file yang dipilih.
    </p>
    <form action="database.php" method="post" onsubmit="return confirm('PERINGATAN KERAS!\n\nAnda akan MENGHAPUS SEMUA TABEL dan membuat ulang skema database dari file yang dipilih.\n\nAksi ini tidak dapat diurungkan.\n\nLanjutkan?');">
        <input type="hidden" name="action" value="reset_with_file">

        <label for="sql_file">Pilih File Skema SQL:</label>
        <select name="sql_file" id="sql_file" style="width: 100%; padding: 8px; margin-bottom: 15px;">
            <option value="updated_schema.sql">updated_schema.sql (Disarankan)</option>
            <option value="setup.sql">setup.sql (Skema Awal/Lama)</option>
        </select>

        <button type="submit" class="btn btn-danger">Reset Database dengan File Pilihan</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const migrationForm = document.getElementById('migration-form');
    const migrationButton = document.getElementById('migration-button');
    const resultsContainer = document.getElementById('migration-results-container');
    const resultsPre = document.getElementById('migration-results');

    migrationForm.addEventListener('submit', function(event) {
        event.preventDefault();

        if (!confirm('Apakah Anda yakin ingin menjalankan migrasi database? Proses ini tidak dapat diurungkan.')) {
            return;
        }

        migrationButton.disabled = true;
        migrationButton.textContent = 'Menjalankan...';
        resultsContainer.style.display = 'block';
        resultsPre.textContent = 'Memproses permintaan...';

        fetch('run_migrations_ajax.php', {
            method: 'POST'
        })
        .then(response => {
            // Cek apakah response OK, tapi tetap proses sebagai teks apapun hasilnya
            return response.text().then(text => {
                if (!response.ok) {
                    // Lemparkan error dengan teks dari server untuk debugging
                    throw new Error('Network response was not ok (' + response.status + ').\n\n' + text);
                }
                return text;
            });
        })
        .then(text => {
            resultsPre.style.color = '#f1f1f1'; // Warna default untuk output mentah
            resultsPre.textContent = text;
        })
        .catch(error => {
            resultsPre.style.color = '#e74c3c';
            resultsPre.textContent = 'Terjadi kesalahan saat menghubungi server.\n' + error.message;
            console.error('Fetch Error:', error);
        })
        .finally(() => {
            migrationButton.disabled = false;
            migrationButton.textContent = 'Jalankan Migrasi Database';
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../partials/footer.php';
?>
