<?php

namespace TGBot\Controllers\Admin;

use Exception;
use TGBot\Controllers\BaseController;
use TGBot\Database\RoleRepository;

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
        try {
            $roles = $this->roleRepo->getAllRoles();

            $message = $_SESSION['flash_message'] ?? null;
            unset($_SESSION['flash_message']);

            $this->view('admin/roles/index', [
                'page_title' => 'Manajemen Peran',
                'roles' => $roles,
                'message' => $message
            ], 'admin_layout');
        } catch (Exception $e) {
            app_log('Error in RoleController/index: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the role management page.'
            ], 'admin_layout');
        }
    }

    public function store()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['role_name'])) {
                header('Location: /admin/roles');
                exit();
            }

            $role_name = trim(htmlspecialchars($_POST['role_name'] ?? '', ENT_QUOTES, 'UTF-8'));

            $rowCount = $this->roleRepo->addRole($role_name);

            if ($rowCount > 0) {
                $_SESSION['flash_message'] = "Peran '{$role_name}' berhasil ditambahkan.";
            } elseif ($rowCount === 0) {
                $_SESSION['flash_message'] = "Peran '{$role_name}' sudah ada, tidak ada yang ditambahkan.";
            } else {
                $_SESSION['flash_message'] = "Gagal menambahkan peran karena kesalahan database.";
            }
        } catch (Exception $e) {
            app_log('Error in RoleController/store: ' . $e->getMessage(), 'error');
            $_SESSION['flash_message'] = "An error occurred while adding the role.";
        }

        header("Location: /admin/roles");
        exit;
    }

    public function destroy()
    {
        try {
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

            $rowCount = $this->roleRepo->deleteRole($role_id);

            if ($rowCount > 0) {
                $_SESSION['flash_message'] = "Peran berhasil dihapus.";
            } elseif ($rowCount === 0) {
                $_SESSION['flash_message'] = "Gagal menghapus: Peran tidak ditemukan.";
            } else {
                $_SESSION['flash_message'] = "Gagal menghapus peran karena kesalahan database.";
            }
        } catch (Exception $e) {
            app_log('Error in RoleController/destroy: ' . $e->getMessage(), 'error');
            $_SESSION['flash_message'] = "An error occurred while deleting the role.";
        }

        header("Location: /admin/roles");
        exit;
    }
}
