<?php

namespace TGBot\Database;

use PDO;
use Exception;

/**
 * Repositori untuk mengelola data penjualan (`sales`).
 * Menyediakan metode untuk membuat catatan penjualan, memeriksa riwayat pembelian, dll.
 */
class SaleRepository
{
    private $pdo;

    /**
     * Membuat instance SaleRepository.
     *
     * @param PDO $pdo Objek koneksi database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Memeriksa apakah seorang pengguna telah membeli sebuah paket tertentu.
     *
     * @param int $package_id ID internal paket.
     * @param int $user_id ID Telegram pengguna.
     * @return bool True jika pengguna sudah pernah membeli, false jika belum.
     */
    public function hasUserPurchased(int $package_id, int $user_id): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM sales WHERE package_id = ? AND buyer_user_id = ?");
        $stmt->execute([$package_id, $user_id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Membuat catatan penjualan baru, mengurangi saldo pembeli, dan menambah saldo penjual.
     * Metode ini harus dijalankan di dalam sebuah transaksi yang dikelola oleh pemanggil.
     *
     * @param int $package_id ID paket yang dijual.
     * @param int $seller_user_id ID Telegram penjual.
     * @param int $buyer_user_id ID Telegram pembeli.
     * @param float $price Harga penjualan.
     * @return bool True jika operasi database berhasil.
     * @throws Exception Jika terjadi kesalahan database.
     */
    public function createSale(int $package_id, int $seller_user_id, int $buyer_user_id, float $price): bool
    {
        // CATATAN: Pastikan konstanta SYSTEM_INCOME_USER_ID sudah didefinisikan di config.php
        if (!defined('SYSTEM_INCOME_USER_ID')) {
            throw new Exception('ID pengguna sistem untuk komisi belum diatur.');
        }

        try {
            // 1. Hitung komisi berdasarkan tingkatan harga
            if ($price < 10000) {
                $commission_rate = 0.10; // 10%
            } elseif ($price >= 100000) {
                $commission_rate = 0.05; // 5%
            } else {
                $commission_rate = 0.07; // 7%
            }

            $commission_amount = floor($price * $commission_rate);
            $seller_earning = $price - $commission_amount;

            // 2. Kurangi saldo pembeli
            $stmt_buyer = $this->pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt_buyer->execute([$price, $buyer_user_id]);

            // 3. Tambah saldo penjual
            $stmt_seller = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt_seller->execute([$seller_earning, $seller_user_id]);

            // 4. Tambah saldo komisi ke akun sistem
            if ($commission_amount > 0) {
                $stmt_admin = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt_admin->execute([$commission_amount, SYSTEM_INCOME_USER_ID]);
            }

            // 5. Catat penjualan
            $stmt_sale = $this->pdo->prepare("INSERT INTO sales (package_id, seller_user_id, buyer_user_id, price, commission) VALUES (?, ?, ?, ?, ?)");
            $stmt_sale->execute([$package_id, $seller_user_id, $buyer_user_id, $price, $commission_amount]);

            return true;
        } catch (Exception $e) {
            app_log("Sale creation failed: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Menemukan semua paket yang telah dibeli oleh seorang pengguna.
     *
     * @param int $buyer_user_id ID Telegram pengguna (pembeli).
     * @return array Daftar paket yang dibeli, termasuk tanggal pembelian.
     */
    public function findPackagesByBuyerId(int $buyer_user_id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.purchased_at, mp.*\n             FROM sales s\n             JOIN media_packages mp ON s.package_id = mp.id\n             WHERE s.buyer_user_id = ?\n             ORDER BY s.purchased_at DESC"
        );
        $stmt->execute([$buyer_user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count the number of sales for a specific package.
     *
     * @param int $package_id The ID of the package.
     * @return int The number of sales for the package.
     */
    public function countSalesForPackage(int $package_id): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sales WHERE package_id = ?");
        $stmt->execute([$package_id]);
        return (int)$stmt->fetchColumn();
    }
}
