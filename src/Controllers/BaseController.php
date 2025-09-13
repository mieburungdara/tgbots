<?php

namespace TGBot\Controllers;

require_once __DIR__ . '/../../core/helpers.php';

use PDO;
use Exception;
use TGBot\Exceptions\BotNotFoundException;
use TGBot\TelegramAPI;

abstract class BaseController extends AppController {

    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // This check protects all admin controllers that extend BaseController.
        var_dump($_SESSION);
        die('--- DEBUGGING SESSION STATE ---');
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header("Location: /xoradmin/login");
            exit();
        }
    }

    /**
     * Mendapatkan instance bot dan API.
     *
     * @param int $bot_id
     * @return TelegramAPI
     * @throws BotNotFoundException
     * @throws Exception
     */
    protected function getBotAndApi(int $bot_id): TelegramAPI
    {
        if (!$bot_id) {
            // Melempar InvalidArgumentException akan lebih semantik di sini,
            // tetapi untuk saat ini kita akan melempar Exception umum agar tidak menambah kompleksitas.
            throw new Exception('ID Bot tidak valid.', 400);
        }
        $pdo = \get_db_connection();
        $stmt = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
        $stmt->execute([$bot_id]);
        $bot = $stmt->fetch();
        if (!$bot) {
            throw new BotNotFoundException("Bot dengan ID {$bot_id} tidak ditemukan.", 404);
        }
        return new TelegramAPI($bot['token']);
    }
}