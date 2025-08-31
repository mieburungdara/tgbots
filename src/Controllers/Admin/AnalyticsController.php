<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot\Controllers\Admin;

require_once __DIR__ . '/../BaseController.php';

use Exception;
use TGBot\Controllers\BaseController;
use TGBot\Database\AnalyticsRepository;

/**
 * Class AnalyticsController
 * @package TGBot\Controllers\Admin
 *
 * Kontroler ini bertanggung jawab untuk menangani semua logika yang terkait dengan analitik dan statistik penjualan.
 * Ini mengumpulkan data dari repositori dan menampilkannya di dasbor analitik.
 */
class AnalyticsController extends BaseController
{
    /**
     * Menampilkan dasbor analitik.
     *
     * Metode ini mengambil data ringkasan penjualan global, penjualan harian, dan paket terlaris dari repositori.
     * Kemudian, data tersebut diproses untuk ditampilkan dalam bentuk grafik dan tabel di halaman analitik.
     *
     * @return void
     */
    public function index(): void
    {
        try {
            $pdo = get_db_connection();
            $analyticsRepo = new AnalyticsRepository($pdo);

            $summary = $analyticsRepo->getGlobalSummary();
            $sales_by_day = $analyticsRepo->getSalesByDay(null, 30);
            $top_packages = $analyticsRepo->getTopSellingPackages(5);

            $chart_labels = [];
            $chart_data = [];
            foreach ($sales_by_day as $day) {
                $chart_labels[] = date("d M", strtotime($day['sales_date']));
                $chart_data[] = $day['daily_revenue'];
            }

            $this->view('admin/analytics/index', [
                'page_title' => 'Analitik Penjualan',
                'summary' => $summary,
                'chart_labels' => $chart_labels,
                'chart_data' => $chart_data,
                'top_packages' => $top_packages
            ], 'admin_layout');
        } catch (Exception $e) {
            app_log('Error in AnalyticsController: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the analytics page.'
            ], 'admin_layout');
        }
    }
}
