<?php

namespace TGBot\Database;

use PDO;

class SubscriptionRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new subscription.
     *
     * @param int $user_id
     * @param int $package_id
     * @param string $endDate
     * @return bool
     */
    public function createSubscription(int $user_id, int $package_id, string $endDate): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO subscriptions (user_id, package_id, start_date, end_date, status) VALUES (?, ?, NOW(), ?, 'active')"
        );
        return $stmt->execute([$user_id, $package_id, $endDate]);
    }

    /**
     * Check if a user has an active subscription for a package.
     *
     * @param int $user_id
     * @param int $package_id
     * @return bool
     */
    public function hasActiveSubscription(int $user_id, int $package_id): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM subscriptions WHERE user_id = ? AND package_id = ? AND status = 'active' AND end_date > NOW() LIMIT 1"
        );
        $stmt->execute([$user_id, $package_id]);
        return $stmt->fetchColumn() !== false;
    }
}
