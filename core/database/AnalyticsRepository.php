<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot\Database;

use PDO;

/**
 * Class AnalyticsRepository
 * @package TGBot\Database
 */
class AnalyticsRepository
{
    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * AnalyticsRepository constructor.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get global sales summary.
     *
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
     * Get user purchase stats.
     *
     * @param int $buyer_user_id
     * @return array
     */
    public function getUserPurchaseStats(int $buyer_user_id): array
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(id) as total_purchases, SUM(price) as total_spent FROM sales WHERE buyer_user_id = ?");
        $stmt->execute([$buyer_user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_purchases' => $result['total_purchases'] ?? 0,
            'total_spent' => $result['total_spent'] ?? 0,
        ];
    }

    /**
     * Get seller summary.
     *
     * @param int $seller_user_id
     * @return array
     */
    public function getSellerSummary(int $seller_user_id): array
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(id) as total_sales, SUM(price) as total_revenue FROM sales WHERE seller_user_id = ?");
        $stmt->execute([$seller_user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_sales' => $result['total_sales'] ?? 0,
            'total_revenue' => $result['total_revenue'] ?? 0,
        ];
    }

    /**
     * Get sales by day.
     *
     * @param int|null $seller_user_id
     * @param int $days
     * @param int|null $packageId
     * @return array
     */
    public function getSalesByDay(?int $seller_user_id = null, int $days = 30, ?int $packageId = null): array
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

        if ($seller_user_id !== null) {
            $sql .= " AND seller_user_id = ?";
            $params[] = $seller_user_id;
        }

        if ($packageId !== null) {
            $sql .= " AND package_id = ?";
            $params[] = $packageId;
        }

        $sql .= " GROUP BY sales_date ORDER BY sales_date ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get top selling packages.
     *
     * @param int $limit
     * @return array
     */
    public function getTopSellingPackages(int $limit = 5): array
    {
        $sql = "
            SELECT p.id, p.description, COUNT(s.id) as sales_count, SUM(s.price) as total_revenue
            FROM sales s
            JOIN post_packages p ON s.package_id = p.id
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
     * Get top selling packages for a seller.
     *
     * @param int $seller_user_id
     * @param int $limit
     * @return array
     */
    public function getTopSellingPackagesForSeller(int $seller_user_id, int $limit = 5): array
    {
        $sql = "
            SELECT p.id, p.public_id, p.description, COUNT(s.id) as sales_count, SUM(s.price) as total_revenue
            FROM sales s
            JOIN post_packages p ON s.package_id = p.id
            WHERE s.seller_user_id = ?
            GROUP BY p.id, p.public_id, p.description
            ORDER BY sales_count DESC, total_revenue DESC
            LIMIT ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(1, $seller_user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get summary for a package.
     *
     * @param int $packageId
     * @return array
     */
    public function getSummaryForPackage(int $packageId): array
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(id) as total_sales, SUM(price) as total_revenue FROM sales WHERE package_id = ?");
        $stmt->execute([$packageId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_sales' => $result['total_sales'] ?? 0,
            'total_revenue' => $result['total_revenue'] ?? 0,
        ];
    }

    /**
     * Get recent sales for a package.
     *
     * @param int $packageId
     * @param int $limit
     * @return array
     */
    public function getRecentSalesForPackage(int $packageId, int $limit = 10): array
    {
        $sql = "
            SELECT s.purchased_at, s.price, u.username as buyer_username
            FROM sales s
            JOIN users u ON s.buyer_user_id = u.id
            WHERE s.package_id = ?
            ORDER BY s.purchased_at DESC
            LIMIT ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(1, $packageId, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
