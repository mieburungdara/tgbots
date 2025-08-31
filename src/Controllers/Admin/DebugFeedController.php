<?php

namespace TGBot\Controllers\Admin;



use Exception;
use TGBot\Controllers\BaseController;
use TGBot\Database\RawUpdateRepository;

class DebugFeedController extends BaseController
{
    public function index()
    {
        try {
            $pdo = \get_db_connection();
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
        } catch (Exception $e) {
            \app_log('Error in DebugFeedController/index: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the debug feed.'
            ], 'admin_layout');
        }
    }
}
