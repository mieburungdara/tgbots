<?php

/**
 * Repositori untuk mengelola channel jualan yang didaftarkan oleh penjual.
 * Setiap penjual dapat mendaftarkan satu channel untuk mem-posting pratinjau konten.
 */
class SellerSalesChannelRepository
{
    private $pdo;

    /**
     * Membuat instance SellerSalesChannelRepository.
     *
     * @param PDO $pdo Objek koneksi database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Menambah atau memperbarui channel jualan untuk seorang penjual.
     * Jika penjual sudah punya channel, detailnya akan diperbarui dan diaktifkan kembali.
     *
     * @param int $seller_telegram_id ID Telegram pengguna (penjual).
     * @param int $telegram_bot_id ID Telegram bot yang akan dihubungkan.
     * @param int $channel_id ID channel Telegram.
     * @param int $discussion_group_id ID grup diskusi yang terhubung.
     * @return bool True jika berhasil.
     */
    public function createOrUpdate(int $seller_telegram_id, int $telegram_bot_id, int $channel_id, int $discussion_group_id): bool
    {
        // Menggunakan ON DUPLICATE KEY UPDATE yang bergantung pada kunci unik di seller_user_id
        $sql = "INSERT INTO seller_sales_channels (seller_user_id, bot_id, channel_id, discussion_group_id, is_active)
                VALUES (?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE bot_id = VALUES(bot_id), channel_id = VALUES(channel_id), discussion_group_id = VALUES(discussion_group_id), is_active = 1";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$seller_telegram_id, $telegram_bot_id, $channel_id, $discussion_group_id]);
    }

    /**
     * Mencari channel jualan yang aktif milik seorang penjual.
     *
     * @param int $seller_telegram_id ID Telegram pengguna (penjual).
     * @return array|false Data channel atau false jika tidak ditemukan/tidak aktif.
     */
    public function findBySellerId(int $seller_telegram_id)
    {
        $sql = "SELECT * FROM seller_sales_channels WHERE seller_user_id = ? AND is_active = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$seller_telegram_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Menonaktifkan channel jualan milik seorang penjual.
     * Ini adalah soft delete, memungkinkan pendaftaran ulang di masa depan.
     *
     * @param int $seller_telegram_id ID Telegram pengguna (penjual).
     * @return bool True jika berhasil.
     */
    public function deactivate(int $seller_telegram_id): bool
    {
        $sql = "UPDATE seller_sales_channels SET is_active = 0 WHERE seller_user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$seller_telegram_id]);
    }
}
