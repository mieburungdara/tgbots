<?php
session_start();
header('Content-Type: application/json');

// Respon default jika terjadi error
$response = ['status' => 'error', 'message' => 'Permintaan tidak valid.'];

// 1. Cek otentikasi
if (!isset($_SESSION['member_user_id'])) {
    $response['message'] = 'Akses ditolak. Silakan login kembali.';
    echo json_encode($response);
    exit;
}

// 2. Cek metode request dan data yang diperlukan
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['package_id'])) {
    echo json_encode($response);
    exit;
}

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/database/PackageRepository.php';

$package_id = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['member_user_id'];

if (!$package_id) {
    $response['message'] = 'ID paket tidak valid.';
    echo json_encode($response);
    exit;
}

try {
    $pdo = get_db_connection();
    $packageRepo = new PackageRepository($pdo);

    // 3. Panggil metode di repository untuk mengubah status
    $new_status = $packageRepo->toggleProtection($package_id, $user_id);

    // 4. Kirim respon sukses
    $response = [
        'status' => 'success',
        'message' => 'Status proteksi berhasil diubah.',
        'is_protected' => $new_status
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
