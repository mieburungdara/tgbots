<?php

namespace TGBot\Database;

use PDO;

class PackageViewRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Mencatat sebuah tampilan unik dari seorang pengguna untuk sebuah paket.
     * Menggunakan INSERT IGNORE untuk secara efisien menghindari duplikasi
     * berdasarkan unique key (package_id, viewer_user_id).
     *
     * @param int $package_id
     * @param int $viewer_user_id
     * @return void
     */
    public function logView(int $package_id, int $viewer_user_id): void
    {
        $sql = "INSERT IGNORE INTO package_views (package_id, viewer_user_id) VALUES (?, ?)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$package_id, $viewer_user_id]);
        } catch (\Exception $e) {
            // Log error but don't block the main execution path
            app_log("Failed to log package view: " . $e->getMessage(), 'error', [
                'package_id' => $package_id,
                'viewer_user_id' => $viewer_user_id
            ]);
        }
    }
    
    /**
     * Menghitung jumlah penonton unik untuk sebuah paket.
     *
     * @param int $package_id
     * @return int
     */
    public function countViews(int $package_id): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT viewer_user_id) FROM package_views WHERE package_id = ?");
        $stmt->execute([$package_id]);
        return (int)$stmt->fetchColumn();
    }
}
