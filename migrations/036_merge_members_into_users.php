<?php

/**
 * Migrasi 036: Menggabungkan tabel `members` ke dalam tabel `users`.
 *
 * Perubahan Skema:
 * 1. Menambahkan kolom terkait login (`login_token`, `token_created_at`, `token_used`) ke tabel `users`.
 * 2. Menyalin data yang ada dari `members` ke `users`.
 * 3. Menghapus tabel `members` yang sudah tidak diperlukan.
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
    // 1. Tambahkan kolom baru ke tabel `users`
    echo "1. Adding columns to `users` table..." . PHP_EOL;
    $pdo->exec("
        ALTER TABLE `users`
        ADD COLUMN `login_token` VARCHAR(255) NULL DEFAULT NULL AFTER `status`,
        ADD COLUMN `token_created_at` TIMESTAMP NULL DEFAULT NULL AFTER `login_token`,
        ADD COLUMN `token_used` TINYINT(1) NOT NULL DEFAULT 0 AFTER `token_created_at`;
    ");
    echo "   Columns added successfully." . PHP_EOL;

    // 2. Salin data dari `members` ke `users`
    echo "2. Copying data from `members` to `users`..." . PHP_EOL;
    $sql = "
        UPDATE `users` u
        JOIN `members` m ON u.id = m.user_id
        SET
            u.login_token = m.login_token,
            u.token_created_at = m.token_created_at,
            u.token_used = m.token_used;
    ";
    $pdo->exec($sql);
    echo "   Data copied successfully." . PHP_EOL;

    // 3. Hapus tabel `members`
    echo "3. Dropping `members` table..." . PHP_EOL;
    $pdo->exec("DROP TABLE IF EXISTS `members`;");
    echo "   `members` table dropped successfully." . PHP_EOL;

    // 4. Hapus foreign key constraint lama dari tabel lain jika ada
    // Berdasarkan analisis, tidak ada tabel lain yang memiliki foreign key ke `members`.
    // Namun, kita perlu memastikan constraint `fk_members_user_id` sudah hilang bersama tabelnya.

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
    // Lemparkan kembali error agar runner utama bisa menangkapnya
    throw $e;
}
