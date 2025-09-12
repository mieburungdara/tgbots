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
use PDO;
use TGBot\Controllers\AppController;

/**
 * Class LoginController
 * @package TGBot\Controllers\Member
 */
class LoginController extends AppController
{
    /**
     * Shows the login form, pre-filling the token and showing an error if available.
     *
     * @return void
     */
    public function showLoginForm(): void
    {
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
            $this->logger->error('Error in Member/LoginController/showLoginForm: ' . $e->getMessage());
            $this->view('member/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the login page.'
            ], 'member_layout');
        }
    }

    /**
     * Processes a login attempt from a submitted form.
     *
     * @return void
     */
    public function processFormLogin(): void
    {
        try {
            $token = $_POST['token'] ?? '';
            $this->handleToken($token);
        } catch (Exception $e) {
            $this->logger->error('Error in Member/LoginController/processFormLogin: ' . $e->getMessage());
            $_SESSION['flash_login_error'] = 'An internal error occurred.';
            header("Location: /member/login");
            exit();
        }
    }

    /**
     * Processes a login attempt from a URL token.
     *
     * @return void
     */
    public function processLinkLogin(): void
    {
        try {
            $token = $_GET['token'] ?? '';
            $this->handleToken($token);
        } catch (Exception $e) {
            $this->logger->error('Error in Member/LoginController/processLinkLogin: ' . $e->getMessage());
            $_SESSION['flash_login_error'] = 'An internal error occurred.';
            header("Location: /member/login");
            exit();
        }
    }

    /**
     * Central token validation logic.
     *
     * @param string $token The token to validate.
     * @return void
     */
    private function handleToken(string $token): void
    {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            if (empty($token)) {
                $_SESSION['flash_login_error'] = 'Token tidak boleh kosong.';
                header('Location: /member/login');
                exit();
            }

            $pdo = \get_db_connection();

            // Check if token is valid and not used
            $stmt = $pdo->prepare(
                "SELECT id, first_name
                 FROM users
                 WHERE login_token = ?
                   AND token_used = 0"
            );
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Success! Token is valid. Create the session.
                $user_id = $user['id'];

                // Mark token as used
                $update_stmt = $pdo->prepare("UPDATE users SET token_used = 1, login_token = NULL WHERE id = ?");
                $update_stmt->execute([$user_id]);

                // Regenerate session ID for security
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['member_user_id'] = $user_id;
                $_SESSION['user_first_name'] = $user['first_name'];

                // Redirect to the member dashboard
                header("Location: /member/dashboard");
                exit;
            } else {
                // Failure: Token is invalid, expired, already used, or not for a member.
                $_SESSION['flash_login_error'] = "Token tidak valid, sudah digunakan, atau kedaluwarsa.";
                header("Location: /member/login?token=" . urlencode($token));
                exit();
            }
        } catch (Exception $e) {
            $this->logger->error('Error in Member/LoginController/handleToken: ' . $e->getMessage());
            $_SESSION['flash_login_error'] = 'Terjadi kesalahan internal saat mencoba login.';
            header("Location: /member/login");
            exit();
        }
    }
}
