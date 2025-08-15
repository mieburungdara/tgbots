<?php

class SellerSalesChannelRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Menambah atau memperbarui channel jualan untuk seorang penjual.
     * Jika penjual sudah punya channel, channel ID akan diperbarui dan diaktifkan kembali.
     *
     * @param int $seller_user_id ID internal pengguna (penjual).
     * @param int $channel_id ID channel Telegram.
     * @return bool True jika berhasil.
     */
    public function createOrUpdate(int $seller_user_id, int $channel_id): bool
    {
        // Menggunakan ON DUPLICATE KEY UPDATE yang bergantung pada kunci unik di seller_user_id
        $sql = "INSERT INTO seller_sales_channels (seller_user_id, channel_id, is_active)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE channel_id = VALUES(channel_id), is_active = 1";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$seller_user_id, $channel_id]);
    }

    /**
     * Mencari channel jualan yang aktif milik seorang penjual.
     *
     * @param int $seller_user_id ID internal pengguna (penjual).
     * @return array|false Data channel atau false jika tidak ditemukan/tidak aktif.
     */
    public function findBySellerId(int $seller_user_id)
    {
        $sql = "SELECT * FROM seller_sales_channels WHERE seller_user_id = ? AND is_active = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$seller_user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Menonaktifkan channel jualan milik seorang penjual.
     * Ini adalah soft delete, memungkinkan pendaftaran ulang di masa depan.
     *
     * @param int $seller_user_id ID internal pengguna (penjual).
     * @return bool True jika berhasil.
     */
    public function deactivate(int $seller_user_id): bool
    {
        $sql = "UPDATE seller_sales_channels SET is_active = 0 WHERE seller_user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$seller_user_id]);
    }
}
