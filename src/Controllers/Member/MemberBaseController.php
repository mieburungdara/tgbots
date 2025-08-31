<?php

require_once __DIR__ . '/../AppController.php';

abstract class MemberBaseController extends AppController {

    public function __construct() {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            // Protect all controllers that extend this one by checking for member session
            if (!isset($_SESSION['member_user_id'])) {
                // Redirect to the member login page if not authenticated.
                // This route will be created when we refactor the member login page.
                header('Location: /member/login');
                exit();
            }
        } catch (Exception $e) {
            app_log('Error in MemberBaseController/__construct: ' . $e->getMessage(), 'error');
            // For a base controller, it might be best to show a generic error page
            // as we don't know the context of the request yet.
            $this->view('member/error', [
                'page_title' => 'Error',
                'error_message' => 'An unexpected error occurred.'
            ]);
            exit();
        }
    }
}
