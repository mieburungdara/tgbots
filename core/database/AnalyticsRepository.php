<?php

class AnalyticsRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Mengambil ringkasan data penjualan global.
     * @return array
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
     * Mengambil ringkasan data penjualan untuk penjual tertentu.
     * @param int $sellerId
     * @return array
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
     * Mengambil data penjualan per hari untuk grafik.
     * @param int|null $sellerId Jika null, ambil data global.
     * @param int $days Rentang hari ke belakang.
     * @return array
     */
    public function getSalesByDay(int $sellerId = null, int $days = 30): array
    {
        $sql = "SELECT DATE(purchased_at) as sales_date, SUM(price) as daily_revenue
                FROM sales";

        $params = [date('Y-m-d H:i:s', strtotime("-{$days} days"))];

        $sql .= " WHERE purchased_at >= ?";

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
     * Mengambil paket terlaris.
     * @param int $limit
     * @return array
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
}
