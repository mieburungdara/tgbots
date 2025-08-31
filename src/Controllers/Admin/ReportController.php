<?php

namespace App\Controllers\Admin;


use App\Models\Repositories\ReportRepository;
use Exception;

class ReportController extends AdminBaseController
{
    private $reportRepo;

    public function __construct()
    {
        parent::__construct();
        $this->reportRepo = new ReportRepository($this->getDB());
    }

    public function financial()
    {
        try {
            $this->render('admin/reports/financial', [
                'title' => 'Laporan Keuangan',
                'summary' => $this->reportRepo->getSummary(),
                'dailyRevenue' => $this->reportRepo->getDailyRevenueLast30Days(),
                'monthlyRevenue' => $this->reportRepo->getMonthlyRevenueThisYear(),
            ]);
        } catch (Exception $e) {
            app_log('Error fetching financial report: ' . $e->getMessage(), 'error');
            $this->render('admin/reports/financial', [
                'title' => 'Laporan Keuangan',
                'error' => 'Gagal memuat laporan keuangan. Silakan periksa log untuk detailnya.'
            ]);
        }
    }
}
