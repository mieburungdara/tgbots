-- Migration to create the balance_transactions table
-- Dijalankan pada 2025-08-23
-- Tabel ini mencatat semua transaksi perubahan saldo yang dilakukan oleh admin.

CREATE TABLE `balance_transactions` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID unik transaksi.',
  `user_id` BIGINT NOT NULL COMMENT 'ID pengguna yang saldonya diubah.',
  `amount` DECIMAL(15, 2) NOT NULL COMMENT 'Jumlah yang ditambahkan (positif) atau dikurangi (negatif).',
  `type` VARCHAR(50) NOT NULL DEFAULT 'admin_adjustment' COMMENT 'Jenis transaksi (misal: admin_adjustment, refund, etc).',
  `description` TEXT NULL COMMENT 'Catatan atau alasan dari admin untuk transaksi ini.',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu transaksi dibuat.',
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_balance_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Mencatat riwayat penyesuaian saldo oleh admin.';
