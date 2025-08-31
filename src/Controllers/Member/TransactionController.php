<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../../../core/database/SaleRepository.php';
require_once __DIR__ . '/../../../core/database/PackageRepository.php';

class TransactionController extends BaseController {

    public function purchased() {
        $pdo = get_db_connection();
        $saleRepo = new SaleRepository($pdo);
        $user_id = $_SESSION['member_user_id'];

        $purchased_packages = $saleRepo->findPackagesByBuyerId($user_id);

        $this->view('member/transactions/purchased', [
            'page_title' => 'Konten Dibeli',
            'purchased_packages' => $purchased_packages
        ], 'member_layout');
    }

    public function sold() {
        $pdo = get_db_connection();
        $packageRepo = new PackageRepository($pdo);
        $user_id = $_SESSION['member_user_id'];

        $message = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);

        $sold_packages = $packageRepo->findAllBySellerId($user_id);

        $this->view('member/transactions/sold', [
            'page_title' => 'Konten Dijual',
            'sold_packages' => $sold_packages,
            'message' => $message
        ], 'member_layout');
    }

    public function softDeletePackage() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /member/my_content'); // Or sold page
            exit();
        }

        $pdo = get_db_connection();
        $packageRepo = new PackageRepository($pdo);
        $user_id = $_SESSION['member_user_id'];
        $package_id_to_delete = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);

        if ($package_id_to_delete) {
            try {
                if ($packageRepo->softDeletePackage($package_id_to_delete, $user_id)) {
                    $_SESSION['flash_message'] = "Konten berhasil dihapus.";
                } else {
                    $_SESSION['flash_message'] = "Gagal menghapus konten.";
                }
            } catch (Exception $e) {
                $_SESSION['flash_message'] = "Error: " . $e->getMessage();
            }
        }

        header("Location: /member/sold");
        exit;
    }
}
