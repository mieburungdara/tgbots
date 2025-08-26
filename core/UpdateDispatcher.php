<?php

declare(strict_types=1);

require_once __DIR__ . '/App.php';
require_once __DIR__ . '/handlers/HandlerInterface.php';
require_once __DIR__ . '/handlers/MessageHandler.php';
require_once __DIR__ . '/handlers/CallbackQueryHandler.php';
require_once __DIR__ . '/handlers/EditedMessageHandler.php';
require_once __DIR__ . '/handlers/InlineQueryHandler.php';
require_once __DIR__ . '/handlers/ChannelPostHandler.php';
require_once __DIR__ . '/handlers/MediaHandler.php';
require_once __DIR__ . '/database/UserRepository.php';
require_once __DIR__ . '/database/BotRepository.php';

/**
 * Class UpdateDispatcher
 * Menerima pembaruan, menentukan jenisnya, dan mendelegasikannya ke handler yang sesuai.
 */
class UpdateDispatcher
{
    private $pdo;
    private $bot;
    private $update;
    private $update_handler;
    private $telegram_api;
    private $bot_settings;
    private $telegram_bot_id;

    public function __construct(PDO $pdo, array $bot, array $update)
    {
        $this->pdo = $pdo;
        $this->bot = $bot;
        $this->update = $update;

        $bot_repo = new BotRepository($pdo);
        $this->bot_settings = $bot_repo->getBotSettings($bot['id']);

        $this->update_handler = new UpdateHandler($this->bot_settings);

        // Ekstrak dan simpan telegram_bot_id untuk penggunaan konsisten
        $this->telegram_bot_id = (int)explode(':', $bot['token'])[0];
        $this->telegram_api = new TelegramAPI($bot['token'], $pdo, $this->telegram_bot_id);
    }

    public function dispatch()
    {
        $update_type = $this->update_handler->getUpdateType($this->update);
        if ($update_type === null) {
            return; // Abaikan pembaruan yang tidak didukung atau dinonaktifkan
        }

        // Kasus khusus yang tidak memerlukan transaksi atau konteks pengguna penuh
        if ($update_type === 'inline_query') {
            $handler = new InlineQueryHandler();
            // App 'lite' untuk inline query
            $app = new App($this->pdo, $this->telegram_api, $this->bot, $this->bot_settings, [], 0);
            $handler->handle($app, $this->update['inline_query']);
            return;
        }

        // Mulai transaksi untuk semua jenis pembaruan lainnya
        $this->pdo->beginTransaction();
        try {
            $handler = $this->getHandlerForUpdate($update_type);
            if (!$handler) {
                $this->pdo->rollBack();
                return; // Tidak ada handler yang cocok
            }

            $message_context = UpdateHandler::getMessageContext($this->update);
            if (!$message_context) {
                 $this->pdo->rollBack();
                 return;
            }

            // Dapatkan atau buat pengguna jika relevan
            $user_id = $message_context['from']['id'] ?? null;
            $chat_id = $message_context['chat']['id'] ?? 0;
            $current_user = null;

            if ($user_id) {
                $user_repo = new UserRepository($this->pdo, $this->telegram_bot_id);
                $current_user = $user_repo->findOrCreateUser(
                    $user_id,
                    $message_context['from']['first_name'] ?? '',
                    $message_context['from']['username'] ?? null
                );
                if (!$current_user) throw new Exception("Gagal menemukan atau membuat pengguna.");
            }

            // Simpan pesan masuk (kecuali untuk beberapa jenis pembaruan)
            if ($update_type === 'message' || $update_type === 'edited_message' || $update_type === 'callback_query') {
                 $this->logIncomingMessage($message_context, $update_type, $current_user['telegram_id'] ?? null);
            }

            // Buat App container
            $app = new App($this->pdo, $this->telegram_api, $this->bot, $this->bot_settings, $current_user ?? [], $chat_id);

            // Jalankan handler
            $handler->handle($app, $this->update[$update_type]);

            // Handle media saving separately if it's a message
            if ($update_type === 'message' && UpdateHandler::isMediaMessage($this->update['message'])) {
                $media_handler = new MediaHandler();
                $media_handler->handle($app, $this->update['message']);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Lemparkan kembali agar bisa ditangkap oleh blok catch di webhook.php
            throw $e;
        }
    }

    private function getHandlerForUpdate(string $update_type): ?HandlerInterface
    {
        switch ($update_type) {
            case 'message':
                return new MessageHandler();
            case 'edited_message':
                return new EditedMessageHandler();
            case 'callback_query':
                return new CallbackQueryHandler();
            case 'channel_post':
                return new ChannelPostHandler();
            // MediaHandler dipanggil secara terpisah
            default:
                return null;
        }
    }

    private function logIncomingMessage(array $context, string $update_type, ?int $user_id)
    {
        $telegram_message_id = $context['message_id'] ?? 0;
        $chat_id = $context['chat']['id'];
        $chat_type = $context['chat']['type'] ?? 'unknown';
        $text = $context['text'] ?? ($context['caption'] ?? '');
        $timestamp = $context['date'] ?? time();
        $message_date = date('Y-m-d H:i:s', $timestamp);

        $stmt = $this->pdo->prepare(
            "INSERT INTO messages (user_id, bot_id, telegram_message_id, chat_id, chat_type, update_type, text, raw_data, direction, telegram_timestamp)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'incoming', ?)"
        );
        $stmt->execute([
            $user_id,
            $this->telegram_bot_id,
            $telegram_message_id,
            $chat_id,
            $chat_type,
            $update_type,
            $text,
            json_encode($this->update),
            $message_date
        ]);
    }
}
