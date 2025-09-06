<?php

namespace TGBot\Controllers\Admin;



use TGBot\Controllers\BaseController;
use TGBot\Database\TelegramErrorLogRepository;
use PDO;
use Exception;
use PDOException;

class LogController extends BaseController {

    public function app() {
        try {
            $pdo = \get_db_connection();
            $items_per_page = 50;

            $stmt_levels = $pdo->query("SELECT DISTINCT level FROM app_logs ORDER BY level ASC");
            $log_levels = $stmt_levels->fetchAll(PDO::FETCH_COLUMN);

            $selected_level = isset($_GET['level']) && in_array($_GET['level'], $log_levels) ? $_GET['level'] : 'all';

            $message = $_SESSION['flash_message'] ?? null;
            unset($_SESSION['flash_message']);

            $count_sql = "SELECT COUNT(*) FROM app_logs";
            if ($selected_level !== 'all') {
                $count_sql .= " WHERE level = :level";
            }
            $count_stmt = $pdo->prepare($count_sql);
            if ($selected_level !== 'all') {
                $count_stmt->bindParam(':level', $selected_level, PDO::PARAM_STR);
            }
            $count_stmt->execute();
            $total_items = $count_stmt->fetchColumn();

            $total_pages = ceil($total_items / $items_per_page);
            $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $current_page = max(1, min($current_page, $total_pages));
            $offset = ($current_page - 1) * $items_per_page;

            $sql = "SELECT * FROM app_logs";
            if ($selected_level !== 'all') {
                $sql .= " WHERE level = :level";
            }
            $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($sql);
            if ($selected_level !== 'all') {
                $stmt->bindParam(':level', $selected_level, PDO::PARAM_STR);
            }
            $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->view('admin/logs/app', [
                'page_title' => 'Database Log Viewer',
                'logs' => $logs,
                'log_levels' => $log_levels,
                'selected_level' => $selected_level,
                'total_pages' => $total_pages,
                'current_page' => $current_page,
                'message' => $message
            ], 'admin_layout');
        } catch (Exception $e) {
            \app_log('Error in LogController/app: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the app logs.'
            ], 'admin_layout');
        }
    }

    public function clearAppLogs() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /xoradmin/logs');
            exit();
        }

        $pdo = \get_db_connection();
        try {
            $pdo->query("TRUNCATE TABLE app_logs");
            \app_log("Tabel app_logs dibersihkan oleh admin.", 'system');
            $_SESSION['flash_message'] = "Semua log berhasil dibersihkan.";
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "Gagal membersihkan log: " . $e->getMessage();
        }

        header("Location: /xoradmin/logs");
        exit;
    }

    public function media() {
        try {
            $pdo = \get_db_connection();
            $sql = "
                SELECT
                    mf.id, mf.type, mf.file_name, mf.caption, mf.file_size, mf.media_group_id, mf.created_at,
                    u.first_name as user_first_name, u.username as user_username,
                    b.name as bot_name, b.id as bot_id
                FROM media_files mf
                LEFT JOIN users u ON mf.user_id = u.id
                LEFT JOIN messages m ON mf.message_id = m.telegram_message_id
                LEFT JOIN bots b ON m.bot_id = b.id
                WHERE b.id IS NOT NULL
                ORDER BY mf.created_at DESC
                LIMIT 100;
            ";
            $media_logs_flat = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            $grouped_logs = [];
            foreach ($media_logs_flat as $log) {
                $group_key = $log['media_group_id'] ?? 'single_' . $log['id'];
                if (!isset($grouped_logs[$group_key])) {
                    $grouped_logs[$group_key] = [
                        'items' => [],
                        'group_info' => [
                            'user' => $log['user_first_name'] . ($log['user_username'] ? ' (@' . $log['user_username'] . ')' : ''),
                            'bot' => $log['bot_name'],
                            'bot_id' => $log['bot_id'],
                            'time' => $log['created_at'],
                            'media_group_id' => $log['media_group_id']
                        ]
                    ];
                }
                $grouped_logs[$group_key]['items'][] = $log;
            }

            $this->view('admin/logs/media', [
                'page_title' => 'Log Media',
                'grouped_logs' => $grouped_logs
            ], 'admin_layout');
        } catch (Exception $e) {
            \app_log('Error in LogController/media: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the media logs.'
            ], 'admin_layout');
        }
    }

    public function telegram() {
        try {
            $pdo = \get_db_connection();
            $logRepo = new TelegramErrorLogRepository($pdo);

            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = 25;
            $total_records = $logRepo->countAll();
            $total_pages = ceil($total_records / $limit);
            $page = max(1, min($page, $total_pages));
            $offset = ($page - 1) * $limit;

            $logs = $logRepo->findAll($limit, $offset);

            $this->view('admin/logs/telegram', [
                'page_title' => 'Log Kesalahan Telegram',
                'logs' => $logs,
                'total_pages' => $total_pages,
                'page' => $page
            ], 'admin_layout');
        } catch (Exception $e) {
            \app_log('Error in LogController/telegram: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the Telegram error logs.'
            ], 'admin_layout');
        }
    }

    public function publicErrorLog(): void
    {
        try {
            $log_file_path = __DIR__ . '/../../../public/error_log.txt';
            $parsed_logs = [];
            $error_message = null;

            if (file_exists($log_file_path)) {
                $log_content = file_get_contents($log_file_path);
                if ($log_content === false) {
                    $error_message = "Gagal membaca isi file log: " . htmlspecialchars($log_file_path);
                    \app_log('Error reading public/error_log.txt: ' . $log_file_path, 'error');
                } elseif (empty($log_content)) {
                    $error_message = "File log kosong.";
                } else {
                    $lines = explode("\n", $log_content);
                    $current_log_entry = null;

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;

                        // Regex to identify the start of a new log entry
                        // Example: [06-Sep-2025 08:51:22 UTC] PHP Fatal error: ...
                        if (preg_match('/^\[(.*?)\] (PHP (?:Fatal error|Warning|Parse error|Notice|Deprecated|Strict Standards|Recoverable fatal error|Catchable fatal error)): (.*)$/', $line, $matches)) {
                            // If there's a previous entry being built, save it
                            if ($current_log_entry !== null) {
                                $parsed_logs[] = $current_log_entry;
                            }

                            $timestamp_utc_str = $matches[1];
                            $level = $matches[2];
                            $message = $matches[3];

                            // Convert UTC timestamp to local timezone
                            try {
                                $datetime_utc = new \DateTime($timestamp_utc_str, new \DateTimeZone('UTC'));
                                $timezone = new \DateTimeZone('Asia/Singapore'); // Assuming user's timezone is UTC+8
                                $datetime_utc->setTimezone($timezone);
                                $timestamp_local_str = $datetime_utc->format('d-M-Y H:i:s T'); // Format with timezone abbreviation
                            } catch (Exception $e) {
                                \app_log('Error converting timestamp: ' . $e->getMessage(), 'error');
                                $timestamp_local_str = $timestamp_utc_str . ' (Error TZ)'; // Fallback
                            }

                            $current_log_entry = [
                                'timestamp' => $timestamp_local_str,
                                'level' => $level,
                                'message' => $message
                            ];
                        } else {
                            // This line is part of the previous log entry's message (e.g., stack trace)
                            if ($current_log_entry !== null) {
                                $current_log_entry['message'] .= "\n" . $line;
                            } else {
                                // If a line doesn't match the start of a new entry and there's no current entry,
                                // it's an unparsable line at the beginning or a standalone line.
                                // Treat it as a generic unknown entry.
                                $parsed_logs[] = [
                                    'timestamp' => 'N/A',
                                    'level' => 'UNKNOWN',
                                    'message' => $line
                                ];
                            }
                        }
                    }

                    // Add the last log entry if it exists
                    if ($current_log_entry !== null) {
                        $parsed_logs[] = $current_log_entry;
                    }
                }
            } else {
                $error_message = "File log tidak ditemukan: " . htmlspecialchars($log_file_path);
                \app_log('Public error log file not found: ' . $log_file_path, 'warning');
            }

            $this->view('admin/logs/public_error', [
                'page_title' => 'Public Error Log Viewer',
                'parsed_logs' => $parsed_logs,
                'error_message' => $error_message
            ], 'admin_layout');
        } catch (Exception $e) {
            \app_log('Error in LogController/publicErrorLog: ' . $e->getMessage(), 'error');
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the public error log.'
            ], 'admin_layout');
        }
    }
}
