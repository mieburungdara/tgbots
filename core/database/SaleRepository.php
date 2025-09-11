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
     * @return bool True jika pengguna sudah pernah membeli atau diberi hadiah, false jika belum.
     */
    public function hasUserPurchased(int $package_id, int $user_id): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM sales WHERE package_id = ? AND granted_to_user_id = ?");
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
    public function createSale(int $package_id, int $seller_user_id, int $buyer_user_id, int $granted_to_user_id, float $price, ?string $expires_at = null): bool
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
            $sql = "INSERT INTO sales (package_id, seller_user_id, buyer_user_id, granted_to_user_id, price, commission, sale_type";
            $values = "?, ?, ?, ?, ?, ?, 'one_time'";
            $params = [$package_id, $seller_user_id, $buyer_user_id, $granted_to_user_id, $price, $commission_amount];

            if ($expires_at !== null) {
                $sql .= ", expires_at";
                $values .= ", ?";
                $params[] = $expires_at;
            }

            $sql .= ") VALUES (" . $values . ")";
            $stmt_sale = $this->pdo->prepare($sql);
            $stmt_sale->execute($params);

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

    /**
     * Mendapatkan detail penjualan berdasarkan package_id dan granted_to_user_id.
     * Digunakan untuk memeriksa status hadiah.
     *
     * @param int $package_id ID internal paket.
     * @param int $granted_to_user_id ID pengguna yang diberikan akses.
     * @return array|false Detail penjualan atau false jika tidak ditemukan.
     */
    public function getSaleDetails(int $package_id, int $granted_to_user_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sales WHERE package_id = ? AND granted_to_user_id = ? ORDER BY sale_date DESC LIMIT 1");
        $stmt->execute([$package_id, $granted_to_user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Menandai hadiah sebagai sudah diklaim.
     *
     * @param int $sale_id ID penjualan.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function markSaleAsClaimed(int $sale_id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE sales SET claimed_at = NOW() WHERE id = ?");
        return $stmt->execute([$sale_id]);
    }

    public function createSubscriptionSale(int $seller_user_id, int $buyer_user_id, float $price): bool
    {
        // Logika komisi yang sama dapat diterapkan di sini jika diperlukan
        $commission_rate = 0.10; // Contoh komisi tetap 10% untuk langganan
        $commission_amount = floor($price * $commission_rate);
        $seller_earning = $price - $commission_amount;

        // Kurangi saldo pembeli
        $stmt_buyer = $this->pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt_buyer->execute([$price, $buyer_user_id]);

        // Tambah saldo penjual
        $stmt_seller = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt_seller->execute([$seller_earning, $seller_user_id]);

        // Tambah saldo komisi ke akun sistem
        if ($commission_amount > 0 && defined('SYSTEM_INCOME_USER_ID')) {
            $stmt_admin = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt_admin->execute([$commission_amount, SYSTEM_INCOME_USER_ID]);
        }

        // Catat penjualan langganan (package_id adalah NULL)
        $stmt_sale = $this->pdo->prepare("INSERT INTO sales (package_id, seller_user_id, buyer_user_id, price, commission, sale_type) VALUES (NULL, ?, ?, ?, ?, 'subscription');");
        return $stmt_sale->execute([$seller_user_id, $buyer_user_id, $price, $commission_amount]);
    }

    /**
     * Menemukan hadiah yang akan kadaluarsa dan belum diklaim.
     *
     * @param int $hours_until_expiration Batas waktu dalam jam.
     * @return array Daftar hadiah yang akan kadaluarsa.
     */
    public function findExpiringUnclaimedGifts(int $hours_until_expiration): array
    {
        $sql = "
            SELECT
                s.*,
                mp.public_id
            FROM
                sales s
            JOIN
                media_packages mp ON s.package_id = mp.id
            WHERE
                s.granted_to_user_id IS NOT NULL
                AND s.buyer_user_id != s.granted_to_user_id
                AND s.claimed_at IS NULL
                AND s.expires_at IS NOT NULL
                AND s.expires_at <= NOW() + INTERVAL :hours HOUR
                AND s.expires_at > NOW()
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':hours', $hours_until_expiration, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Membatalkan penjualan hadiah dan mengembalikan saldo pembeli.
     *
     * @param int $sale_id ID penjualan hadiah.
     * @param int $buyer_user_id ID pembeli (pengirim hadiah).
     * @param float $price Harga hadiah yang akan dikembalikan.
     * @return bool True jika berhasil, false jika gagal.
     * @throws Exception Jika terjadi kesalahan database.
     */
    public function cancelGiftSale(int $sale_id, int $buyer_user_id, float $price): bool
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Kembalikan saldo pembeli (pengirim hadiah)
            $stmt_refund = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt_refund->execute([$price, $buyer_user_id]);

            // 2. Tandai penjualan sebagai dibatalkan (atau hapus, tergantung kebijakan)
            // Untuk saat ini, kita akan menandainya sebagai 'cancelled'
            $stmt_cancel_sale = $this->pdo->prepare("UPDATE sales SET sale_type = 'cancelled_gift' WHERE id = ?");
            $stmt_cancel_sale->execute([$sale_id]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            app_log("Failed to cancel gift sale: " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Mendapatkan detail penjualan berdasarkan ID penjualan.
     *
     * @param int $sale_id ID penjualan.
     * @return array|false Detail penjualan atau false jika tidak ditemukan.
     */
    public function getSaleDetailsById(int $sale_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sales WHERE id = ?");
        $stmt->execute([$sale_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Menghadiahkan ulang sebuah penjualan hadiah kepada pengguna baru.
     *
     * @param int $sale_id ID penjualan hadiah yang akan dihadiahkan ulang.
     * @param int $new_granted_to_user_id ID pengguna baru yang akan menerima hadiah.
     * @return bool True jika berhasil, false jika gagal.
     * @throws Exception Jika terjadi kesalahan database.
     */
    public function reGiftSale(int $sale_id, int $new_granted_to_user_id): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Reset claimed_at dan set expires_at ke 7 hari dari sekarang
            $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));

            $stmt = $this->pdo->prepare("UPDATE sales SET granted_to_user_id = ?, claimed_at = NULL, expires_at = ? WHERE id = ?");
            $stmt->execute([$new_granted_to_user_id, $expires_at, $sale_id]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            app_log("Failed to re-gift sale: " . $e->getMessage(), 'error');
            throw $e;
        }
    }
