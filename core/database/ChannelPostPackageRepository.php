<?php

class ChannelPostPackageRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Creates a new record to link a channel post to a package.
     *
     * @param int $channel_id The ID of the channel.
     * @param int $message_id The ID of the message in the channel.
     * @param int $package_id The ID of the package.
     * @return bool True on success, false on failure.
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
     * Finds a package ID by the channel and message ID.
     *
     * @param int $channel_id The ID of the channel.
     * @param int $message_id The ID of the message in the channel.
     * @return array|null The package record or null if not found.
     */
    public function findByChannelAndMessage(int $channel_id, int $message_id): ?array
    {
        $sql = "SELECT p.* FROM media_packages p
                JOIN channel_post_packages cpp ON p.id = cpp.package_id
                WHERE cpp.channel_id = ? AND cpp.message_id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$channel_id, $message_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}
