<?php

require_once __DIR__ . '/../AppController.php';

class LoginController extends AppController {

    /**
     * Handles the one-time login token from the URL.
     * If the user is an admin, it shows a choice page.
     * Otherwise, it should handle normal user login (TBD or handled by Member/LoginController).
     */
    public function handleToken() {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            if (!isset($_GET['token'])) {
                header('Location: /');
                exit();
            }

            $token = $_GET['token'];
            $pdo = get_db_connection();

            if ($pdo) {
                // Check if token is valid and belongs to an admin
                $stmt = $pdo->prepare(
                    "SELECT u.id, u.first_name\n                 FROM users u\n                 JOIN user_roles ur ON u.id = ur.user_id\n                 JOIN roles r ON ur.role_id = r.id\n                 WHERE u.login_token = ? AND u.token_used = 0 AND u.token_created_at >= NOW() - INTERVAL 5 MINUTE AND r.name = 'Admin'"
                );
                $stmt->execute([$token]);
                $user = $stmt->fetch();

                if ($user) {
                    // Admin token is valid. Create the session.
                    $pdo->prepare("UPDATE users SET token_used = 1, login_token = NULL WHERE login_token = ?")->execute([$token]);

                    session_regenerate_id(true);
                    $_SESSION['is_admin'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_first_name'] = $user['first_name'];

                    // Show the choice page instead of redirecting
                    return $this->view('auth/login_choice', ['page_title' => 'Pilih Panel']);
                }
            }

            // If token is invalid, expired, or not for an admin, deny access.
            session_unset();
            session_destroy();
            http_response_code(403);
            $this->view('auth/access_denied', ['bot_username' => null, 'error_message' => 'Tautan login tidak valid, kedaluwarsa, atau bukan untuk admin.']);
        } catch (Exception $e) {
            app_log('Error in LoginController/handleToken: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred during login.'
            ], 'admin_layout');
        }
    }

    /**
     * Destroys the session and logs the user out.
     */
    public function logout() {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            session_unset();
            session_destroy();
        } catch (Exception $e) {
            app_log('Error in LoginController/logout: ' . $e->getMessage(), 'error');
        }

        header("Location: /");
        exit();
    }
}
