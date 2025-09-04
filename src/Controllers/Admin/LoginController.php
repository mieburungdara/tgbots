<?php

namespace TGBot\Controllers\Admin;

use TGBot\Controllers\AppController;

class LoginController extends AppController
{
    private string $correct_password;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->correct_password = defined('XOR_ADMIN_PASSWORD') ? XOR_ADMIN_PASSWORD : 'sup3r4dmin';
    }

    public function showLoginForm()
    {
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            header("Location: /xoradmin/dashboard");
            exit;
        }
        $this->view('admin/login', [
            'page_title' => 'Admin Login',
            'error' => $_SESSION['login_error'] ?? null
        ]);
        unset($_SESSION['login_error']);
    }

    public function processLogin()
    {
        if (isset($_POST['password']) && hash_equals($this->correct_password, $_POST['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_first_name'] = 'Admin'; // Generic name for password login
            header("Location: /xoradmin/dashboard");
            exit;
        } else {
            $_SESSION['login_error'] = "Password salah!";
            header("Location: /xoradmin/login");
            exit;
        }
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        header("Location: /xoradmin/login");
        exit;
    }
}
