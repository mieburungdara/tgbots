<?php

namespace TGBot\Database;

use PDO;

class FeatureChannelRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find a feature channel configuration by its ID.
     */
    public function find(int $id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM feature_channels WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find all feature channel configurations.
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT fc.*, b.first_name as bot_name, u.username as owner_username
             FROM feature_channels fc
             JOIN bots b ON fc.managing_bot_id = b.id
             LEFT JOIN users u ON fc.owner_user_id = u.id
             ORDER BY fc.id DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new feature channel configuration.
     */
    public function create(array $data): bool
    {
        $sql = "INSERT INTO feature_channels (name, feature_type, moderation_channel_id, public_channel_id, discussion_group_id, discussion_group_name, managing_bot_id, owner_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['feature_type'],
            $data['moderation_channel_id'] ?: null,
            $data['public_channel_id'] ?: null,
            $data['discussion_group_id'] ?: null,
            $data['discussion_group_name'] ?: null,
            $data['managing_bot_id'],
            $data['owner_user_id'] ?: null,
        ]);
    }

    /**
     * Update an existing feature channel configuration.
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE feature_channels SET name = ?, feature_type = ?, moderation_channel_id = ?, public_channel_id = ?, discussion_group_id = ?, discussion_group_name = ?, managing_bot_id = ?, owner_user_id = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['feature_type'],
            $data['moderation_channel_id'] ?: null,
            $data['public_channel_id'] ?: null,
            $data['discussion_group_id'] ?: null,
            $data['discussion_group_name'] ?: null,
            $data['managing_bot_id'],
            $data['owner_user_id'] ?: null,
            $id
        ]);
    }

    /**
     * Delete a feature channel configuration.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM feature_channels WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Find all feature channel configurations by owner and feature type.
     */
    public function findAllByOwnerAndFeature(int $owner_user_id, string $feature_type): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM feature_channels WHERE owner_user_id = ? AND feature_type = ?");
        $stmt->execute([$owner_user_id, $feature_type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
