<?php

namespace TGBot\Database;

use PDO;
use PDOException;
use TGBot\App;

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
            App::getLogger()->error("Error adding private channel: " . $e->getMessage());
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
     * Menghapus channel dari database berdasarkan ID Telegram-nya.
     *
     * @param int $telegram_id ID unik dari channel Telegram.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function deleteByTelegramId(int $telegram_id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM private_channels WHERE channel_id = ?");
        return $stmt->execute([$telegram_id]);
    }

    /**
     * Mendapatkan semua channel pribadi dari database, beserta bot yang terhubung.
     *
     * @return array Daftar semua channel dengan informasi bot terkait.
     */
    public function getAllChannels(): array
    {
        $sql = "
            SELECT
                pc.id,
                pc.channel_id,
                pc.name,
                COUNT(pcb.bot_id) as bot_count,
                GROUP_CONCAT(b.username SEPARATOR ', ') as bot_usernames
            FROM
                private_channels pc
            LEFT JOIN
                private_channel_bots pcb ON pc.id = pcb.private_channel_id
            LEFT JOIN
                bots b ON pcb.bot_id = b.id
            GROUP BY
                pc.id, pc.channel_id, pc.name
            ORDER BY
                pc.id ASC
        ";
        $stmt = $this->pdo->query($sql);
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

    /**
     * Mendapatkan satu channel berdasarkan ID Telegram-nya.
     *
     * @param int $telegram_id
     * @return array|false
     */
    public function findByTelegramId(int $telegram_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM private_channels WHERE channel_id = ?");
        $stmt->execute([$telegram_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Memperbarui data channel yang ada.
     *
     * @param int $id ID internal channel yang akan diperbarui.
     * @param string $newName Nama baru untuk channel.
     * @param int $newChannelId ID Telegram baru untuk channel.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function updateChannel(int $id, string $newName, int $newChannelId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE private_channels SET name = ?, channel_id = ? WHERE id = ?"
        );
        return $stmt->execute([$newName, $newChannelId, $id]);
    }

    /**
     * Mengatur channel tertentu sebagai default.
     *
     * @param int $id ID internal channel yang akan dijadikan default.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function setDefaultChannel(int $id): bool
    {
        // This feature is deprecated as the 'is_default' column has been removed.
        // Returning true to not break the controller, but doing nothing.
        App::getLogger()->warning("Attempted to call deprecated function setDefaultChannel.");
        return true;
        /*
        $this->pdo->beginTransaction();
        try {
            // Reset semua channel agar tidak ada yang default
            $this->pdo->exec("UPDATE private_channels SET is_default = 0");

            // Atur channel yang dipilih sebagai default
            $stmt = $this->pdo->prepare(
                "UPDATE private_channels SET is_default = 1 WHERE id = ?"
            );
            $stmt->execute([$id]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            App::getLogger()->error("Failed to set default channel: " . $e->getMessage());
            return false;
        }
        */
    }

    /**
     * Memperbarui nama channel berdasarkan ID Telegram-nya.
     *
     * @param int $telegram_id ID unik dari channel Telegram.
     * @param string $new_name Nama baru untuk channel.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function updateNameByTelegramId(int $telegram_id, string $new_name): bool
    {
        $stmt = $this->pdo->prepare("UPDATE private_channels SET name = ? WHERE channel_id = ?");
        return $stmt->execute([$new_name, $telegram_id]);
    }
}
