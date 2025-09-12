<?php

namespace TGBot\Controllers\Admin;

use TGBot\Controllers\BaseController;
use TGBot\Database\TelegramErrorLogRepository;
use PDO;
use Exception;
use PDOException;
use TGBot\Logger;

class LogController extends BaseController
{
    public function app()
    {
        try {
            $logger = new Logger();
            $pdo = \get_db_connection($logger);
            $itemsPerPage = 50;

            $stmtLevels = $pdo->query("SELECT DISTINCT level FROM app_logs ORDER BY level ASC");
            $logLevels = $stmtLevels->fetchAll(PDO::FETCH_COLUMN);

            $selectedLevel = isset($_GET['level']) && in_array($_GET['level'], $logLevels, true)
                ? $_GET['level']
                : 'all';

            $message = $_SESSION['flash_message'] ?? null;
            unset($_SESSION['flash_message']);

            $countSql = "SELECT COUNT(*) FROM app_logs";
            if ($selectedLevel !== 'all') {
                $countSql .= " WHERE level = :level";
            }

            $countStmt = $pdo->prepare($countSql);
            if ($selectedLevel !== 'all') {
                $countStmt->bindParam(':level', $selectedLevel, PDO::PARAM_STR);
            }
            $countStmt->execute();
            $totalItems = $countStmt->fetchColumn();

            $totalPages = (int) ceil($totalItems / $itemsPerPage);
            $currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $currentPage = max(1, min($currentPage, $totalPages));
            $offset = ($currentPage - 1) * $itemsPerPage;

            $sql = "SELECT * FROM app_logs";
            if ($selectedLevel !== 'all') {
                $sql .= " WHERE level = :level";
            }
            $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            if ($selectedLevel !== 'all') {
                $stmt->bindParam(':level', $selectedLevel, PDO::PARAM_STR);
            }
            $stmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->view(
                'admin/logs/app',
                [
                    'page_title'     => 'Database Log Viewer',
                    'logs'           => $logs,
                    'log_levels'     => $logLevels,
                    'selected_level' => $selectedLevel,
                    'total_pages'    => $totalPages,
                    'current_page'   => $currentPage,
                    'message'        => $message,
                ],
                'admin_layout'
            );
        } catch (Exception $e) {
            $logger->error('Error in LogController/app: ' . $e->getMessage());

            $this->view(
                'admin/error',
                [
                    'page_title'    => 'Error',
                    'error_message' => 'An error occurred while loading the app logs.',
                ],
                'admin_layout'
            );
        }
    }

