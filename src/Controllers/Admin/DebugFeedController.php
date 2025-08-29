<?php

require_once __DIR__ . '/../BaseController.php';
require_once __DIR__ . '/../../../core/database/RawUpdateRepository.php';

class DebugFeedController extends BaseController
{
    public function index()
    {
        $pdo = get_db_connection();
        $raw_update_repo = new RawUpdateRepository($pdo);

        // Pagination Logic
        $items_per_page = 25;
        $total_items = $raw_update_repo->countAll();
        $total_pages = ceil($total_items / $items_per_page);
        $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $current_page = max(1, min($current_page, $total_pages));
        $offset = ($current_page - 1) * $items_per_page;

        $updates = $raw_update_repo->findAll($items_per_page, $offset);

        $this->view('admin/debug_feed/index', [
            'page_title' => 'Raw Telegram Update Feed',
            'updates' => $updates,
            'pagination' => [
                'current_page' => $current_page,
                'total_pages' => $total_pages
            ]
        ], 'admin_layout');
    }
}
