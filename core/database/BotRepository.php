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

/**
 * Class BotRepository
 * @package TGBot\Database
 */
class BotRepository
{
    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * BotRepository constructor.
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all bots.
     *
     * @return array
     */
    public function getAllBots(): array
    {
        $stmt = $this->pdo->query("SELECT id, first_name, username FROM bots ORDER BY first_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a bot by its Telegram ID.
     *
     * @param int $bot_id
     * @return array|false
     */
    public function findBotByTelegramId(int $bot_id)
    {
        // Bot sekarang dicari berdasarkan ID Telegram-nya, yang merupakan Primary Key.
        $stmt = $this->pdo->prepare("SELECT id, token, username, assigned_feature, failure_count, circuit_breaker_open_until FROM bots WHERE id = ?");
        $stmt->execute([$bot_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update the circuit breaker state for a bot.
     *
     * @param int $bot_id
     * @param int $failure_count
     * @param int $circuit_breaker_open_until
     * @return bool
     */
    public function updateCircuitBreakerState(int $bot_id, int $failure_count, int $circuit_breaker_open_until): bool
    {
        $stmt = $this->pdo->prepare("UPDATE bots SET failure_count = ?, circuit_breaker_open_until = ? WHERE id = ?");
        return $stmt->execute([$failure_count, $circuit_breaker_open_until, $bot_id]);
    }

    /**
     * Get bot settings.
     *
     * @param int $bot_id
     * @return array
     */
    public function getBotSettings(int $bot_id): array
    {
        $stmt_settings = $this->pdo->prepare("SELECT setting_key, setting_value FROM bot_settings WHERE bot_id = ?");
        $stmt_settings->execute([$bot_id]);
        $bot_settings_raw = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);

        // Tetapkan pengaturan default jika tidak ada di database
        return [
            'save_text_messages'    => $bot_settings_raw['save_text_messages'] ?? '1',
            'save_media_messages'   => $bot_settings_raw['save_media_messages'] ?? '1',
            'save_callback_queries' => $bot_settings_raw['save_callback_queries'] ?? '0',
            'save_edited_messages'  => $bot_settings_raw['save_edited_messages'] ?? '0',
        ];
    }

    /**
     * Find a bot by its assigned feature.
     *
     * @param string $feature
     * @return array|false
     */
    public function findBotByFeature(string $feature)
    {
        $stmt = $this->pdo->prepare("SELECT username FROM bots WHERE assigned_feature = ? LIMIT 1");
        $stmt->execute([$feature]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find all bots by their assigned feature.
     *
     * @param string $feature
     * @return array
     */
    public function findAllBotsByFeature(string $feature): array
    {
        $stmt = $this->pdo->prepare("SELECT id, username FROM bots WHERE assigned_feature = ? ORDER BY username ASC");
        $stmt->execute([$feature]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a bot by its username.
     *
     * @param string $username
     * @return array|false
     */
    public function findByUsername(string $username)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bots WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
