<?php

require_once __DIR__ . '/../BaseController.php';
require_once __DIR__ . '/../../../core/database/AnalyticsRepository.php';

class AnalyticsController extends BaseController {

    public function index() {
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
