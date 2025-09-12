<?php

/**
 * This file is part of the TGBot package.
 *
 * (c) Zidin Mitra Abadi <zidinmitra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TGBot;

use Exception;
use PDO;
use Throwable;
use TGBot\Handlers\HandlerInterface;
use TGBot\Handlers\MessageHandler;
use TGBot\Handlers\CallbackQueryHandler;
use TGBot\Handlers\EditedMessageHandler;
use TGBot\Handlers\InlineQueryHandler;
use TGBot\Handlers\ChannelPostHandler;
use TGBot\Handlers\MediaHandler;
use TGBot\Handlers\UpdateHandler;
use TGBot\Database\UserRepository;
use TGBot\Database\BotRepository;
use TGBot\App;

/**
 * Class UpdateDispatcher
 * @package TGBot
 */
class UpdateDispatcher
{
    /**
     * @var PDO
     */
    private PDO $pdo;

    /**
     * @var array
     */
    private array $bot;

    /**
     * @var array
     */
    private array $update;

    /**
     * @var UpdateHandler
     */
    private UpdateHandler $update_handler;

    /**
     * @var TelegramAPI
     */
    private TelegramAPI $telegram_api;

    /**
     * @var array
     */
    private array $bot_settings;

    /**
     * UpdateDispatcher constructor.
     *
     * @param PDO $pdo
     * @param array $bot
     * @param array $update
     */
    public function __construct(PDO $pdo, array $bot, array $update, TelegramAPI $telegram_api)
    {
        $this->pdo = $pdo;
        $this->bot = $bot;
        $this->update = $update;
        $this->telegram_api = $telegram_api;

        $bot_repo = new BotRepository($pdo);
        $this->bot_settings = $bot_repo->getBotSettings((int)$bot['id']);

        $this->update_handler = new UpdateHandler($this->bot_settings);
    }

    /**
     * Dispatch the update to the appropriate handler.
     *
     * @return void
     * @throws Throwable
     */
    public function dispatch(): void
    {
        App::getLogger()->info("Memulai dispatch...");
        $update_type = $this->update_handler->getUpdateType($this->update);
        if ($update_type === null) {
            App::getLogger()->info("Jenis update tidak didukung atau dinonaktifkan, proses diabaikan.");
            return; // Abaikan pembaruan yang tidak didukung atau dinonaktifkan
        }
        App::getLogger()->info("Jenis update terdeteksi: {$update_type}");

        // Kasus khusus yang tidak memerlukan transaksi atau konteks pengguna penuh
        if ($update_type === 'inline_query') {
            App::getLogger()->info("Menangani inline_query secara khusus.");
            $handler = new InlineQueryHandler();
            // App 'lite' untuk inline query
            $app = new App($this->pdo, $this->telegram_api, $this->bot, $this->bot_settings, [], 0);
            $handler->handle($app, $this->update['inline_query']);
            App::getLogger()->info("Penanganan inline_query selesai.");
            return;
        }

        // Mulai transaksi untuk semua jenis pembaruan lainnya
        App::getLogger()->info("Memulai transaksi database...");
        $this->pdo->beginTransaction();
        try {
            App::getLogger()->info("Mendapatkan handler untuk jenis update: {$update_type}...");
            $handler = $this->getHandlerForUpdate($update_type);
            if (!$handler) {
                App::getLogger()->warning("Tidak ada handler yang cocok untuk {$update_type}, rollback.");
                $this->pdo->rollBack();
                return; // Tidak ada handler yang cocok
            }
            App::getLogger()->info("Handler ditemukan: " . get_class($handler));

            $message_context = UpdateHandler::getMessageContext($this->update);
            if (!$message_context) {
                 App::getLogger()->warning("Tidak dapat menemukan konteks pesan, rollback.");
                 $this->pdo->rollBack();
                 return;
            }
            App::getLogger()->info("Konteks pesan ditemukan.");

            // Dapatkan atau buat pengguna jika relevan
            $user_id = $message_context['from']['id'] ?? null;
            $chat_id = (int)($message_context['chat']['id'] ?? 0);
            $current_user = null;

            if ($user_id) {
                App::getLogger()->info("Memproses pengguna dengan ID: {$user_id}");
                $user_repo = new UserRepository($this->pdo, (int)$this->bot['id']);
                $current_user = $user_repo->findOrCreateUser(
                    $user_id,
                    $message_context['from']['first_name'] ?? '',
                    $message_context['from']['username'] ?? null
                );
                if (!$current_user) throw new Exception("Gagal menemukan atau membuat pengguna.");
                App::getLogger()->info("Pengguna berhasil diproses, ID internal: {$current_user['id']}");
            }

            // Simpan pesan masuk (kecuali untuk beberapa jenis pembaruan)
            if ($update_type === 'message' || $update_type === 'edited_message' || $update_type === 'callback_query') {
                 App::getLogger()->info("Mencatat pesan masuk...");
                 $this->logIncomingMessage($message_context, $update_type, isset($current_user['id']) ? (int)$current_user['id'] : null);
                 App::getLogger()->info("Pesan masuk berhasil dicatat.");
            }

            // Buat App container
            App::getLogger()->info("Membuat instance App...");
            $app = new App($this->pdo, $this->telegram_api, $this->bot, $this->bot_settings, $current_user ?? [], $chat_id);
            App::getLogger()->info("Instance App berhasil dibuat.");

            // Jalankan handler
            App::getLogger()->info("Menjalankan handler...");
            $handler->handle($app, $this->update[$update_type]);
            App::getLogger()->info("Handler selesai dijalankan.");

            // Handle media saving separately if it's a message
            if ($update_type === 'message' && UpdateHandler::isMediaMessage($this->update['message'])) {
                App::getLogger()->info("Pesan media terdeteksi, menjalankan MediaHandler...");
                $media_handler = new MediaHandler();
                $media_handler->handle($app, $this->update['message']);
                App::getLogger()->info("MediaHandler selesai.");
            }

            App::getLogger()->info("Melakukan commit transaksi database...");
            $this->pdo->commit();
            App::getLogger()->info("Transaksi berhasil di-commit.");

        } catch (Throwable $e) {
            App::getLogger()->error("Dispatcher caught a throwable: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            if ($this->pdo->inTransaction()) {
                App::getLogger()->info("Melakukan rollback transaksi karena ada error.");
                $this->pdo->rollBack();
            }
            // Log the error, but don't re-throw it. This prevents the webhook from failing
            // and causing Telegram to send the same update repeatedly.
            // The specific handler (e.g., BuyCallback) is responsible for notifying the user.
        }
        App::getLogger()->info("Dispatch selesai.");
    }

    /**
     * Get the handler for the update type.
     *
     * @param string $update_type
     * @return HandlerInterface|null
     */
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

    /**
     * Log an incoming message.
     *
     * @param array $context
     * @param string $update_type
     * @param int|null $user_id
     * @return void
     */
    private function logIncomingMessage(array $context, string $update_type, ?int $user_id): void
    {
        $telegram_message_id = $context['message_id'] ?? 0;
        $chat_id = $context['chat']['id'];
        $chat_type = $context['chat']['type'] ?? 'unknown';
        $text = $context['text'] ?? ($context['caption'] ?? '');
        $timestamp = $context['date'] ?? time();
        $message_date = date('Y-m-d H:i:s', $timestamp);

        $stmt = $this->pdo->prepare(
            "INSERT INTO messages (user_id, bot_id, telegram_message_id, chat_id, chat_type, update_type, text, raw_data, direction, telegram_timestamp)\n             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'incoming', ?)"
        );
        $stmt->execute([
            $user_id,
            (int)$this->bot['id'],
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
