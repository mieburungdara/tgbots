<?php

namespace TGBot\Controllers\Admin;

use Exception;
use TGBot\Controllers\BaseController;
use TGBot\Database\FeatureChannelRepository;
use TGBot\Database\BotRepository;

class FeatureChannelController extends BaseController
{
    private FeatureChannelRepository $featureChannelRepo;
    private BotRepository $botRepo;

    public function __construct()
    {
        parent::__construct();
        $pdo = \get_db_connection();
        $this->featureChannelRepo = new FeatureChannelRepository($pdo);
        $this->botRepo = new BotRepository($pdo);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $configs = $this->featureChannelRepo->findAll();
            $this->view('admin/feature_channels/index', [
                'page_title' => 'Manajemen Channel Fitur',
                'configs' => $configs,
                'flash_message' => $_SESSION['flash_message'] ?? null,
            ], 'admin_layout');
            unset($_SESSION['flash_message']);
        } catch (Exception $e) {
            $this->view('admin/error', ['error_message' => $e->getMessage()], 'admin_layout');
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $bots = $this->botRepo->getAllBots();
        $this->view('admin/feature_channels/form', [
            'page_title' => 'Tambah Konfigurasi Channel',
            'bots' => $bots,
            'config' => [],
            'action' => '/admin/feature-channels/store'
        ], 'admin_layout');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        try {
            $this->featureChannelRepo->create($_POST);
            $_SESSION['flash_message'] = 'Konfigurasi channel berhasil dibuat.';
        } catch (Exception $e) {
            $_SESSION['flash_message'] = 'Gagal membuat konfigurasi: ' . $e->getMessage();
        }
        header('Location: /admin/feature-channels');
        exit();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit()
    {
        try {
            $id = (int)($_GET['id'] ?? 0);
            $config = $this->featureChannelRepo->find($id);
            if (!$config) {
                throw new Exception("Konfigurasi dengan ID {$id} tidak ditemukan.");
            }
            $bots = $this->botRepo->getAllBots();
            $this->view('admin/feature_channels/form', [
                'page_title' => 'Edit Konfigurasi Channel',
                'bots' => $bots,
                'config' => $config,
                'action' => '/admin/feature-channels/update?id=' . $id
            ], 'admin_layout');
        } catch (Exception $e) {
            $_SESSION['flash_message'] = $e->getMessage();
            header('Location: /admin/feature-channels');
            exit();
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {
        try {
            $id = (int)($_GET['id'] ?? 0);
            $this->featureChannelRepo->update($id, $_POST);
            $_SESSION['flash_message'] = 'Konfigurasi channel berhasil diperbarui.';
        } catch (Exception $e) {
            $_SESSION['flash_message'] = 'Gagal memperbarui konfigurasi: ' . $e->getMessage();
        }
        header('Location: /admin/feature-channels');
        exit();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $this->featureChannelRepo->delete($id);
            $_SESSION['flash_message'] = 'Konfigurasi channel berhasil dihapus.';
        } catch (Exception $e) {
            $_SESSION['flash_message'] = 'Gagal menghapus konfigurasi: ' . $e->getMessage();
        }
        header('Location: /admin/feature-channels');
        exit();
    }
}
