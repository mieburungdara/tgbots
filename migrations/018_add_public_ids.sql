-- Menambahkan kolom untuk ID publik penjual dan urutan paket
ALTER TABLE users
ADD COLUMN public_seller_id CHAR(4) NULL UNIQUE AFTER telegram_id,
ADD COLUMN seller_package_sequence INT UNSIGNED NOT NULL DEFAULT 0 AFTER public_seller_id;

-- Menambahkan kolom untuk ID publik paket
ALTER TABLE media_packages
ADD COLUMN public_id VARCHAR(15) NULL UNIQUE AFTER id;
