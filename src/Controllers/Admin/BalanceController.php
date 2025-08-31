<?php

require_once __DIR__ . '/../BaseController.php';

class BalanceController extends BaseController {

    public function index() {
        try {
            $pdo = get_db_connection();

            $flash_message = $_SESSION['flash_message'] ?? '';
            $flash_message_type = $_SESSION['flash_message_type'] ?? 'success';
            unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);

            $search_term = $_GET['search'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $sort_by = $_GET['sort'] ?? 'id';
            $order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
            $limit = 50;

            $allowed_sort_columns = ['id', 'first_name', 'username', 'balance', 'total_income', 'total_spending'];
            if (!in_array($sort_by, $allowed_sort_columns)) {
                $sort_by = 'id';
            }

            $where_clause = '';
            $params = [];
            if (!empty($search_term)) {
                $where_clause = "WHERE u.first_name LIKE :search1 OR u.last_name LIKE :search2 OR u.username LIKE :search3";
                $params = [':search1' => "%$search_term%", ':search2' => "%$search_term%", ':search3' => "%$search_term%"];
            }

            $count_sql = "SELECT COUNT(*) FROM users u {$where_clause}";
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute($params);
            $total_users = $count_stmt->fetchColumn();
            $total_pages = ceil($total_users / $limit);
            $offset = ($page - 1) * $limit;

            $sql = "
            SELECT
                u.id as telegram_id, u.first_name, u.last_name, u.username, u.balance,
                (SELECT SUM(price) FROM sales WHERE seller_user_id = u.id) as total_income,
                (SELECT SUM(price) FROM sales WHERE buyer_user_id = u.id) as total_spending
            FROM users u
            {$where_clause}
            ORDER BY {$sort_by} {$order}
            LIMIT :limit OFFSET :offset
        ";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->view('admin/balance/index', [
                'page_title' => 'Manajemen Saldo',
                'users_data' => $users_data,
                'total_pages' => $total_pages,
                'page' => $page,
                'sort_by' => $sort_by,
                'order' => $order,
                'search_term' => $search_term,
                'flash_message' => $flash_message,
                'flash_message_type' => $flash_message_type
            ], 'admin_layout');
        } catch (Exception $e) {
            app_log('Error in BalanceController/index: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the balance management page.'
            ], 'admin_layout');
        }
    }

    public function adjust() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
            header('Location: /admin/balance');
            exit();
        }

        $pdo = get_db_connection();
        $user_id = (int)($_POST['user_id'] ?? 0);
        $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $description = trim($_POST['description'] ?? '');
        $action = $_POST['action'];

        if ($user_id && $amount > 0) {
            $transaction_amount = ($action === 'add_balance') ? $amount : -$amount;
            $pdo->beginTransaction();
            try {
                $stmt_update_user = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt_update_user->execute([$transaction_amount, $user_id]);
                $stmt_insert_trans = $pdo->prepare("INSERT INTO balance_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)");
                $stmt_insert_trans->execute([$user_id, $transaction_amount, 'admin_adjustment', $description]);
                $pdo->commit();
                $_SESSION['flash_message'] = "Saldo pengguna berhasil diperbarui.";
                $_SESSION['flash_message_type'] = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_message'] = "Terjadi kesalahan: " . $e->getMessage();
                $_SESSION['flash_message_type'] = 'danger';
            }
        } else {
            $_SESSION['flash_message'] = "Input tidak valid.";
            $_SESSION['flash_message_type'] = 'danger';
        }

        $redirect_url = "/admin/balance?" . http_build_query($_GET);
        header("Location: " . $redirect_url);
        exit;
    }

    // --- API Methods for balance logs ---

    public function getBalanceLog() {
        $user_id = isset($_GET['telegram_id']) ? (int)$_GET['telegram_id'] : 0;
        if (!$user_id) return $this->jsonResponse(['error' => 'Telegram ID tidak valid.'], 400);

        $pdo = get_db_connection();
        try {
            $stmt = $pdo->prepare("SELECT amount, type, description, created_at FROM balance_transactions WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->jsonResponse($logs);
        } catch (PDOException $e) {
            return $this->jsonResponse(['error' => 'Gagal mengambil data transaksi.'], 500);
        }
    }

    public function getSalesLog() {
        $user_id = isset($_GET['telegram_id']) ? (int)$_GET['telegram_id'] : 0;
        if (!$user_id) return $this->jsonResponse(['error' => 'Telegram ID tidak valid.'], 400);

        $pdo = get_db_connection();
        try {
            $stmt = $pdo->prepare(
                "SELECT s.price, s.purchased_at, mp.title as package_title, u_buyer.first_name as buyer_name
                 FROM sales s
                 JOIN media_packages mp ON s.package_id = mp.id
                 JOIN users u_buyer ON s.buyer_user_id = u_buyer.id
                 WHERE s.seller_user_id = ? ORDER BY s.purchased_at DESC"
            );
            $stmt->execute([$user_id]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->jsonResponse($logs);
        } catch (PDOException $e) {
            return $this->jsonResponse(['error' => 'Gagal mengambil data penjualan.'], 500);
        }
    }

    public function getPurchasesLog() {
        $user_id = isset($_GET['telegram_id']) ? (int)$_GET['telegram_id'] : 0;
        if (!$user_id) return $this->jsonResponse(['error' => 'Telegram ID tidak valid.'], 400);

        $pdo = get_db_connection();
        try {
            $stmt = $pdo->prepare(
                "SELECT s.price, s.purchased_at, mp.title as package_title
                 FROM sales s
                 JOIN media_packages mp ON s.package_id = mp.id
                 WHERE s.buyer_user_id = ? ORDER BY s.purchased_at DESC"
            );
            $stmt->execute([$user_id]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->jsonResponse($logs);
        } catch (PDOException $e) {
            return $this->jsonResponse(['error' => 'Gagal mengambil data pembelian.'], 500);
        }
    }
}
