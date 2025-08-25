<?php

/**
 * Repositori untuk mengelola paket media (`media_packages`).
 * Menyediakan semua operasi CRUD dan query terkait untuk paket konten.
 */
class PackageRepository
{
    private $pdo;

    /**
     * Membuat instance PackageRepository.
     *
     * @param PDO $pdo Objek koneksi database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Menemukan sebuah paket berdasarkan ID internalnya.
     *
     * @param int $id ID internal paket.
     * @return array|false Data paket atau false jika tidak ditemukan.
     */
    public function find(int $id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM media_packages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil semua file yang tersimpan (di channel penyimpanan) yang terkait dengan sebuah paket.
     *
     * @param int $package_id ID internal paket.
     * @return array Daftar file, masing-masing berisi `storage_channel_id` dan `storage_message_id`.
     */
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

    /**
     * Menemukan paket yang tersedia untuk dibeli berdasarkan ID-nya.
     * Hanya mengembalikan paket dengan status 'available'.
     *
     * @param int $package_id ID internal paket.
     * @return array|false Data harga dan penjual, atau false jika tidak tersedia untuk dibeli.
     */
    public function findForPurchase(int $package_id)
    {
        $stmt = $this->pdo->prepare("SELECT price, seller_user_id FROM media_packages WHERE id = ? AND status = 'available'");
        $stmt->execute([$package_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Menandai sebuah paket sebagai 'terjual' (sold).
     *
     * @param int $package_id ID internal paket yang akan ditandai.
     * @return void
     */
    public function markAsSold(int $package_id)
    {
        $stmt = $this->pdo->prepare("UPDATE media_packages SET status = 'sold' WHERE id = ?");
        $stmt->execute([$package_id]);
    }

    /**
     * Menemukan semua paket yang dijual oleh ID penjual tertentu.
     *
     * @param int $sellerTelegramId ID Telegram pengguna penjual.
     * @return array Daftar paket yang dijual.
     */
    public function findAllBySellerId(int $sellerTelegramId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT mp.*
             FROM media_packages mp
             WHERE mp.seller_user_id = ? AND mp.status != 'deleted'
             ORDER BY mp.created_at DESC"
        );
        $stmt->execute([$sellerTelegramId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Melakukan soft delete pada sebuah paket dengan mengubah statusnya menjadi 'deleted'.
     *
     * @param int $packageId ID paket yang akan dihapus.
     * @param int $sellerTelegramId ID Telegram penjual yang meminta penghapusan, untuk verifikasi kepemilikan.
     * @return bool True jika berhasil, false jika gagal.
     * @throws Exception Jika paket tidak ditemukan, bukan milik penjual, atau sudah terjual.
     */
    public function softDeletePackage(int $packageId, int $sellerTelegramId): bool
    {
        $package = $this->find($packageId);

        if (!$package) {
            throw new Exception("Paket tidak ditemukan.");
        }

        if ($package['seller_user_id'] != $sellerTelegramId) {
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
     * Menghapus paket secara permanen dari database, termasuk file media terkait.
     * Metode ini sebaiknya dijalankan dalam sebuah transaksi.
     *
     * @param int $packageId ID paket yang akan dihapus.
     * @return array Informasi file media yang dihapus, untuk keperluan pembersihan di Telegram.
     * @throws Exception Jika paket tidak ditemukan atau sudah terjual (untuk melindungi riwayat).
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
     * Menemukan semua paket dengan paginasi, biasanya untuk panel admin.
     * Menggabungkan dengan data pengguna untuk menampilkan username penjual.
     *
     * @param int $limit Jumlah maksimum paket per halaman.
     * @param int $offset Jumlah paket yang akan dilewati (untuk paginasi).
     * @return array Daftar paket.
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $sql = "
            SELECT mp.*, u.username as seller_username
            FROM media_packages mp
            JOIN users u ON mp.seller_user_id = u.telegram_id
            ORDER BY mp.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Menemukan sebuah paket berdasarkan ID publiknya yang unik.
     *
     * @param string $publicId ID publik paket (misal: 'ABCD_0001').
     * @return array|false Data paket atau false jika tidak ditemukan.
     */
    public function findByPublicId(string $publicId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM media_packages WHERE public_id = ?");
        $stmt->execute([$publicId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mengubah status proteksi konten (`protect_content`) sebuah paket.
     *
     * @param int $packageId ID paket yang akan diubah.
     * @param int $sellerTelegramId ID Telegram penjual yang meminta, untuk verifikasi kepemilikan.
     * @return bool Status proteksi konten yang baru (true jika aktif, false jika tidak).
     * @throws Exception Jika paket tidak ditemukan atau pengguna bukan pemiliknya.
     */
    public function toggleProtection(int $packageId, int $sellerTelegramId): bool
    {
        $package = $this->find($packageId);

        if (!$package) {
            throw new Exception("Paket tidak ditemukan.");
        }

        if ($package['seller_user_id'] != $sellerTelegramId) {
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
     * Memperbarui detail sebuah paket (deskripsi dan harga).
     *
     * @param int $packageId ID paket yang akan diperbarui.
     * @param int $sellerTelegramId ID Telegram penjual yang meminta, untuk verifikasi kepemilikan.
     * @param string $description Deskripsi baru untuk paket.
     * @param int|null $price Harga baru untuk paket (bisa null jika tidak diubah).
     * @return bool True jika berhasil, false jika gagal.
     * @throws Exception Jika paket tidak ditemukan atau pengguna bukan pemiliknya.
     */
    public function updatePackageDetails(int $packageId, int $sellerTelegramId, string $description, ?int $price): bool
    {
        $package = $this->find($packageId);

        if (!$package) {
            throw new Exception("Paket tidak ditemukan.");
        }

        if ($package['seller_user_id'] != $sellerTelegramId) {
            throw new Exception("Anda tidak memiliki izin untuk mengubah paket ini.");
        }

        $stmt = $this->pdo->prepare(
            "UPDATE media_packages SET description = ?, price = ? WHERE id = ?"
        );
        return $stmt->execute([$description, $price, $packageId]);
    }

    /**
     * Membuat paket baru dengan ID publik yang unik dan diformat secara otomatis.
     * Metode ini secara atomik menaikkan nomor urut paket penjual dan membuat ID.
     * Sebaiknya dijalankan di dalam sebuah transaksi.
     *
     * @param int $seller_telegram_id ID Telegram penjual.
     * @param int $telegram_bot_id ID Telegram bot yang digunakan untuk membuat paket.
     * @param string $description Deskripsi paket.
     * @param int $thumbnail_media_id ID internal dari file media yang dijadikan thumbnail.
     * @return int ID internal dari paket yang baru dibuat.
     * @throws Exception Jika terjadi kegagalan dalam transaksi atau jika penjual tidak valid.
     */
    public function createPackageWithPublicId(int $seller_telegram_id, int $telegram_bot_id, string $description, int $thumbnail_media_id): int
    {
        try {
            // 1. Ambil dan kunci baris pengguna untuk mendapatkan urutan & ID publik
            // Catatan: FOR UPDATE memerlukan transaksi aktif, yang sekarang ditangani oleh webhook.php
            $stmt_user = $this->pdo->prepare("SELECT public_seller_id, seller_package_sequence FROM users WHERE telegram_id = ? FOR UPDATE");
            $stmt_user->execute([$seller_telegram_id]);
            $seller_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if (!$seller_info || !$seller_info['public_seller_id']) {
                throw new Exception("Penjual ini tidak memiliki ID publik.");
            }

            // 2. Tingkatkan nomor urut
            $new_sequence = $seller_info['seller_package_sequence'] + 1;
            $stmt_update_seq = $this->pdo->prepare("UPDATE users SET seller_package_sequence = ? WHERE telegram_id = ?");
            $stmt_update_seq->execute([$new_sequence, $seller_telegram_id]);

            // 3. Buat ID publik
            $public_id = $seller_info['public_seller_id'] . '_' . str_pad($new_sequence, 4, '0', STR_PAD_LEFT);

            // 4. Masukkan paket baru
            $stmt_package = $this->pdo->prepare(
                "INSERT INTO media_packages (seller_user_id, bot_id, description, thumbnail_media_id, status, public_id)
                 VALUES (?, ?, ?, ?, 'pending', ?)"
            );
            $stmt_package->execute([$seller_telegram_id, $telegram_bot_id, $description, $thumbnail_media_id, $public_id]);
            $package_id = $this->pdo->lastInsertId();

            return $package_id;

        } catch (Exception $e) {
            // Rollback akan ditangani oleh penangan eksepsi global di webhook.php
            throw new Exception("Gagal membuat paket baru: " . $e->getMessage());
        }
    }

    /**
     * Mendapatkan data file media yang dijadikan thumbnail untuk sebuah paket.
     * Jika thumbnail spesifik telah diatur, metode ini akan mengembalikannya.
     * Jika tidak, metode ini akan mengembalikan file media pertama yang ditambahkan ke paket.
     *
     * @param int $package_id ID internal paket.
     * @return array|false Data file thumbnail sebagai array asosiatif, atau false jika tidak ditemukan.
     */
    public function getThumbnailFile(int $package_id)
    {
        $package = $this->find($package_id);
        if (!$package) return false;

        $thumbnail_media_id = $package['thumbnail_media_id'];

        // Jika thumbnail spesifik di-set, gunakan itu
        if (!empty($thumbnail_media_id)) {
            $stmt = $this->pdo->prepare("SELECT * FROM media_files WHERE id = ?");
            $stmt->execute([$thumbnail_media_id]);
            $thumb = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($thumb) return $thumb;
        }

        // Jika tidak, gunakan file pertama yang terkait dengan paket
        $stmt = $this->pdo->prepare("SELECT * FROM media_files WHERE package_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$package_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
