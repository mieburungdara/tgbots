<?php

/**
 * MIGRATION 035: DISABLED.
 *
 * This migration was intended to switch the primary key of the `bots` table
 * from an internal auto-incrementing `id` to the `telegram_bot_id`.
 *
 * However, the final database schema as seen in `updated_schema.sql` reflects a different outcome:
 * - The primary key for the `bots` table is still named `id`.
 * - This `id` column was changed to a BIGINT to store the Telegram Bot ID directly.
 *
 * This migration script is therefore inconsistent with the final state of the database
 * and represents an abandoned or altered refactoring path.
 *
 * Running this script would corrupt the database by attempting to add a redundant
 * `telegram_bot_id` column and incorrectly altering primary and foreign keys.
 *
 * To prevent accidental execution and to document this historical inconsistency,
 * the logic of this migration has been disabled.
 *
 * Original Author: Jules (AI)
 * Date: 2025-08-27
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

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
    echo "Migration 035 has been intentionally disabled due to inconsistency with the final database schema." . PHP_EOL;
    echo "No changes were made." . PHP_EOL;

    if ($is_managing_transaction) {
        $pdo->commit();
        echo PHP_EOL . "Migration successful (no-op)! This script committed the transaction." . PHP_EOL;
    } else {
        echo PHP_EOL . "Migration step successful (no-op)! Transaction will be committed by the parent runner." . PHP_EOL;
    }

} catch (Exception $e) {
    if ($is_managing_transaction) {
        $pdo->rollBack();
        echo PHP_EOL . "Transaction rolled back by this script." . PHP_EOL;
    }
    throw $e;
}
