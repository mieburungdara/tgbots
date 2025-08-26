<?php

/**
 * Migrasi 035: Beralih dari ID internal ke ID Telegram sebagai Primary Key.
 *
 * Perubahan Skema:
 * 1.  Menambahkan `telegram_bot_id` ke tabel `bots` dan mengisinya dari token.
 * 2.  Mengubah semua kolom `user_id` dan `bot_id` di tabel terkait menjadi BIGINT.
 * 3.  Mengganti nilai `user_id` dan `bot_id` internal dengan `telegram_id` dan `telegram_bot_id`.
 * 4.  Mengubah Primary Key di `users` dari `id` menjadi `telegram_id`.
 * 5.  Mengubah Primary Key di `bots` dari `id` menjadi `telegram_bot_id`.
 * 6.  Membuat ulang semua Foreign Key yang relevan.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../core/database.php';

// Jika skrip ini di-include oleh runner lain, $pdo sudah harus ada.
// Jika tidak, buat koneksi baru untuk eksekusi mandiri.
if (!isset($pdo)) {
    $pdo = get_db_connection();
    if (!$pdo) {
        die("Could not connect to the database." . PHP_EOL);
    }
}

// Tentukan apakah skrip ini perlu mengelola transaksinya sendiri.
$is_managing_transaction = !$pdo->inTransaction();

// Daftar tabel yang memiliki foreign key ke `users` atau `bots`
// dan nama constraint-nya. Ini mungkin perlu disesuaikan.
$constraints_to_rebuild = [
    'rel_user_bot' => [
        ['name' => 'rel_user_bot_ibfk_1', 'col' => 'user_id', 'ref_table' => 'users', 'ref_col' => 'telegram_id'],
        ['name' => 'rel_user_bot_ibfk_2', 'col' => 'bot_id', 'ref_table' => 'bots', 'ref_col' => 'telegram_bot_id']
    ],
    'messages' => [
        ['name' => 'messages_ibfk_1', 'col' => 'user_id', 'ref_table' => 'users', 'ref_col' => 'telegram_id'],
        ['name' => 'messages_ibfk_2', 'col' => 'bot_id', 'ref_table' => 'bots', 'ref_col' => 'telegram_bot_id']
    ],
    'members' => [
        ['name' => 'fk_members_user_id', 'col' => 'user_id', 'ref_table' => 'users', 'ref_col' => 'telegram_id']
    ],
    'bot_settings' => [
        ['name' => 'bot_settings_ibfk_1', 'col' => 'bot_id', 'ref_table' => 'bots', 'ref_col' => 'telegram_bot_id']
    ],
    'seller_sales_channels' => [
        ['name' => 'seller_sales_channels_ibfk_1', 'col' => 'user_id', 'ref_table' => 'users', 'ref_col' => 'telegram_id'],
        ['name' => 'seller_sales_channels_ibfk_2', 'col' => 'bot_id', 'ref_table' => 'bots', 'ref_col' => 'telegram_bot_id']
    ],
    'channel_post_packages' => [
        ['name' => 'channel_post_packages_ibfk_1', 'col' => 'bot_id', 'ref_table' => 'bots', 'ref_col' => 'telegram_bot_id']
    ],
    'balance_transactions' => [
        ['name' => 'balance_transactions_ibfk_1', 'col' => 'user_id', 'ref_table' => 'users', 'ref_col' => 'telegram_id']
    ],
    'sales' => [
        // Asumsi nama constraint, mungkin perlu verifikasi
        ['name' => 'sales_ibfk_1', 'col' => 'seller_user_id', 'ref_table' => 'users', 'ref_col' => 'telegram_id'],
        ['name' => 'sales_ibfk_2', 'col' => 'buyer_user_id', 'ref_table' => 'users', 'ref_col' => 'telegram_id'],
    ]
    // Tambahkan tabel lain jika ada
];

if ($is_managing_transaction) {
    $pdo->beginTransaction();
    echo "Transaction started by this script." . PHP_EOL;
}

try {
    // 1. Tambah dan isi `telegram_bot_id` di tabel `bots`
    echo "1. Altering `bots` table..." . PHP_EOL;
    try {
        $pdo->exec("ALTER TABLE `bots` ADD COLUMN `telegram_bot_id` BIGINT NULL UNIQUE AFTER `id`;");
    } catch (PDOException $e) {
        // SQLSTATE 42S21 adalah error untuk 'Duplicate column name'
        if ($e->getCode() === '42S21') {
            echo "   Warning: Column 'telegram_bot_id' already exists. Skipping 'ADD COLUMN'." . PHP_EOL;
        } else {
            throw $e; // Lemparkan kembali error lain yang tidak terduga
        }
    }
    $pdo->exec("UPDATE `bots` SET `telegram_bot_id` = CAST(SUBSTRING_INDEX(`token`, ':', 1) AS UNSIGNED);");

    // Validasi
    $stmt = $pdo->query("SELECT COUNT(*) FROM `bots` WHERE `telegram_bot_id` IS NULL;");
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Found bots with NULL telegram_bot_id. Migration cannot continue.");
    }
    echo "   `bots` table altered and populated successfully." . PHP_EOL;

    // 2. Drop Foreign Keys
    echo "2. Dropping foreign keys..." . PHP_EOL;
    foreach ($constraints_to_rebuild as $table => $constraints) {
        foreach ($constraints as $constraint) {
            try {
                $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `{$constraint['name']}`;");
                echo "   Dropped FK `{$constraint['name']}` from `$table`." . PHP_EOL;
            } catch (PDOException $e) {
                echo "   Warning: Could not drop FK `{$constraint['name']}` from `$table`. It might not exist. Error: " . $e->getMessage() . PHP_EOL;
            }
        }
    }

    // 3. Update data di tabel anak
    echo "3. Updating foreign key columns data..." . PHP_EOL;
    $pdo->exec("UPDATE `rel_user_bot` ru JOIN `users` u ON ru.user_id = u.id SET ru.user_id = u.telegram_id;");
    $pdo->exec("UPDATE `rel_user_bot` ru JOIN `bots` b ON ru.bot_id = b.id SET ru.bot_id = b.telegram_bot_id;");
    $pdo->exec("UPDATE `messages` m JOIN `users` u ON m.user_id = u.id SET m.user_id = u.telegram_id;");
    $pdo->exec("UPDATE `messages` m JOIN `bots` b ON m.bot_id = b.id SET m.bot_id = b.telegram_bot_id;");
    $pdo->exec("UPDATE `members` m JOIN `users` u ON m.user_id = u.id SET m.user_id = u.telegram_id;");
    $pdo->exec("UPDATE `bot_settings` bs JOIN `bots` b ON bs.bot_id = b.id SET bs.bot_id = b.telegram_bot_id;");
    $pdo->exec("UPDATE `seller_sales_channels` ssc JOIN `users` u ON ssc.user_id = u.id SET ssc.user_id = u.telegram_id;");
    $pdo->exec("UPDATE `seller_sales_channels` ssc JOIN `bots` b ON ssc.bot_id = b.id SET ssc.bot_id = b.telegram_bot_id;");
    $pdo->exec("UPDATE `channel_post_packages` cpp JOIN `bots` b ON cpp.bot_id = b.id SET cpp.bot_id = b.telegram_bot_id;");
    $pdo->exec("UPDATE `balance_transactions` bt JOIN `users` u ON bt.user_id = u.id SET bt.user_id = u.telegram_id;");
    $pdo->exec("UPDATE `sales` s JOIN `users` u_seller ON s.seller_user_id = u_seller.id SET s.seller_user_id = u_seller.telegram_id;");
    $pdo->exec("UPDATE `sales` s JOIN `users` u_buyer ON s.buyer_user_id = u_buyer.id SET s.buyer_user_id = u_buyer.telegram_id;");
    echo "   Data updated successfully." . PHP_EOL;

    // 4. Ubah tipe kolom
    echo "4. Changing column types to BIGINT..." . PHP_EOL;
    $pdo->exec("ALTER TABLE `rel_user_bot` MODIFY `user_id` BIGINT NOT NULL, MODIFY `bot_id` BIGINT NOT NULL;");
    $pdo->exec("ALTER TABLE `messages` MODIFY `user_id` BIGINT NULL, MODIFY `bot_id` BIGINT NOT NULL;"); // User ID can be null
    $pdo->exec("ALTER TABLE `members` MODIFY `user_id` BIGINT NOT NULL;");
    $pdo->exec("ALTER TABLE `bot_settings` MODIFY `bot_id` BIGINT NOT NULL;");
    $pdo->exec("ALTER TABLE `seller_sales_channels` MODIFY `user_id` BIGINT NOT NULL, MODIFY `bot_id` BIGINT NOT NULL;");
    $pdo->exec("ALTER TABLE `channel_post_packages` MODIFY `bot_id` BIGINT NOT NULL;");
    $pdo->exec("ALTER TABLE `balance_transactions` MODIFY `user_id` BIGINT NOT NULL;");
    $pdo->exec("ALTER TABLE `sales` MODIFY `seller_user_id` BIGINT NOT NULL, MODIFY `buyer_user_id` BIGINT NOT NULL;");
    echo "   Column types changed." . PHP_EOL;

    // 5. Ubah Primary Keys
    echo "5. Changing primary keys..." . PHP_EOL;
    // Users
    $pdo->exec("ALTER TABLE `users` DROP PRIMARY KEY, DROP COLUMN `id`;");
    $pdo->exec("ALTER TABLE `users` ADD PRIMARY KEY (`telegram_id`);");
    echo "   Primary key for `users` is now `telegram_id`." . PHP_EOL;
    // Bots
    $pdo->exec("ALTER TABLE `bots` DROP PRIMARY KEY, DROP COLUMN `id`;");
    $pdo->exec("ALTER TABLE `bots` MODIFY `telegram_bot_id` BIGINT NOT NULL;");
    $pdo->exec("ALTER TABLE `bots` ADD PRIMARY KEY (`telegram_bot_id`);");
    echo "   Primary key for `bots` is now `telegram_bot_id`." . PHP_EOL;

    // 6. Re-create Foreign Keys
    echo "6. Re-creating foreign keys..." . PHP_EOL;
    foreach ($constraints_to_rebuild as $table => $constraints) {
        foreach ($constraints as $constraint) {
            $pdo->exec(
                "ALTER TABLE `$table` ADD CONSTRAINT `{$constraint['name']}` " .
                "FOREIGN KEY (`{$constraint['col']}`) REFERENCES `{$constraint['ref_table']}`(`{$constraint['ref_col']}`) ON DELETE CASCADE;"
            );
            echo "   Created FK `{$constraint['name']}` on `$table`." . PHP_EOL;
        }
    }

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
