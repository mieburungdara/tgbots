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
use TGBot\Database\AnalyticsRepository;

/**
 * Class DashboardController
 * @package TGBot\Controllers\Member
 */
class DashboardController extends MemberBaseController
{
    /**
     * Display the member dashboard.
     *
     * @return void
     */
    public function index(): void
    {
        try {
            // The constructor of MemberBaseController already handles the session check.
            $pdo = \get_db_connection();
            $analyticsRepo = new AnalyticsRepository($pdo);
            $user_id = $_SESSION['member_user_id'];

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_info = $stmt->fetch();

            // If user info is gone for some reason, log them out.
            if (!$user_info) {
                session_destroy();
                header("Location: /member/login");
                exit;
            }

            $periods = [
                '1' => 'Hari Ini',
                '7' => '7 Hari',
                '30' => '30 Hari',
                '365' => '1 Tahun',
            ];
            $current_period = isset($_GET['period']) && isset($periods[$_GET['period']]) ? $_GET['period'] : '30';

            $seller_summary = $analyticsRepo->getSellerSummary($user_id);
            $purchase_stats = $analyticsRepo->getUserPurchaseStats($user_id);
            $sales_by_day = $analyticsRepo->getSalesByDay($user_id, $current_period);
            $top_selling_items = $analyticsRepo->getTopSellingPackagesForSeller($user_id, 5);

            $chart_labels = [];
            $chart_data = [];
            $label_format = ($current_period > 90) ? 'M Y' : 'd M';
            foreach ($sales_by_day as $day) {
                $chart_labels[] = date($label_format, strtotime($day['sales_date']));
                $chart_data[] = $day['daily_revenue'];
            }

            $chart_title = 'Tren Pendapatan ' . $periods[$current_period];

            $this->view('member/dashboard/index', [
                'page_title' => 'Dashboard',
                'user_info' => $user_info,
                'periods' => $periods,
                'current_period' => $current_period,
                'seller_summary' => $seller_summary,
                'purchase_stats' => $purchase_stats,
                'chart_labels' => $chart_labels,
                'chart_data' => $chart_data,
                'chart_title' => $chart_title,
                'top_selling_items' => $top_selling_items
            ], 'member_layout');
        } catch (Exception $e) {
            $this->logger->error('Error in Member/DashboardController/index: ' . $e->getMessage());
            $this->view('member/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the dashboard.'
            ], 'member_layout');
        }
    }
}
