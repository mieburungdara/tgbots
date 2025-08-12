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
}
