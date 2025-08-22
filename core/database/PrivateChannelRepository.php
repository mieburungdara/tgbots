<?php

/**
 * Repositori untuk mengelola channel pribadi yang digunakan sebagai penyimpanan media.
 * Channel-channel ini tidak terkait langsung dengan penjual, melainkan digunakan oleh bot
 * secara umum untuk menyimpan file.
 */
class PrivateChannelRepository
{
    private $pdo;

    /**
     * Membuat instance PrivateChannelRepository.
     *
     * @param PDO $pdo Objek koneksi database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Menambahkan channel baru ke database.
     *
     * @param int $channel_id ID unik dari channel Telegram.
     * @param string $name Nama deskriptif untuk channel.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function addChannel(int $channel_id, string $name): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO private_channels (channel_id, name) VALUES (?, ?)"
            );
            return $stmt->execute([$channel_id, $name]);
        } catch (PDOException $e) {
            // Mungkin sudah ada (unique constraint) atau error lain
            error_log("Error adding private channel: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Menghapus channel dari database berdasarkan ID internalnya.
     *
     * @param int $id ID internal (primary key) dari channel.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function deleteChannel(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM private_channels WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Mendapatkan semua channel pribadi dari database.
     *
     * @return array Daftar semua channel.
     */
    public function getAllChannels(): array
    {
        $stmt = $this->pdo->query("SELECT id, channel_id, name FROM private_channels ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mendapatkan satu channel berdasarkan ID internalnya.
     *
     * @param int $id
     * @return array|false
     */
    public function findById(int $id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM private_channels WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
