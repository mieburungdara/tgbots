<?php

require_once __DIR__ . '/../BaseController.php';

class UserController extends BaseController {

    public function index() {
        $pdo = get_db_connection();

        // --- Logika Pencarian ---
        $search_term = $_GET['search'] ?? '';
        $where_clause = '';
        $params = [];
        if (!empty($search_term)) {
            $where_clause = "WHERE u.id = :search_id OR u.first_name LIKE :like_fn OR u.last_name LIKE :like_ln OR u.username LIKE :like_un";
            $params = [
                ':search_id' => $search_term,
                ':like_fn' => "%$search_term%",
                ':like_ln' => "%$search_term%",
                ':like_un' => "%$search_term%"
            ];
        }

        // --- Logika Pengurutan ---
        $sort_columns = ['id', 'first_name', 'username', 'status', 'roles'];
        $sort_by = in_array($_GET['sort'] ?? '', $sort_columns) ? $_GET['sort'] : 'id';
        $order = strtolower($_GET['order'] ?? '') === 'asc' ? 'ASC' : 'DESC';
        $order_by_column = $sort_by === 'roles' ? 'roles' : "u.{$sort_by}";
        $order_by_clause = "ORDER BY {$order_by_column} {$order}";

        // --- Logika Pagination ---
        $page = (int)($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Query untuk menghitung total pengguna
        $count_sql = "SELECT COUNT(*) FROM users u {$where_clause}";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_users = $count_stmt->fetchColumn();
        $total_pages = ceil($total_users / $limit);

        // --- Ambil data pengguna ---
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.username, u.status, GROUP_CONCAT(r.name SEPARATOR ', ') as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            {$where_clause}
            GROUP BY u.id
            {$order_by_clause}
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();

        // --- Ambil semua peran yang tersedia untuk modal ---
        $all_roles = $pdo->query("SELECT * FROM roles ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $this->view('admin/users/index', [
            'page_title' => 'Manajemen Pengguna',
            'users' => $users,
            'all_roles' => $all_roles,
            'total_users' => $total_users,
            'total_pages' => $total_pages,
            'page' => $page,
            'search_term' => $search_term,
            'sort_by' => $sort_by,
            'order' => $order,
            'message' => $_SESSION['flash_message'] ?? ''
        ], 'admin_layout');

        unset($_SESSION['flash_message']);
    }

    public function getRoles() {
        if (!isset($_GET['telegram_id']) || !filter_var($_GET['telegram_id'], FILTER_VALIDATE_INT)) {
            return $this->jsonResponse(['error' => 'Telegram ID tidak valid atau tidak diberikan.'], 400);
        }
        $user_id = (int)$_GET['telegram_id'];
        $pdo = get_db_connection();
        try {
            $stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $role_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
            return $this->jsonResponse(['role_ids' => $role_ids]);
        } catch (Exception $e) {
            error_log('API Error in UserController@getRoles: ' . $e->getMessage());
            return $this->jsonResponse(['error' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    public function updateRoles() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->jsonResponse(['error' => 'Metode permintaan tidak valid.'], 405);
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['telegram_id']) || !filter_var($data['telegram_id'], FILTER_VALIDATE_INT)) {
            return $this->jsonResponse(['error' => 'Telegram ID tidak valid.'], 400);
        }
        if (!isset($data['role_ids']) || !is_array($data['role_ids'])) {
            return $this->jsonResponse(['error' => 'Role IDs tidak valid.'], 400);
        }

        $user_id_to_update = (int)$data['telegram_id'];
        $role_ids = array_filter(array_map('intval', $data['role_ids']), fn($id) => $id > 0);
        $pdo = get_db_connection();

        try {
            $pdo->beginTransaction();
            $stmt_delete = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
            $stmt_delete->execute([$user_id_to_update]);
            if (!empty($role_ids)) {
                $stmt_insert = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
                foreach ($role_ids as $role_id) {
                    $stmt_insert->execute([$user_id_to_update, $role_id]);
                }
            }
            $pdo->commit();
            return $this->jsonResponse(['success' => true, 'message' => 'Peran pengguna berhasil diperbarui.']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('API Error in UserController@updateRoles: ' . $e->getMessage());
            return $this->jsonResponse(['error' => 'Terjadi kesalahan pada server.'], 500);
        }
    }
}
