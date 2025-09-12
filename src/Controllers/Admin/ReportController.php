<?php

namespace TGBot\Controllers\Admin;

use TGBot\Controllers\BaseController;
use Exception;

class ReportController extends BaseController
{
    public function financial()
    {
        try {
            // $pdo = \get_db_connection();
            // $reportRepo = new \TGBot\Database\ReportRepository($pdo);

            $this->view('admin/reports/financial', [
                'page_title' => 'Laporan Keuangan',
                // 'summary' => $reportRepo->getSummary(),
                // 'dailyRevenue' => $reportRepo->getDailyRevenueLast30Days(),
                // 'monthlyRevenue' => $reportRepo->getMonthlyRevenueThisYear(),
                'summary' => [],
                'dailyRevenue' => [],
                'monthlyRevenue' => [],
                'error' => 'ReportRepository belum diimplementasikan.'
            ], 'admin_layout');
        } catch (Exception $e) {
            \app_log('Error fetching financial report: ' . $e->getMessage(), 'error');
            $this->view('admin/reports/financial', [
                'page_title' => 'Laporan Keuangan',
                'error' => 'Gagal memuat laporan keuangan. Silakan periksa log untuk detailnya.'
            ], 'admin_layout');
        }
    }
}