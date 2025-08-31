<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot\Controllers\Member;

use Exception;
use TGBot\Controllers\Member\MemberBaseController;
use TGBot\Database\SaleRepository;
use TGBot\Database\PackageRepository;

/**
 * Class TransactionController
 * @package TGBot\Controllers\Member
 */
class TransactionController extends MemberBaseController
{
    /**
     * Display the purchased content page.
     *
     * @return void
     */
    public function purchased(): void
    {
        try {
            $pdo = \get_db_connection();
            $saleRepo = new SaleRepository($pdo);
            $user_id = $_SESSION['member_user_id'];

            $purchased_packages = $saleRepo->findPackagesByBuyerId($user_id);

            $this->view('member/transactions/purchased', [
                'page_title' => 'Konten Dibeli',
                'purchased_packages' => $purchased_packages
            ], 'member_layout');
        } catch (Exception $e) {
            \app_log('Error in TransactionController/purchased: ' . $e->getMessage(), 'error');
            $this->view('member/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading your purchased content.'
            ], 'member_layout');
        }
    }

    /**
     * Display the sold content page.
     *
     * @return void
     */
    public function sold(): void
    {
        try {
            $pdo = \get_db_connection();
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
        } catch (Exception $e) {
            \app_log('Error in TransactionController/sold: ' . $e->getMessage(), 'error');
            $this->view('member/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading your sold content.'
            ], 'member_layout');
        }
    }

    /**
     * Soft delete a package.
     *
     * @return void
     */
    public function softDeletePackage(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /member/my_content'); // Or sold page
            exit();
        }

        $pdo = \get_db_connection();
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
