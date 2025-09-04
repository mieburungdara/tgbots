<?php

namespace TGBot\Controllers;

require_once __DIR__ . '/../../core/helpers.php';

use PDO;
use Exception;



abstract class BaseController extends AppController {

    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // This check protects all admin controllers that extend BaseController.
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header("Location: /xoradmin/login");
            exit();
        }
    }
}
