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
}
