<?php

namespace TGBot\Database;

use PDO;
use PDOException;

/**
 * Repositori untuk mengelola hubungan antara channel pribadi dan bot.
 * Mengelola tabel pivot `private_channel_bots`.
 */
class PrivateChannelBotRepository
{
    private $pdo;

    /**
     * @param PDO $pdo Objek koneksi database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Menambahkan hubungan antara bot dan channel.
     *
     * @param int $private_channel_id
     * @param int $bot_id
     * @return bool
     */
    public function addBotToChannel(int $private_channel_id, int $bot_id): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO private_channel_bots (private_channel_id, bot_id) VALUES (?, ?)"
            );
            return $stmt->execute([$private_channel_id, $bot_id]);
        } catch (PDOException $e) {
            error_log("Error adding bot to channel: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Memverifikasi bot dalam sebuah channel dengan menandai timestamp verifikasi.
     *
     * @param int $private_channel_id
     * @param int $bot_id
     * @return bool
     */
    public function verifyBotInChannel(int $private_channel_id, int $bot_id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE private_channel_bots SET verified_at = CURRENT_TIMESTAMP WHERE private_channel_id = ? AND bot_id = ?"
        );
        return $stmt->execute([$private_channel_id, $bot_id]);
    }

    /**
     * Mendapatkan semua bot yang terhubung dengan sebuah channel.
     *
     * @param int $private_channel_id
     * @return array
     */
    public function getBotsForChannel(int $private_channel_id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.first_name, b.username, pcb.verified_at
            FROM bots b
            JOIN private_channel_bots pcb ON b.id = pcb.bot_id
            WHERE pcb.private_channel_id = ?
            ORDER BY b.first_name ASC
        ");
        $stmt->execute([$private_channel_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Menghapus hubungan antara bot dan channel.
     *
     * @param int $private_channel_id
     * @param int $bot_id
     * @return bool
     */
    public function removeBotFromChannel(int $private_channel_id, int $bot_id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM private_channel_bots WHERE private_channel_id = ? AND bot_id = ?"
        );
        return $stmt->execute([$private_channel_id, $bot_id]);
    }

    /**
     * Memeriksa apakah bot sudah terhubung dengan channel.
     *
     * @param int $private_channel_id
     * @param int $bot_id
     * @return bool
     */
    public function isBotInChannel(int $private_channel_id, int $bot_id): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM private_channel_bots WHERE private_channel_id = ? AND bot_id = ?"
        );
        $stmt->execute([$private_channel_id, $bot_id]);
        return $stmt->fetchColumn() !== false;
    }
}
