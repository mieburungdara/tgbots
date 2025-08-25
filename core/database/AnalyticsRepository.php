<?php

/**
 * Repositori untuk mengambil data analitik dan statistik dari database.
 * Menyediakan metode untuk mendapatkan ringkasan penjualan, statistik pengguna, dll.
 */
class AnalyticsRepository
{
    private $pdo;

    /**
     * Membuat instance AnalyticsRepository.
     *
     * @param PDO $pdo Objek koneksi database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Mengambil ringkasan data penjualan global.
     *
     * @return array Mengembalikan array dengan `total_sales` dan `total_revenue`.
     */
    public function getGlobalSummary(): array
    {
        $stmt = $this->pdo->query("SELECT COUNT(id) as total_sales, SUM(price) as total_revenue FROM sales");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_sales' => $result['total_sales'] ?? 0,
            'total_revenue' => $result['total_revenue'] ?? 0,
        ];
    }

    /**
     * Mengambil ringkasan statistik pembelian untuk seorang pengguna.
     *
     * @param int $buyerId ID internal pengguna (pembeli).
     * @return array Mengembalikan array dengan `total_purchases` dan `total_spent`.
     */
    public function getUserPurchaseStats(int $buyerId): array
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(id) as total_purchases, SUM(price) as total_spent FROM sales WHERE buyer_user_id = ?");
        $stmt->execute([$buyerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_purchases' => $result['total_purchases'] ?? 0,
            'total_spent' => $result['total_spent'] ?? 0,
        ];
    }

    /**
     * Mengambil ringkasan statistik penjualan untuk seorang penjual.
     *
     * @param int $sellerId ID internal pengguna (penjual).
     * @return array Mengembalikan array dengan `total_sales` dan `total_revenue`.
     */
    public function getSellerSummary(int $sellerId): array
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(id) as total_sales, SUM(price) as total_revenue FROM sales WHERE seller_user_id = ?");
        $stmt->execute([$sellerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_sales' => $result['total_sales'] ?? 0,
            'total_revenue' => $result['total_revenue'] ?? 0,
        ];
    }

    /**
     * Mengambil data pendapatan harian atau bulanan untuk ditampilkan dalam grafik.
     * Agregasi beralih ke bulanan jika rentang hari lebih dari 90.
     *
     * @param int|null $sellerId Jika null, ambil data global. Jika diisi, filter berdasarkan ID penjual.
     * @param int $days Jumlah hari terakhir yang akan diambil datanya.
     * @return array Daftar data, masing-masing berisi `sales_date` dan `daily_revenue`.
     */
    public function getSalesByDay(int $sellerId = null, int $days = 30): array
    {
        $params = [date('Y-m-d H:i:s', strtotime("-{$days} days"))];

        $sql = "SELECT ";
        if ($days > 90) {
            // Agregasi bulanan untuk rentang waktu yang lebih panjang
            $sql .= "DATE_FORMAT(purchased_at, '%Y-%m-01') as sales_date, SUM(price) as daily_revenue";
        } else {
            // Agregasi harian untuk rentang waktu yang lebih pendek
            $sql .= "DATE(purchased_at) as sales_date, SUM(price) as daily_revenue";
        }

        $sql .= " FROM sales WHERE purchased_at >= ?";

        if ($sellerId !== null) {
            $sql .= " AND seller_user_id = ?";
            $params[] = $sellerId;
        }

        $sql .= " GROUP BY sales_date ORDER BY sales_date ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil daftar paket konten terlaris.
     *
     * @param int $limit Jumlah maksimum paket yang akan diambil.
     * @return array Daftar paket terlaris.
     */
    public function getTopSellingPackages(int $limit = 5): array
    {
        $sql = "
            SELECT p.id, p.description, COUNT(s.id) as sales_count, SUM(s.price) as total_revenue
            FROM sales s
            JOIN media_packages p ON s.package_id = p.id
            GROUP BY p.id, p.description
            ORDER BY sales_count DESC, total_revenue DESC
            LIMIT ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mengambil daftar paket konten terlaris untuk seorang penjual.
     *
     * @param int $sellerId ID penjual.
     * @param int $limit Jumlah maksimum paket yang akan diambil.
     * @return array Daftar paket terlaris.
     */
    public function getTopSellingPackagesForSeller(int $sellerId, int $limit = 5): array
    {
        $sql = "
            SELECT p.id, p.public_id, p.description, COUNT(s.id) as sales_count, SUM(s.price) as total_revenue
            FROM sales s
            JOIN media_packages p ON s.package_id = p.id
            WHERE s.seller_user_id = ?
            GROUP BY p.id, p.public_id, p.description
            ORDER BY sales_count DESC, total_revenue DESC
            LIMIT ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(1, $sellerId, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
