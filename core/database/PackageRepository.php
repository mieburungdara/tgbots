<?php

class PackageRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function find(int $id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM media_packages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPackageFiles(int $package_id): array
    {
        $stmt = $this->pdo->prepare("SELECT storage_channel_id, storage_message_id FROM media_files WHERE package_id = ? AND storage_message_id IS NOT NULL ORDER BY storage_message_id ASC");
        $stmt->execute([$package_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil file paket dan mengelompokkannya berdasarkan media_group_id.
     * Setiap grup atau file tunggal dianggap sebagai satu "halaman".
     *
     * @param int $package_id
     * @return array Array yang berisi halaman-halaman, di mana setiap halaman adalah array file.
     */
    public function getGroupedPackageContent(int $package_id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT storage_channel_id, storage_message_id, media_group_id
             FROM media_files
             WHERE package_id = ? AND storage_message_id IS NOT NULL
             ORDER BY storage_message_id ASC"
        );
        $stmt->execute([$package_id]);
        $all_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($all_files)) {
            return [];
        }

        $pages = [];
        $processed_storage_ids = [];

        foreach ($all_files as $file) {
            if (in_array($file['storage_message_id'], $processed_storage_ids)) {
                continue;
            }

            if ($file['media_group_id'] === null) {
                // File tunggal, menjadi satu halaman sendiri
                $pages[] = [$file];
                $processed_storage_ids[] = $file['storage_message_id'];
            } else {
                // Bagian dari media group, temukan semua file lain dalam grup ini
                $current_group_id = $file['media_group_id'];
                $current_page = [];
                foreach ($all_files as $group_file) {
                    if ($group_file['media_group_id'] === $current_group_id) {
                        $current_page[] = $group_file;
                        $processed_storage_ids[] = $group_file['storage_message_id'];
                    }
                }
                if (!empty($current_page)) {
                    $pages[] = $current_page;
                }
            }
        }

        return $pages;
    }

    public function findForPurchase(int $package_id)
    {
        $stmt = $this->pdo->prepare("SELECT price, seller_user_id FROM media_packages WHERE id = ? AND status = 'available'");
        $stmt->execute([$package_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function markAsSold(int $package_id)
    {
        $stmt = $this->pdo->prepare("UPDATE media_packages SET status = 'sold' WHERE id = ?");
        $stmt->execute([$package_id]);
    }

    /**
     * Menemukan semua paket yang dijual oleh ID penjual tertentu.
     *
     * @param int $sellerId ID internal pengguna penjual.
     * @return array Daftar paket yang dijual.
     */
    public function findAllBySellerId(int $sellerId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT mp.*, mf.file_id as thumbnail_file_id
             FROM media_packages mp
             LEFT JOIN media_files mf ON mp.thumbnail_media_id = mf.id
             WHERE mp.seller_user_id = ? AND mp.status != 'deleted'
             ORDER BY mp.created_at DESC"
        );
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Melakukan soft delete pada sebuah paket dengan mengubah statusnya menjadi 'deleted'.
     *
     * @param int $packageId ID paket yang akan dihapus.
     * @param int $sellerId ID penjual yang meminta penghapusan, untuk verifikasi kepemilikan.
     * @return bool True jika berhasil, false jika gagal.
     * @throws Exception Jika paket tidak ditemukan, bukan milik penjual, atau sudah terjual.
     */
    public function softDeletePackage(int $packageId, int $sellerId): bool
    {
        $package = $this->find($packageId);

        if (!$package) {
            throw new Exception("Paket tidak ditemukan.");
        }

        if ($package['seller_user_id'] != $sellerId) {
            throw new Exception("Anda tidak memiliki izin untuk menghapus paket ini.");
        }

        if ($package['status'] === 'sold') {
            throw new Exception("Tidak dapat menghapus paket yang sudah terjual.");
        }

        if ($package['status'] === 'deleted') {
            return true; // Anggap berhasil jika sudah dihapus
        }

        $stmt = $this->pdo->prepare("UPDATE media_packages SET status = 'deleted' WHERE id = ?");
        return $stmt->execute([$packageId]);
    }

    /**
     * Menghapus paket secara permanen dari database.
     *
     * @param int $packageId ID paket yang akan dihapus.
     * @return array Informasi file yang disimpan untuk dihapus dari Telegram.
     * @throws Exception Jika paket tidak ditemukan atau sudah terjual.
     */
    public function hardDeletePackage(int $packageId): array
    {
        $package = $this->find($packageId);
        if (!$package) {
            throw new Exception("Paket tidak ditemukan.");
        }
        if ($package['status'] === 'sold') {
            throw new Exception("Tidak dapat menghapus paket yang sudah terjual untuk menjaga riwayat transaksi.");
        }

        // Ambil info file yang disimpan sebelum menghapus
        $stmt_files = $this->pdo->prepare("SELECT storage_channel_id, storage_message_id FROM media_files WHERE package_id = ?");
        $stmt_files->execute([$packageId]);
        $files_to_delete = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

        // Hapus file media dan paket
        // Transaksi sekarang ditangani oleh pemanggil (misalnya, webhook)
        try {
            $stmt_delete_files = $this->pdo->prepare("DELETE FROM media_files WHERE package_id = ?");
            $stmt_delete_files->execute([$packageId]);

            $stmt_delete_package = $this->pdo->prepare("DELETE FROM media_packages WHERE id = ?");
            $stmt_delete_package->execute([$packageId]);

        } catch (Exception $e) {
            // Rollback akan ditangani oleh penangan eksepsi global
            throw new Exception("Gagal menghapus paket dari database: " . $e->getMessage());
        }

        return $files_to_delete;
    }

    /**
     * Menemukan semua paket untuk ditampilkan di admin panel.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $sql = "
            SELECT mp.*, u.username as seller_username
            FROM media_packages mp
            JOIN users u ON mp.seller_user_id = u.id
            ORDER BY mp.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByPublicId(string $publicId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM media_packages WHERE public_id = ?");
        $stmt->execute([$publicId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mengubah status proteksi konten sebuah paket.
     *
     * @param int $packageId ID paket yang akan diubah.
     * @param int $sellerId ID penjual untuk verifikasi kepemilikan.
     * @return bool Status proteksi yang baru.
     * @throws Exception Jika paket tidak ditemukan atau bukan milik penjual.
     */
    public function toggleProtection(int $packageId, int $sellerId): bool
    {
        $package = $this->find($packageId);

        if (!$package) {
            throw new Exception("Paket tidak ditemukan.");
        }

        if ($package['seller_user_id'] != $sellerId) {
            throw new Exception("Anda tidak memiliki izin untuk mengubah paket ini.");
        }

        // Gunakan null coalescing operator untuk menangani kasus di mana kolom belum ada
        $current_status = $package['protect_content'] ?? false;
        $new_status = !$current_status;

        $stmt = $this->pdo->prepare("UPDATE media_packages SET protect_content = ? WHERE id = ?");
        $stmt->execute([$new_status, $packageId]);

        return $new_status;
    }

    /**
     * Membuat paket baru dengan ID publik yang diformat.
     *
     * @param int $seller_user_id
     * @param int $bot_id
     * @param string $description
     * @param int $thumbnail_media_id
     * @return int ID internal dari paket yang baru dibuat.
     * @throws Exception
     */
    public function createPackageWithPublicId(int $seller_user_id, int $bot_id, string $description, int $thumbnail_media_id): int
    {
        try {
            // 1. Ambil dan kunci baris pengguna untuk mendapatkan urutan & ID publik
            // Catatan: FOR UPDATE memerlukan transaksi aktif, yang sekarang ditangani oleh webhook.php
            $stmt_user = $this->pdo->prepare("SELECT public_seller_id, seller_package_sequence FROM users WHERE id = ? FOR UPDATE");
            $stmt_user->execute([$seller_user_id]);
            $seller_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if (!$seller_info || !$seller_info['public_seller_id']) {
                throw new Exception("Penjual ini tidak memiliki ID publik.");
            }

            // 2. Tingkatkan nomor urut
            $new_sequence = $seller_info['seller_package_sequence'] + 1;
            $stmt_update_seq = $this->pdo->prepare("UPDATE users SET seller_package_sequence = ? WHERE id = ?");
            $stmt_update_seq->execute([$new_sequence, $seller_user_id]);

            // 3. Buat ID publik
            $public_id = $seller_info['public_seller_id'] . '_' . str_pad($new_sequence, 4, '0', STR_PAD_LEFT);

            // 4. Masukkan paket baru
            $stmt_package = $this->pdo->prepare(
                "INSERT INTO media_packages (seller_user_id, bot_id, description, thumbnail_media_id, status, public_id)
                 VALUES (?, ?, ?, ?, 'pending', ?)"
            );
            $stmt_package->execute([$seller_user_id, $bot_id, $description, $thumbnail_media_id, $public_id]);
            $package_id = $this->pdo->lastInsertId();

            return $package_id;

        } catch (Exception $e) {
            // Rollback akan ditangani oleh penangan eksepsi global di webhook.php
            throw new Exception("Gagal membuat paket baru: " . $e->getMessage());
        }
    }
}
