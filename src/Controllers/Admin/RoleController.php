<?php

require_once __DIR__ . '/../BaseController.php';
require_once __DIR__ . '/../../../core/database/RoleRepository.php';

class RoleController extends BaseController
{
    private $roleRepo;

    public function __construct()
    {
        parent::__construct();
        $pdo = get_db_connection();
        $this->roleRepo = new RoleRepository($pdo);
    }

    public function index()
    {
        $roles = $this->roleRepo->getAllRoles();

        if (session_status() == PHP_SESSION_NONE) session_start();
        $message = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);

        $this->view('admin/roles/index', [
            'page_title' => 'Manajemen Peran',
            'roles' => $roles,
            'message' => $message
        ], 'admin_layout');
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['role_name'])) {
            header('Location: /admin/roles');
            exit();
        }

        $role_name = trim(htmlspecialchars($_POST['role_name'] ?? '', ENT_QUOTES, 'UTF-8'));

        if (session_status() == PHP_SESSION_NONE) session_start();

        if ($this->roleRepo->addRole($role_name)) {
            $_SESSION['flash_message'] = "Peran '{$role_name}' berhasil ditambahkan.";
        } else {
            $_SESSION['flash_message'] = "Gagal menambahkan peran.";
        }

        header("Location: /admin/roles");
        exit;
    }

    public function destroy()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/roles');
            exit();
        }

        $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
        // filter_input returns null if var not set, false if filter fails.
        if ($role_id === false || $role_id === null) {
            header('Location: /admin/roles');
            exit();
        }

        if (session_status() == PHP_SESSION_NONE) session_start();

        if ($this->roleRepo->deleteRole($role_id)) {
            $_SESSION['flash_message'] = "Peran berhasil dihapus.";
        } else {
            $_SESSION['flash_message'] = "Gagal menghapus peran.";
        }

        header("Location: /admin/roles");
        exit;
    }
}
