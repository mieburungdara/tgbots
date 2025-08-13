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
        $stmt = $this->pdo->prepare("SELECT file_id, type, chat_id, message_id FROM media_files WHERE package_id = ? ORDER BY id");
        $stmt->execute([$package_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
}
