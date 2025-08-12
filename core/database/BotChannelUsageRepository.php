<?php

require_once __DIR__ . '/PrivateChannelRepository.php';

class BotChannelUsageRepository
{
    private $pdo;
    private $privateChannelRepo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->privateChannelRepo = new PrivateChannelRepository($pdo);
    }

    /**
     * Mendapatkan channel berikutnya untuk digunakan oleh bot tertentu,
     * menggunakan strategi round-robin.
     *
     * @param int $botId ID dari bot.
     * @return array|null Detail channel yang dipilih, atau null jika tidak ada channel tersedia.
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
                "INSERT INTO bot_channel_usage (bot_id, last_used_channel_id) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE last_used_channel_id = VALUES(last_used_channel_id)"
            );
            $stmt->execute([$botId, $nextChannel['id']]);
        }

        return $nextChannel;
    }
}
