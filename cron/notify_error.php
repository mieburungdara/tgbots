<?php

// Skrip Cron untuk Notifikasi Error ke Telegram
// Jalankan skrip ini setiap menit melalui cron job.
// Contoh: * * * * * /usr/bin/php /path/to/your/project/cron/notify_error.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/helpers.php';

// Muat konfigurasi. Jika tidak ada, skrip akan berhenti.
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    die("File config.php tidak ditemukan.\n");
}

use TGBot\TelegramAPI;
use TGBot\App;

// Inisialisasi logger
$logger = App::getLogger('error_notifier');

// --- Konfigurasi ---
$logFilePath = __DIR__ . '/../logs/error.log';
$stateFilePath = __DIR__ . '/../logs/error_notifier.state';

// --- Validasi Konfigurasi Notifikasi ---
if (!defined('NOTIFICATION_BOT_TOKEN') || !defined('NOTIFICATION_CHAT_ID') || empty(NOTIFICATION_BOT_TOKEN) || empty(NOTIFICATION_CHAT_ID)) {
    $logger->warning("NOTIFICATION_BOT_TOKEN atau NOTIFICATION_CHAT_ID tidak diatur di config.php. Notifikasi dinonaktifkan.");
    // Kita tidak perlu `die()` di sini, agar tidak ada email error dari cron.
    // Cukup catat di log dan keluar secara diam-diam.
    exit;
}

// --- Logika Utama ---
try {
    if (!file_exists($logFilePath)) {
        // Jika file log tidak ada, tidak ada yang perlu dilakukan.
        exit;
    }

    // Dapatkan ukuran terakhir yang tercatat dari file state
    $lastSize = 0;
    if (file_exists($stateFilePath)) {
        $lastSize = (int)file_get_contents($stateFilePath);
    }

    // Dapatkan ukuran file saat ini
    clearstatcache(); // Hapus cache status file untuk mendapatkan ukuran yang akurat
    $currentSize = filesize($logFilePath);

    if ($currentSize > $lastSize) {
        // Ada konten baru di file log
        $fp = fopen($logFilePath, 'r');
        if ($fp) {
            fseek($fp, $lastSize);
            $newContent = fread($fp, $currentSize - $lastSize);
            fclose($fp);

            if (!empty(trim($newContent))) {
                $logger->info("Error baru terdeteksi. Mengirim notifikasi.");

                // Format pesan
                $message = "🚨 **Error Baru Terdeteksi di Sistem** 🚨\n\n";
                $message .= "```\n";
                // Batasi panjang pesan agar tidak melebihi batas Telegram
                $message .= substr(trim($newContent), 0, 3500);
                $message .= "\n```";

                // Kirim notifikasi
                $telegram = new TelegramAPI(NOTIFICATION_BOT_TOKEN, null, null, $logger);
                $response = $telegram->sendMessage(NOTIFICATION_CHAT_ID, $message, 'Markdown');

                if ($response['ok']) {
                    $logger->info("Notifikasi berhasil dikirim.");
                } else {
                    $logger->error("Gagal mengirim notifikasi: " . ($response['description'] ?? 'Unknown error'));
                }
            }
        }
    }

    // Perbarui file state dengan ukuran file yang baru
    file_put_contents($stateFilePath, $currentSize);

} catch (Exception $e) {
    $logger->critical("Terjadi kesalahan kritis pada skrip notifikasi error: " . $e->getMessage());
    // Kirim notifikasi darurat jika mungkin
    try {
        $emergencyMessage = "🔥 **CRITICAL ERROR in error_notifier.php** 🔥\n\n" . $e->getMessage();
        $telegram = new TelegramAPI(NOTIFICATION_BOT_TOKEN);
        $telegram->sendMessage(NOTIFICATION_CHAT_ID, $emergencyMessage, 'Markdown');
    } catch (Exception $ex) {
        // Gagal mengirim notifikasi darurat, tidak ada lagi yang bisa dilakukan.
    }
}

?>