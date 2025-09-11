<?php

require_once __DIR__ . '/../../core/database.php';

$pdo = get_db_connection();

$sql = "
CREATE TABLE IF NOT EXISTS `package_views` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `package_id` BIGINT NOT NULL,
  `viewer_user_id` BIGINT NOT NULL,
  `viewed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_view` (`package_id`, `viewer_user_id`),
  INDEX `package_id_idx` (`package_id`),
  CONSTRAINT `fk_view_package` FOREIGN KEY (`package_id`) REFERENCES `media_packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_view_viewer` FOREIGN KEY (`viewer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mencatat setiap tampilan unik per pengguna per paket untuk analitik.'
";

try {
    $pdo->exec($sql);
    echo "Table 'package_views' created successfully or already exists.\n";
} catch (PDOException $e) {
    die("Could not create table 'package_views': " . $e->getMessage());
}


