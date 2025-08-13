<?php
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

// Handle soft delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_package') {
    $package_id_to_delete = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
    if ($package_id_to_delete) {
        try {
            if ($packageRepo->softDeletePackage($package_id_to_delete, $user_id)) {
                $_SESSION['message'] = "Konten berhasil dihapus.";
            } else {
                $_SESSION['message'] = "Gagal menghapus konten.";
            }
        } catch (Exception $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
        }
    }
    // Redirect untuk menghindari re-submission
    header("Location: sold.php");
    exit;
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Ambil data paket yang dijual (yang tidak dihapus)
$sold_packages = $packageRepo->findAllBySellerId($user_id);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konten Dijual - Member Area</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background-color: #f0f2f5; margin: 0; padding: 2rem; color: #1c1e21; }
        .container { max-width: 960px; margin: auto; }
        .header { background: white; padding: 1rem 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        h1 { font-size: 1.5rem; margin: 0; }
        nav a { text-decoration: none; color: #007bff; margin-left: 1rem; font-weight: bold; }
        nav a.active { color: #1c1e21; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; }
        .card-thumbnail { width: 100%; height: 180px; background-color: #eee; text-align: center; line-height: 180px; font-size: 2rem; color: #ccc; }
        .card-body { padding: 1rem; flex-grow: 1; }
        .card-footer { padding: 0 1rem 1rem 1rem; }
        .card-title { font-size: 1.1rem; font-weight: bold; margin: 0 0 0.5rem 0; }
        .card-text { font-size: 0.9rem; color: #606770; margin-bottom: 0.5rem; }
        .card-price { font-size: 1rem; font-weight: bold; color: #28a745; }
        .status { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: bold; display: inline-block; }
        .status-available { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-sold { background-color: #e2e3e5; color: #383d41; }
        .no-content { background: white; padding: 2rem; text-align: center; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-delete { width: 100%; padding: 0.5rem 1rem; background-color: #dc3545; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9rem; border: none; cursor: pointer; }
        .btn-delete:hover { background-color: #c82333; }
        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 8px; background-color: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Konten yang Anda Jual</h1>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="sold.php" class="active">Dijual</a>
                <a href="purchased.php">Dibeli</a>
                <a href="dashboard.php?action=logout">Logout</a>
            </nav>
        </div>

        <?php if ($message): ?>
            <div class="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

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
                            <p class="card-text">ID Paket: #<?= htmlspecialchars($package['id']) ?></p>
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
    </div>
</body>
</html>