    public function clearAppLogs()
    {
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

    public function media()
    {
        try {
            $logger = new Logger();
            $pdo = \get_db_connection($logger);

            $sql = "
                SELECT
                    mf.id, mf.type, mf.file_name, mf.caption, mf.file_size, mf.media_group_id, mf.created_at,
                    u.first_name AS user_first_name, u.username AS user_username,
                    b.name AS bot_name, b.id AS bot_id
                FROM media_files mf
                LEFT JOIN users u ON mf.user_id = u.id
                LEFT JOIN messages m ON mf.message_id = m.telegram_message_id
                LEFT JOIN bots b ON m.bot_id = b.id
                WHERE b.id IS NOT NULL
                ORDER BY mf.created_at DESC
                LIMIT 100;
            ";

            $mediaLogsFlat = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            $groupedLogs = [];
            foreach ($mediaLogsFlat as $log) {
                $groupKey = $log['media_group_id'] ?? 'single_' . $log['id'];

                if (!isset($groupedLogs[$groupKey])) {
                    $groupedLogs[$groupKey] = [
                        'items'      => [],
                        'group_info' => [
                            'user'           => $log['user_first_name']
                                . ($log['user_username'] ? ' (@' . $log['user_username'] . ')' : ''),
                            'bot'            => $log['bot_name'],
                            'bot_id'         => $log['bot_id'],
                            'time'           => $log['created_at'],
                            'media_group_id' => $log['media_group_id'],
                        ],
                    ];
                }

                $groupedLogs[$groupKey]['items'][] = $log;
            }

            $this->view(
                'admin/logs/media',
                [
                    'page_title'   => 'Log Media',
                    'grouped_logs' => $groupedLogs,
                ],
                'admin_layout'
            );
        } catch (Exception $e) {
            $logger->error('Error in LogController/media: ' . $e->getMessage());

            $this->view(
                'admin/error',
                [
                    'page_title'    => 'Error',
                    'error_message' => 'An error occurred while loading the media logs.',
                ],
                'admin_layout'
            );
        }
    }

    public function telegram()
    {
        try {
            $logger = new Logger();
            $pdo = \get_db_connection($logger);
            $logRepo = new TelegramErrorLogRepository($pdo);

            $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $limit = 25;

            $totalRecords = $logRepo->countAll();
            $totalPages = (int) ceil($totalRecords / $limit);
            $page = max(1, min($page, $totalPages));
            $offset = ($page - 1) * $limit;

            $logs = $logRepo->findAll($limit, $offset);

            $this->view(
                'admin/logs/telegram',
                [
                    'page_title'   => 'Log Kesalahan Telegram',
                    'logs'         => $logs,
                    'total_pages'  => $totalPages,
                    'page'         => $page,
                ],
                'admin_layout'
            );
        } catch (Exception $e) {
            $logger->error('Error in LogController/telegram: ' . $e->getMessage());

            $this->view(
                'admin/error',
                [
                    'page_title'    => 'Error',
                    'error_message' => 'An error occurred while loading the Telegram error logs.',
                ],
                'admin_layout'
            );
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
                'page_title'      => 'Public Error Log Viewer',
                'error_message'   => $errorMessage,
                'parsed_logs'     => [],
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

            $this->view(
                'admin/error',
                [
                    'page_title'    => 'Error',
                    'error_message' => 'An error occurred while loading the public error log.',
                ],
                'admin_layout'
            );
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

            $regex = '/^\[([^\\]+?)\]\[.*?(PHP (?:Fatal error|Warning|Parse error|Notice|Deprecated|Strict Standards|Recoverable fatal error|Catchable fatal error)): (.*)$/';

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
            $timezone = new \DateTimeZone('Asia/Singapore'); // Assuming user's timezone is UTC+8
            $datetimeUtc->setTimezone($timezone);

            $timestampLocalStr = $datetimeUtc->format('d-M-Y H:i:s');
            $sortableTimestamp = $datetimeUtc->getTimestamp();
        } catch (Exception $e) {
            $logger->error('Error converting timestamp: ' . $e->getMessage());
            $timestampLocalStr = $timestampUtcStr . ' (Error TZ)';
            $sortableTimestamp = 0;
        }

        return [
            'timestamp'          => $timestampLocalStr,
            'level'              => $level,
            'message'            => $message,
            'sortable_timestamp' => $sortableTimestamp,
        ];
    }

    private function appendToCurrentLogEntry(string $line, ?array &$currentLogEntry, array &$parsedLogs): void
    {
        if ($currentLogEntry !== null) {
            $currentLogEntry['message'] .= "\n" . $line;
        } else {
            $parsedLogs[] = [
                'timestamp'          => 'N/A',
                'level'              => 'UNKNOWN',
                'message'            => $line,
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
            $logFilePath = $this->getPublicLogFilePath();

            if (file_exists($logFilePath)) {
                if (file_put_contents($logFilePath, '') !== false) {
                    $_SESSION['flash_message'] = "Log kesalahan publik berhasil dibersihkan.";
                    $logger->info('Log kesalahan publik berhasil dibersihkan oleh admin.');
                } else {
                    $_SESSION['flash_message'] = "Gagal membersihkan isi file log kesalahan publik.";
                    $logger->error('Gagal membersihkan isi public/error_log.txt: ' . $logFilePath);
                }
            } else {
                $_SESSION['flash_message'] = "File log kesalahan publik tidak ditemukan.";
                $logger->warning('File log kesalahan publik tidak ditemukan saat mencoba membersihkan: ' . $logFilePath);
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Terjadi kesalahan saat membersihkan log: " . $e->getMessage();
            $logger->error('Error saat membersihkan log kesalahan publik: ' . $e->getMessage());
        }

        header("Location: /xoradmin/public_error_log");
        exit;
    }
}
