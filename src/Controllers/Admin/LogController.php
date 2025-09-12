<?php

namespace TGBot\Controllers\Admin;

use TGBot\Controllers\BaseController;
use TGBot\Database\TelegramErrorLogRepository;
use PDO;
use Exception;
use PDOException;
use TGBot\Logger;

class LogController extends BaseController {

    public function app() {
        try {
            $logger = new Logger();
            $pdo = \get_db_connection($logger);
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
            $logger->error('Error in LogController/app: ' . $e->getMessage());
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

        $logger = new Logger();
        $pdo = \get_db_connection($logger);
        try {
            $pdo->query("TRUNCATE TABLE app_logs");
            $logger->info("Tabel app_logs dibersihkan oleh admin.");
            $_SESSION['flash_message'] = "Semua log berhasil dibersihkan.";
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "Gagal membersihkan log: " . $e->getMessage();
        }

        header("Location: /xoradmin/logs");
        exit;
    }

    public function media() {
        try {
            $logger = new Logger();
            $pdo = \get_db_connection($logger);
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
            $logger->error('Error in LogController/media: ' . $e->getMessage());
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the media logs.'
            ], 'admin_layout');
        }
    }

    public function telegram() {
        try {
            $logger = new Logger();
            $pdo = \get_db_connection($logger);
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
            $logger->error('Error in LogController/telegram: ' . $e->getMessage());
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the Telegram error logs.'
            ], 'admin_layout');
        }
    }

    public function publicErrorLog(): void
    {
        $logger = new Logger();
        try {
            $logFilePath = $this->getPublicLogFilePath();
            $errorMessage = null;
            $logContent = $this->readPublicLogFile($logFilePath, $logger, $errorMessage);

            $isRawView = isset($_GET['raw']) && $_GET['raw'] === 'true';

            $viewData = [
                'page_title' => 'Public Error Log Viewer',
                'error_message' => $errorMessage,
                'parsed_logs' => [],
                'raw_log_content' => null,
            ];

            if ($logContent !== false) {
                if ($isRawView) {
                    $viewData['page_title'] = 'Public Error Log (Raw)';
                    $viewData['raw_log_content'] = htmlspecialchars($logContent);
                } else {
                    $viewData['parsed_logs'] = $this->parseLogContent($logContent, $logger);
                    $this->sortParsedLogs($viewData['parsed_logs']);
                }
            }

            $this->view('admin/logs/public_error', $viewData, 'admin_layout');

        } catch (Exception $e) {
            $logger->error('Error in LogController/publicErrorLog: ' . $e->getMessage());
            $this->view('admin/error', [
                'page_title' => 'Error',
                'error_message' => 'An error occurred while loading the public error log.'
            ], 'admin_layout');
        }
    }

    private function getPublicLogFilePath(): string
    {
        return __DIR__ . '/../../../public/error_log.txt';
    }

    private function readPublicLogFile(string $logFilePath, Logger $logger, ?string &$errorMessage): string|false
    {
        if (!file_exists($logFilePath)) {
            $errorMessage = "File log tidak ditemukan: " . htmlspecialchars($logFilePath);
            $logger->warning('Public error log file not found: ' . $logFilePath);
            return false;
        }

        $logContent = file_get_contents($logFilePath);

        if ($logContent === false) {
            $errorMessage = "Gagal membaca isi file log: " . htmlspecialchars($logFilePath);
            $logger->error('Error reading public/error_log.txt: ' . $logFilePath);
            return false;
        }

        if (empty($logContent)) {
            $errorMessage = "File log kosong.";
            return false;
        }

        return $logContent;
    }

    private function parseLogContent(string $logContent, Logger $logger): array
    {
        $lines = explode("\n", $logContent);
        $parsedLogs = [];
        $currentLogEntry = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $regex = '/^\['.([^\\\]+?)\].*?(PHP (?:Fatal error|Warning|Parse error|Notice|Deprecated|Strict Standards|Recoverable fatal error|Catchable fatal error)): (.*)$/';
            if (preg_match($regex, $line, $matches)) {
                if ($currentLogEntry !== null) {
                    $parsedLogs[] = $currentLogEntry;
                }
                $currentLogEntry = $this->createNewLogEntry($matches, $logger);
            } else {
                $this->appendToCurrentLogEntry($line, $currentLogEntry, $parsedLogs);
            }
        }

        if ($currentLogEntry !== null) {
            $parsedLogs[] = $currentLogEntry;
        }

        return $parsedLogs;
    }

    private function createNewLogEntry(array $matches, Logger $logger): array
    {
        [, $timestampUtcStr, $level, $message] = $matches;

        try {
            $datetimeUtc = new \DateTime($timestampUtcStr, new \DateTimeZone('UTC'));
            $timezone = new \DateTimeZone('Asia/Singapore'); // Assuming user\'s timezone is UTC+8
            $datetimeUtc->setTimezone($timezone);
            $timestampLocalStr = $datetimeUtc->format('d-M-Y H:i:s');
            $sortableTimestamp = $datetimeUtc->getTimestamp();
        } catch (Exception $e) {
            $logger->error('Error converting timestamp: ' . $e->getMessage());
            $timestampLocalStr = $timestampUtcStr . ' (Error TZ)';
            $sortableTimestamp = 0;
        }

        return [
            'timestamp' => $timestampLocalStr,
            'level' => $level,
            'message' => $message,
            'sortable_timestamp' => $sortableTimestamp,
        ];
    }

    private function appendToCurrentLogEntry(string $line, ?array &$currentLogEntry, array &$parsedLogs): void
    {
        if ($currentLogEntry !== null) {
            $currentLogEntry['message'] .= "\n" . $line;
        } else {
            // This handles lines that appear before the first matched log entry
            $parsedLogs[] = [
                'timestamp' => 'N/A',
                'level' => 'UNKNOWN',
                'message' => $line,
                'sortable_timestamp' => 0,
            ];
        }
    }

    private function sortParsedLogs(array &$parsedLogs): void
    {
        usort($parsedLogs, function ($a, $b) {
            return $b['sortable_timestamp'] <=> $a['sortable_timestamp'];
        });
    }


    public function clearPublicErrorLog(): void
    {
        $logger = new Logger();
        try {
            $log_file_path = $this->getPublicLogFilePath();
            if (file_exists($log_file_path)) {
                // Menghapus isi file dengan menulis string kosong
                if (file_put_contents($log_file_path, '') !== false) {
                    $_SESSION['flash_message'] = "Log kesalahan publik berhasil dibersihkan.";
                    $logger->info('Log kesalahan publik berhasil dibersihkan oleh admin.');
                } else {
                    $_SESSION['flash_message'] = "Gagal membersihkan isi file log kesalahan publik.";
                    $logger->error('Gagal membersihkan isi public/error_log.txt: ' . $log_file_path);
                }
            } else {
                $_SESSION['flash_message'] = "File log kesalahan publik tidak ditemukan.";
                $logger->warning('File log kesalahan publik tidak ditemukan saat mencoba membersihkan: ' . $log_file_path);
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Terjadi kesalahan saat membersihkan log: " . $e->getMessage();
            $logger->error('Error saat membersihkan log kesalahan publik: ' . $e->getMessage());
        }

        header("Location: /xoradmin/public_error_log");
        exit;
    }
}
