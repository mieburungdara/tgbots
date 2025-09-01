<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot\Database;

use PDO;
use TGBot\Database\PrivateChannelRepository;

/**
 * Class BotChannelUsageRepository
 * @package TGBot\Database
 */
class BotChannelUsageRepository
{
    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * @var PrivateChannelRepository
     */
    private PrivateChannelRepository $privateChannelRepo;

    /**
     * BotChannelUsageRepository constructor.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->privateChannelRepo = new PrivateChannelRepository($pdo);
    }

    /**
     * Get the next channel for a bot.
     *
     * @param int $botId
     * @return array|null
     */
    public function getNextChannelForBot(int $botId): ?array
    {
        $allChannels = $this->privateChannelRepo->getAllChannels();
        if (empty($allChannels)) {
            return null; // Tidak ada channel yang terdaftar
        }

        // 1. Dapatkan channel terakhir yang digunakan oleh bot ini
        $stmt = $this->pdo->prepare("SELECT last_used_channel_id FROM bot_channel_usage WHERE bot_id = ?");
        $stmt->execute([$botId]);
        $lastUsedChannelId = $stmt->fetchColumn();

        $nextChannel = null;
        if ($lastUsedChannelId === false) {
            // Bot ini belum pernah menggunakan channel, gunakan yang pertama
            $nextChannel = $allChannels[0];
        } else {
            // Cari index dari channel yang terakhir digunakan
            $lastUsedIndex = -1;
            foreach ($allChannels as $index => $channel) {
                if ($channel['id'] == $lastUsedChannelId) {
                    $lastUsedIndex = $index;
                    break;
                }
            }

            if ($lastUsedIndex === -1) {
                // Channel yang terakhir digunakan mungkin telah dihapus, mulai dari awal
                $nextChannel = $allChannels[0];
            } else {
                // Ambil channel berikutnya dalam daftar (round-robin)
                $nextIndex = ($lastUsedIndex + 1) % count($allChannels);
                $nextChannel = $allChannels[$nextIndex];
            }
        }

        // 2. Perbarui catatan penggunaan channel untuk bot ini
        if ($nextChannel) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO bot_channel_usage (bot_id, last_used_channel_id) VALUES (?, ?)\n                 ON DUPLICATE KEY UPDATE last_used_channel_id = VALUES(last_used_channel_id)"
            );
            $stmt->execute([$botId, $nextChannel['id']]);
        }

        return $nextChannel;
    }
}
