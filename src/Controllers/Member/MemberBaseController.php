<?php

require_once __DIR__ . '/../AppController.php';

abstract class MemberBaseController extends AppController {

    public function __construct() {
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
    }
}
