<?php

/**
 * Migration 010: Merombak sistem peran.
 *
 * - Membuat tabel `roles` dan `user_roles` untuk hubungan many-to-many.
 * - Mengisi `roles` dengan peran default.
 * - Memigrasikan pengguna yang ada dari kolom `users.role` ke tabel `user_roles`.
 * - Menghapus kolom `users.role` yang sudah usang.
 */
function run_migration_031(PDO $pdo): void
{
    echo "Memulai migrasi 031: Merombak sistem peran...\n";

    // Step 1: Create 'roles' table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `roles` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(50) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "  - Tabel 'roles' berhasil dibuat atau sudah ada.\n";

    // Step 2: Create 'user_roles' table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_roles` (
            `user_id` INT(11) NOT NULL,
            `role_id` INT(11) NOT NULL,
            PRIMARY KEY (`user_id`, `role_id`),
            KEY `user_id` (`user_id`),
            KEY `role_id` (`role_id`),
            CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "  - Tabel 'user_roles' berhasil dibuat atau sudah ada.\n";

    // Step 3: Populate 'roles' table with default roles
    $default_roles = ['Admin', 'User', 'VIP', 'Donatur', 'Uploader'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `roles` (`name`) VALUES (?)");
    foreach ($default_roles as $role) {
        $stmt->execute([$role]);
    }
    echo "  - Peran default ('Admin', 'User', 'VIP', 'Donatur', 'Uploader') berhasil dimasukkan.\n";

    // Step 4: Migrate existing users to the new role system
    // Check if the old 'role' column exists before trying to migrate from it
    $check_column_stmt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    if ($check_column_stmt->fetch()) {
        echo "  - Kolom 'role' lama ditemukan. Memulai migrasi data pengguna...\n";

        // Get role IDs. The first column (name) becomes the key, the second (id) becomes the value.
        $roles_stmt = $pdo->query("SELECT name, id FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);
        $admin_role_id = $roles_stmt['Admin'] ?? null;
        $user_role_id = $roles_stmt['User'] ?? null;

        if (!$admin_role_id || !$user_role_id) {
            throw new Exception("Gagal mendapatkan ID peran 'Admin' atau 'User' untuk migrasi.");
        }

        // Get all users with the old role column
        $users_stmt = $pdo->query("SELECT id, role FROM users WHERE role IS NOT NULL AND role != ''");

        $insert_user_role_stmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");

        $migrated_count = 0;
        while ($user = $users_stmt->fetch(PDO::FETCH_ASSOC)) {
            if (strtolower($user['role']) === 'admin') {
                $insert_user_role_stmt->execute([$user['id'], $admin_role_id]);
            } else {
                $insert_user_role_stmt->execute([$user['id'], $user_role_id]);
            }
            $migrated_count++;
        }
        echo "  - {$migrated_count} pengguna yang ada telah dimigrasikan ke sistem peran baru.\n";

        // Step 5: Drop the old 'role' column from 'users' table
        $pdo->exec("ALTER TABLE `users` DROP COLUMN `role`");
        echo "  - Kolom 'role' yang lama telah dihapus dari tabel 'users'.\n";

    } else {
        echo "  - Kolom 'role' yang lama tidak ditemukan di tabel 'users', langkah migrasi data dilewati.\n";
    }

    echo "Migrasi 010 berhasil dijalankan.\n";
}
