<?php

require_once __DIR__ . '/../AppController.php';

class LoginController extends AppController {

    /**
     * Shows the login form, pre-filling the token and showing an error if available.
     */
    public function showLoginForm() {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            if (isset($_SESSION['member_user_id'])) {
                header("Location: /member/dashboard");
                exit;
            }

            $error_message = $_SESSION['flash_login_error'] ?? null;
            unset($_SESSION['flash_login_error']);

            $this->view('member/login/index', [
                'page_title' => 'Login Member',
                'error_message' => $error_message,
                'token_from_url' => $_GET['token'] ?? '' // Pre-fill from URL on redirect
            ]);
        } catch (Exception $e) {
            app_log('Error in Member/LoginController/showLoginForm: ' . $e->getMessage(), 'error');
            $this->view('member/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the login page.'
            ], 'member_layout');
        }
    }

    /**
     * Processes a login attempt from a submitted form.
     */
    public function processFormLogin() {
        try {
            $token = $_POST['token'] ?? '';
            $this->handleToken($token);
        } catch (Exception $e) {
            app_log('Error in Member/LoginController/processFormLogin: ' . $e->getMessage(), 'error');
            $_SESSION['flash_login_error'] = 'An internal error occurred.';
            header("Location: /member/login");
            exit();
        }
    }

    /**
     * Processes a login attempt from a URL token.
     */
    public function processLinkLogin() {
        try {
            $token = $_GET['token'] ?? '';
            $this->handleToken($token);
        } catch (Exception $e) {
            app_log('Error in Member/LoginController/processLinkLogin: ' . $e->getMessage(), 'error');
            $_SESSION['flash_login_error'] = 'An internal error occurred.';
            header("Location: /member/login");
            exit();
        }
    }

    /**
     * Central token validation logic.
     * @param string $token The token to validate.
     */
    private function handleToken(string $token) {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            if (empty($token)) {
                $_SESSION['flash_login_error'] = 'Token tidak boleh kosong.';
                header('Location: /member/login');
                exit();
            }

            $pdo = get_db_connection();
            $error_message = null;

            $stmt = $pdo->prepare("SELECT * FROM users WHERE login_token = ? AND token_used = 0");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && isset($user['token_created_at']) && strtotime($user['token_created_at']) < time() - (5 * 60)) {
                $error_message = "Token yang Anda gunakan sudah kedaluwarsa. Silakan minta yang baru dari bot.";
            } elseif ($user) {
                // Success!
                $user_id = $user['id'];
                $update_stmt = $pdo->prepare("UPDATE users SET token_used = 1, login_token = NULL WHERE id = ?");
                $update_stmt->execute([$user_id]);
                $_SESSION['member_user_id'] = $user_id;
                header("Location: /member/dashboard");
                exit;
            } else {
                // Failure
                $error_message = "Token tidak valid, sudah digunakan, atau kedaluwarsa.";
            }

            // If login failed, redirect back to the login form with an error.
            $_SESSION['flash_login_error'] = $error_message;
            header("Location: /member/login?token=" . urlencode($token));
            exit();
        } catch (Exception $e) {
            app_log('Error in Member/LoginController/handleToken: ' . $e->getMessage(), 'error');
            $_SESSION['flash_login_error'] = 'An internal error occurred.';
            header("Location: /member/login");
            exit();
        }
    }
}
