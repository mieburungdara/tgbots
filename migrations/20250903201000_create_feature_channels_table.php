<?php

/**
 * Migration to create the feature_channels table.
 */

echo "Running migration 20250903201000_create_feature_channels_table.php...\n";

if (!isset($pdo)) {
    echo "Error: PDO object not available.\n";
    return;
}

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `feature_channels` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama internal untuk konfigurasi channel ini.',
      `feature_type` enum('sell','rate','tanya') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Fitur yang terhubung dengan konfigurasi ini.',
      `moderation_channel_id` bigint(20) DEFAULT NULL COMMENT 'ID Channel pribadi untuk backup/moderasi.',
      `public_channel_id` bigint(20) DEFAULT NULL COMMENT 'ID Channel publik untuk menampilkan post.',
      `discussion_group_id` bigint(20) DEFAULT NULL COMMENT 'ID Grup diskusi yang terhubung.',
      `managing_bot_id` bigint(20) NOT NULL COMMENT 'ID Bot yang mengelola channel ini.',
      `owner_user_id` bigint(20) DEFAULT NULL COMMENT 'ID User pemilik channel ini (jika bukan milik admin).',
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `managing_bot_id` (`managing_bot_id`),
      KEY `owner_user_id` (`owner_user_id`),
      CONSTRAINT `fk_feature_channel_bot` FOREIGN KEY (`managing_bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_feature_channel_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Menyimpan konfigurasi channel untuk setiap fitur.';
    ";

    $pdo->exec($sql);
    echo "Table `feature_channels` created successfully (if it didn't exist).\n";

} catch (Exception $e) {
    echo "An error occurred during migration: " . $e->getMessage() . "\n";
    throw $e;
}
