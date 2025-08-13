<?php

class SaleRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Periksa apakah pengguna telah membeli paket tertentu.
     *
     * @param int $package_id
     * @param int $user_id
     * @return bool
     */
    public function hasUserPurchased(int $package_id, int $user_id): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM sales WHERE package_id = ? AND buyer_user_id = ?");
        $stmt->execute([$package_id, $user_id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Buat catatan penjualan baru dan transfer saldo.
     *
     * @param int $package_id
     * @param int $seller_id
     * @param int $buyer_id
     * @param float $price
     * @return bool
     */
    public function createSale(int $package_id, int $seller_id, int $buyer_id, float $price): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Kurangi saldo pembeli
            $stmt_buyer = $this->pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt_buyer->execute([$price, $buyer_id]);

            // Tambah saldo penjual
            $stmt_seller = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt_seller->execute([$price, $seller_id]);

            // Catat penjualan
            $stmt_sale = $this->pdo->prepare("INSERT INTO sales (package_id, seller_user_id, buyer_user_id, price) VALUES (?, ?, ?, ?)");
            $stmt_sale->execute([$package_id, $seller_id, $buyer_id, $price]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            // Mungkin ingin mencatat error di sini
            app_log("Sale creation failed: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Menemukan semua paket yang dibeli oleh ID pembeli tertentu.
     *
     * @param int $buyerId ID internal pengguna pembeli.
     * @return array Daftar paket yang dibeli.
     */
    public function findPackagesByBuyerId(int $buyerId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.purchased_at, mp.*, mf.file_id as thumbnail_file_id
             FROM sales s
             JOIN media_packages mp ON s.package_id = mp.id
             LEFT JOIN media_files mf ON mp.thumbnail_media_id = mf.id
             WHERE s.buyer_user_id = ?
             ORDER BY s.purchased_at DESC"
        );
        $stmt->execute([$buyerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
