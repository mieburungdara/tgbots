<?php

class LoginController {

    /**
     * Shows the login form, pre-filling the token and showing an error if available.
     */
    public function showLoginForm() {
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
    }

    /**
     * Processes a login attempt from a submitted form.
     */
    public function processFormLogin() {
        $token = $_POST['token'] ?? '';
        $this->handleToken($token);
    }

    /**
     * Processes a login attempt from a URL token.
     */
    public function processLinkLogin() {
        $token = $_GET['token'] ?? '';
        $this->handleToken($token);
    }

    /**
     * Central token validation logic.
     * @param string $token The token to validate.
     */
    private function handleToken(string $token) {
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
    }

    /**
     * A simple view helper method.
     */
    protected function view($view, $data = []) {
        extract($data);
        require __DIR__ . "/../../../src/Views/{$view}.php";
    }
}
