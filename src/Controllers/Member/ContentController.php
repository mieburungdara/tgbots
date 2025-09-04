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
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\AnalyticsRepository;

/**
 * Class ContentController
 * @package TGBot\Controllers\Member
 *
 * @purpose Mengelola konten yang dimiliki oleh anggota (penjual), seperti paket file 
 * yang mereka jual.
 */
class ContentController extends MemberBaseController
{
    /**
     * Menampilkan halaman manajemen konten.
     *
     * @purpose Menampilkan halaman "Konten Saya" yang berisi daftar semua paket konten 
     * yang dimiliki oleh anggota yang sedang login.
     *
     * @return void
     */
    public function index(): void
    {
        try {
            $pdo = \get_db_connection();
            $packageRepo = new MediaPackageRepository($pdo);
            $user_id = $_SESSION['member_user_id'];

            $my_packages = $packageRepo->findAllBySellerId($user_id);

            $this->view('member/content/index', [
                'page_title' => 'Konten Saya',
                'my_packages' => $my_packages
            ], 'member_layout');
        } catch (Exception $e) {
            \app_log('Error in ContentController/index: ' . $e->getMessage(), 'error');
            $this->view('member/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading your content.'
            ], 'member_layout');
        }
    }

    /**
     * Menampilkan halaman edit konten.
     *
     * @purpose Menampilkan halaman untuk mengedit detail sebuah paket konten 
     * (misalnya, deskripsi dan harga).
     *
     * @return void
     */
    public function edit(): void
    {
        $public_id = $_GET['id'] ?? null;
        if (!$public_id) {
            header("Location: /member/my_content");
            exit;
        }

        $pdo = \get_db_connection();
        $packageRepo = new MediaPackageRepository($pdo);
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

    /**
     * Memperbarui detail konten.
     *
     * @purpose Memproses dan menyimpan perubahan yang dibuat pada halaman edit konten.
     *
     * @return void
     */
    public function update(): void
    {
        $public_id = $_POST['public_id'] ?? null;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$public_id) {
            header('Location: /member/my_content');
            exit();
        }

        $pdo = \get_db_connection();
        $packageRepo = new MediaPackageRepository($pdo);
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

    /**
     * Menampilkan detail konten.
     *
     * @purpose Menampilkan detail dan statistik penjualan untuk sebuah paket konten, 
     * termasuk grafik pendapatan.
     *
     * @return void
     */
    public function show(): void
    {
        $public_id = $_GET['id'] ?? null;
        if (!$public_id) {
            header("Location: /member/my_content");
            exit;
        }

        $pdo = \get_db_connection();
        $packageRepo = new MediaPackageRepository($pdo);
        $analyticsRepo = new AnalyticsRepository($pdo);
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

        $package_id = $package['id'];
        $periods = ['7' => '7 Hari', '30' => '30 Hari', '90' => '90 Hari', '365' => '1 Tahun'];
        $current_period = isset($_GET['period']) && isset($periods[$_GET['period']]) ? $_GET['period'] : '30';

        $package_summary = $analyticsRepo->getSummaryForPackage($package_id);
        $sales_by_day = $analyticsRepo->getSalesByDay(null, $current_period, $package_id);

        $chart_labels = [];
        $chart_data = [];
        $label_format = ($current_period > 90) ? 'M Y' : 'd M';
        foreach ($sales_by_day as $day) {
            $chart_labels[] = date($label_format, strtotime($day['sales_date']));
            $chart_data[] = $day['daily_revenue'];
        }
        $chart_title = 'Tren Pendapatan ' . $periods[$current_period];

        $this->view('member/content/show', [
            'page_title' => 'Detail Konten: ' . htmlspecialchars($package['public_id']),
            'package' => $package,
            'package_summary' => $package_summary,
            'periods' => $periods,
            'current_period' => $current_period,
            'chart_labels' => $chart_labels,
            'chart_data' => $chart_data,
            'chart_title' => $chart_title
        ], 'member_layout');
    }

    /**
     * Mengubah status proteksi konten.
     *
     * @purpose Fungsi API untuk mengaktifkan atau menonaktifkan proteksi konten 
     * (misalnya, anti-salin) untuk sebuah paket.
     *
     * @return void
     */
    public function toggleProtection(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['package_id'])) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Permintaan tidak valid.'], 400);
            return;
        }

        $package_id = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
        $user_id = $_SESSION['member_user_id'];

        if (!$package_id) {
            $this->jsonResponse(['status' => 'error', 'message' => 'ID paket tidak valid.'], 400);
            return;
        }

        try {
            $pdo = \get_db_connection();
            $packageRepo = new MediaPackageRepository($pdo);
            $new_status = $packageRepo->toggleProtection($package_id, $user_id);
            $this->jsonResponse([
                'status' => 'success',
                'message' => 'Status proteksi berhasil diubah.',
                'is_protected' => $new_status
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Menampilkan halaman manajemen channel.
     *
     * @purpose Menampilkan channel jualan yang terdaftar oleh pengguna.
     *
     * @return void
     */
    public function channels(): void
    {
        try {
            $pdo = \get_db_connection();
            $channelRepo = new \TGBot\Database\FeatureChannelRepository($pdo);
            $user_id = $_SESSION['member_user_id'];

            $sell_channel = $channelRepo->findByOwnerAndFeature($user_id, 'sell');

            $this->view('member/content/channels', [
                'page_title' => 'Channel Saya',
                'channel' => $sell_channel
            ], 'member_layout');
        } catch (Exception $e) {
            \app_log('Error in ContentController/channels: ' . $e->getMessage(), 'error');
            $this->view('member/error', [
                'page_title' => 'Error',
                'error_message' => 'Terjadi kesalahan saat memuat halaman channel.'
            ], 'member_layout');
        }
    }
}