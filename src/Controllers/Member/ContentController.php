<?php

require_once __DIR__ . '/../MemberBaseController.php';
require_once __DIR__ . '/../../../core/database/PackageRepository.php';

class ContentController extends MemberBaseController {

    public function index() {
        $pdo = get_db_connection();
        $packageRepo = new PackageRepository($pdo);
        $user_id = $_SESSION['member_user_id'];

        $my_packages = $packageRepo->findAllBySellerId($user_id);

        $this->view('member/content/index', [
            'page_title' => 'Konten Saya',
            'my_packages' => $my_packages
        ], 'member_layout');
    }

    public function edit() {
        $public_id = $_GET['id'] ?? null;
        if (!$public_id) {
            header("Location: /member/my_content");
            exit;
        }

        $pdo = get_db_connection();
        $packageRepo = new PackageRepository($pdo);
        $user_id = $_SESSION['member_user_id'];

        try {
            $package = $packageRepo->findByPublicId($public_id);
            if (!$package || $package['seller_user_id'] != $user_id) {
                $_SESSION['flash_message'] = "Error: Paket tidak ditemukan atau Anda tidak memiliki izin.";
                header("Location: /member/my_content");
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Error: " . $e->getMessage();
            header("Location: /member/my_content");
            exit;
        }

        $this->view('member/content/edit', [
            'page_title' => 'Edit Konten: ' . htmlspecialchars($package['public_id']),
            'package' => $package,
            'error_message' => $_SESSION['flash_error'] ?? null
        ], 'member_layout');
        unset($_SESSION['flash_error']);
    }

    public function update() {
        $public_id = $_POST['public_id'] ?? null;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$public_id) {
            header('Location: /member/my_content');
            exit();
        }

        $pdo = get_db_connection();
        $packageRepo = new PackageRepository($pdo);
        $user_id = $_SESSION['member_user_id'];

        // Verify ownership again before updating
        $package = $packageRepo->findByPublicId($public_id);
        if (!$package || $package['seller_user_id'] != $user_id) {
            $_SESSION['flash_message'] = "Error: Anda tidak memiliki izin untuk mengedit paket ini.";
            header("Location: /member/my_content");
            exit;
        }

        $description = trim($_POST['description'] ?? '');
        $price = !empty($_POST['price']) ? filter_input(INPUT_POST, 'price', FILTER_VALIDATE_INT) : null;

        if (empty($description)) {
            $_SESSION['flash_error'] = "Deskripsi tidak boleh kosong.";
            header("Location: /member/content/edit?id=" . $public_id);
            exit();
        }

        try {
            $result = $packageRepo->updatePackageDetails($package['id'], $user_id, $description, $price);
            if ($result) {
                $_SESSION['flash_message'] = "Paket '" . htmlspecialchars($public_id) . "' berhasil diperbarui.";
                header("Location: /member/my_content");
                exit;
            } else {
                $_SESSION['flash_error'] = "Gagal memperbarui paket. Silakan coba lagi.";
                header("Location: /member/content/edit?id=" . $public_id);
                exit();
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Terjadi error: " . $e->getMessage();
            header("Location: /member/content/edit?id=" . $public_id);
            exit();
        }
    }
}
