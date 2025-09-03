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
        // Transaksi sekarang ditangani oleh pemanggil (webhook.php).
        // Menghapus beginTransaction/commit/rollback dari sini untuk menghindari transaksi bersarang.
        try {
            // Kurangi saldo pembeli
            $stmt_buyer = $this->pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt_buyer->execute([$price, $buyer_user_id]);

            // Tambah saldo penjual
            $stmt_seller = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt_seller->execute([$price, $seller_user_id]);

            // Catat penjualan
            $stmt_sale = $this->pdo->prepare("INSERT INTO sales (package_id, seller_user_id, buyer_user_id, price) VALUES (?, ?, ?, ?)");
            $stmt_sale->execute([$package_id, $seller_user_id, $buyer_user_id, $price]);

            return true;
        } catch (Exception $e) {
            // Biarkan pemanggil menangani rollback.
            app_log("Sale creation failed: " . $e->getMessage(), 'error');
            // Lemparkan kembali exception agar pemanggil tahu ada masalah dan bisa me-rollback.
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
            "SELECT s.purchased_at, mp.*
             FROM sales s
             JOIN post_packages mp ON s.package_id = mp.id
             WHERE s.buyer_user_id = ?
             ORDER BY s.purchased_at DESC"
        );
        $stmt->execute([$buyer_user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
