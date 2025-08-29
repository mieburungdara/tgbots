<?php

/**
 * Migrasi 037: Buat tabel pivot `private_channel_bots`.
 *
 * Tabel ini akan mengelola hubungan banyak-ke-banyak antara `private_channels`
 * dan `bots`, memungkinkan satu channel dikelola oleh banyak bot.
 *
 * Perubahan Skema:
 * 1. Membuat tabel baru `private_channel_bots`.
 * 2. Menambahkan foreign key ke `private_channels` dan `bots`.
 */

require_once __DIR__ . '/../core/database.php';

if (!isset($pdo)) {
    $pdo = get_db_connection();
    if (!$pdo) {
        die("Could not connect to the database." . PHP_EOL);
    }
}

$is_managing_transaction = !$pdo->inTransaction();

if ($is_managing_transaction) {
    $pdo->beginTransaction();
    echo "Transaction started by this script." . PHP_EOL;
}

try {
    echo "1. Creating `private_channel_bots` table..." . PHP_EOL;
    $pdo->exec("
        CREATE TABLE `private_channel_bots` (
            `private_channel_id` INT NOT NULL,
            `bot_id` BIGINT NOT NULL,
            `verified_at` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`private_channel_id`, `bot_id`),
            FOREIGN KEY (`private_channel_id`)
                REFERENCES `private_channels`(`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            FOREIGN KEY (`bot_id`)
                REFERENCES `bots`(`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "   `private_channel_bots` table created successfully." . PHP_EOL;

    if ($is_managing_transaction) {
        $pdo->commit();
        echo PHP_EOL . "Migration successful! This script committed the transaction." . PHP_EOL;
    } else {
        echo PHP_EOL . "Migration step successful! Transaction will be committed by the parent runner." . PHP_EOL;
    }

} catch (Exception $e) {
    if ($is_managing_transaction) {
        $pdo->rollBack();
        echo PHP_EOL . "Transaction rolled back by this script." . PHP_EOL;
    }
    // Re-throw the exception so the main runner can catch it
    throw $e;
}
