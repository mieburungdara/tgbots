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
use PDOException;

/**
 * Class ChannelPostPackageRepository
 * @package TGBot\Database
 */
class ChannelPostPackageRepository
{
    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * ChannelPostPackageRepository constructor.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new channel post package.
     *
     * @param int $channel_id
     * @param int $message_id
     * @param int $package_id
     * @return bool
     */
    public function create(int $channel_id, int $message_id, int $package_id): bool
    {
        $sql = "INSERT INTO channel_post_packages (channel_id, message_id, package_id) VALUES (?, ?, ?)";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$channel_id, $message_id, $package_id]);
        } catch (PDOException $e) {
            // Log error
            app_log("Error creating channel post package link: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Find a package by channel and message ID.
     *
     * @param int $channel_id
     * @param int $message_id
     * @return array|null
     */
    public function findByChannelAndMessage(int $channel_id, int $message_id): ?array
    {
        $sql = "SELECT p.* FROM post_packages p
                JOIN channel_post_packages cpp ON p.id = cpp.package_id
                WHERE cpp.channel_id = ? AND cpp.message_id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$channel_id, $message_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Find a channel post by package ID.
     *
     * @param int $package_id
     * @return array|null
     */
    public function findByPackageId(int $package_id): ?array
    {
        $sql = "SELECT * FROM channel_post_packages WHERE package_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$package_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}
