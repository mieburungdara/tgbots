<?php

// Jalankan skrip ini dari command line: php migrations/populate_public_ids.php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/helpers.php';

echo "Memulai proses back-filling ID publik...\n";

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // 1. Back-fill untuk Penjual (users table)
    echo "Memproses penjual...\n";
    $stmt_sellers = $pdo->query("SELECT id, public_seller_id FROM users WHERE public_seller_id IS NULL");
    $sellers = $stmt_sellers->fetchAll(PDO::FETCH_ASSOC);

    $seller_count = 0;
    foreach ($sellers as $seller) {
        $max_retries = 5;
        for ($i = 0; $i < $max_retries; $i++) {
            $public_id = generate_seller_id();
            $stmt_check = $pdo->prepare("SELECT 1 FROM users WHERE public_seller_id = ?");
            $stmt_check->execute([$public_id]);
            if ($stmt_check->fetch()) continue;

            $stmt_update = $pdo->prepare("UPDATE users SET public_seller_id = ? WHERE id = ?");
            if ($stmt_update->execute([$public_id, $seller['id']])) {
                $seller_count++;
                break;
            }
        }
    }
    echo "Selesai. {$seller_count} ID penjual publik baru dibuat.\n";


    // 2. Back-fill untuk Paket (media_packages table)
    echo "Memproses paket konten...\n";
    $stmt_packages = $pdo->query(
        "SELECT mp.id, mp.public_id, u.public_seller_id, u.seller_package_sequence
         FROM media_packages mp
         JOIN users u ON mp.seller_user_id = u.id
         WHERE mp.public_id IS NULL
         ORDER BY mp.seller_user_id, mp.created_at ASC"
    );
    $packages = $stmt_packages->fetchAll(PDO::FETCH_ASSOC);

    $package_count = 0;
    $seller_sequences = [];

    foreach ($packages as $package) {
        $seller_id = $package['seller_user_id'];
        if (!isset($seller_sequences[$seller_id])) {
            $seller_sequences[$seller_id] = $package['seller_package_sequence'];
        }

        $seller_sequences[$seller_id]++;
        $new_sequence = $seller_sequences[$seller_id];

        $public_id = $package['public_seller_id'] . '_' . str_pad($new_sequence, 4, '0', STR_PAD_LEFT);

        $stmt_update = $pdo->prepare("UPDATE media_packages SET public_id = ? WHERE id = ?");
        $stmt_update->execute([$public_id, $package['id']]);
        $package_count++;
    }

    // 3. Update sequence counter di tabel users
    foreach ($seller_sequences as $seller_id => $last_sequence) {
        $stmt_update_seq = $pdo->prepare("UPDATE users SET seller_package_sequence = ? WHERE id = ?");
        $stmt_update_seq->execute([$last_sequence, $seller_id]);
    }

    echo "Selesai. {$package_count} ID paket publik baru dibuat.\n";

    $pdo->commit();
    echo "Proses back-filling berhasil diselesaikan.\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Terjadi error: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
