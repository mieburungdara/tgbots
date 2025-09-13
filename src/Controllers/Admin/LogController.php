<?php

namespace TGBot\Controllers\Admin;

use TGBot\Controllers\BaseController;
use TGBot\Database\TelegramErrorLogRepository;
use PDO;
use Exception;
use PDOException;
use TGBot\App;

class LogController extends BaseController
{
    public function app()
    {
        try {
            $logger = App::getLogger();
            $pdo = \get_db_connection();
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

        $logger = App::getLogger();
        $pdo = \get_db_connection();

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
            $logger = App::getLogger();
            $pdo = \get_db_connection();

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

    /**
     * @var Logger $logger
     */
    public function telegram()
    {
        try {
            $logger = App::getLogger();
            $pdo = \get_db_connection();
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

    public function deleteTelegramLog(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /xoradmin/telegram_logs');
            exit();
        }

        $logger = App::getLogger();
        $pdo = \get_db_connection();
        $logRepo = new TelegramErrorLogRepository($pdo);

        $logId = $_POST['id'] ?? null;

        if (!$logId || !is_numeric($logId)) {
            $_SESSION['flash_message'] = "ID log tidak valid.";
            header("Location: /xoradmin/telegram_logs");
            exit;
        }

        try {
            $logRepo->delete($logId);
            $logger->info("Log Telegram dengan ID {$logId} berhasil dihapus oleh admin.");
            $_SESSION['flash_message'] = "Log Telegram berhasil dihapus.";
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Gagal menghapus log Telegram: " . $e->getMessage();
            $logger->error("Gagal menghapus log Telegram dengan ID {$logId}: " . $e->getMessage());
        }

        header("Location: /xoradmin/telegram_logs");
        exit;
    }

    public function clearAllTelegramLogs(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /xoradmin/telegram_logs');
            exit();
        }

        $logger = App::getLogger();
        $pdo = \get_db_connection();
        $logRepo = new TelegramErrorLogRepository($pdo);

        try {
            $logRepo->truncate();
            $logger->info("Semua log Telegram berhasil dibersihkan oleh admin.");
            $_SESSION['flash_message'] = "Semua log Telegram berhasil dibersihkan.";
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Gagal membersihkan semua log Telegram: " . $e->getMessage();
            $logger->error("Gagal membersihkan semua log Telegram: " . $e->getMessage());
        }

        header("Location: /xoradmin/telegram_logs");
        exit;
    }

    public function publicErrorLog(): void
    {
        $logger = App::getLogger();

        try {
            $logFilePath = $this->getPublicLogFilePath();
            $errorMessage = null;
            $logContent = $this->readLogFile($logFilePath, $errorMessage);

            $viewData = [
                'page_title'      => 'Public Error Log Viewer',
                'error_message'   => $errorMessage,
                'raw_log_content' => null,
            ];

            if ($logContent !== false) {
                $viewData['raw_log_content'] = htmlspecialchars($logContent);
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

    public function viewFileLog(?string $logFileName = null): void
    {
        $logger = App::getLogger();
        $baseLogPath = __DIR__ . '/../../../logs/';

        try {
            // Selalu dapatkan daftar semua file log untuk ditampilkan di navigasi
            $logFiles = [];
            if (!function_exists('scandir')) {
                throw new Exception('Fungsi scandir() dinonaktifkan di server ini. Tidak dapat menampilkan daftar file log.');
            }
            if (is_dir($baseLogPath)) {
                $files = scandir($baseLogPath);
                if ($files === false) {
                    throw new Exception('Gagal memindai direktori logs. Ini mungkin disebabkan oleh batasan `open_basedir` pada server Anda.');
                }

                foreach ($files as $file) {
                    if (preg_match('/^[a-zA-Z0-9_.-]+\.log$/', $file)) {
                        $logFiles[] = $file;
                    }
                }
                rsort($logFiles); // Tampilkan file terbaru di atas
            }

            // Jika tidak ada nama file log yang dipilih, tampilkan halaman daftar saja
            if ($logFileName === null) {
                $this->view('admin/logs/file_log', [
                    'page_title'      => 'Daftar File Log',
                    'log_files'       => $logFiles,
                    'log_file_name'   => null,
                    'raw_log_content' => null,
                    'total_pages'     => 0,
                    'current_page'    => 1,
                    'lines_per_page'  => 50,
                    'error_message'   => null,
                ], 'admin_layout');
                return; // Selesai
            }

            // --- Jika nama file log DIPILIH ---

            // Validasi nama file untuk mencegah directory traversal
            if (!in_array($logFileName, $logFiles)) {
                throw new Exception("File log tidak valid atau tidak ditemukan.");
            }

            $logFilePath = realpath($baseLogPath . $logFileName);

            // Baca N baris terakhir dari file untuk efisiensi memori
            $linesToTail = 1000;
            $logContentArray = $this->tailFile($logFilePath, $linesToTail);

            if ($logContentArray === false) {
                throw new Exception("Gagal membaca isi file log: " . htmlspecialchars($logFileName));
            }

            // Paginasi pada hasil N baris terakhir
            $totalLines = count($logContentArray);
            $linesPerPage = isset($_GET['lines']) ? (int)$_GET['lines'] : 50;
            $linesPerPage = max(1, min($linesPerPage, 500));

            $totalPages = (int) ceil($totalLines / $linesPerPage);
            $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $currentPage = max(1, min($currentPage, $totalPages));

            $offset = ($currentPage - 1) * $linesPerPage;
            $paginatedLines = array_slice($logContentArray, $offset, $linesPerPage);

            $this->view('admin/logs/file_log', [
                'page_title'      => 'File Log: ' . htmlspecialchars($logFileName),
                'log_files'       => $logFiles, // Tetap kirim daftar file
                'log_file_name'   => $logFileName,
                'raw_log_content' => implode("\n", $paginatedLines),
                'total_pages'     => $totalPages,
                'current_page'    => $currentPage,
                'lines_per_page'  => $linesPerPage,
                'error_message'   => $totalLines >= $linesToTail ? "Menampilkan {$linesToTail} baris terakhir." : null,
            ], 'admin_layout');

        } catch (Exception $e) {
            $logger->error('Error in LogController/viewFileLog: ' . $e->getMessage());
            $this->view('admin/error', [
                'page_title'    => 'Error',
                'error_message' => 'Terjadi kesalahan saat memuat log file: ' . $e->getMessage(),
            ], 'admin_layout');
        }
    }

    /**
     * Efficiently reads the last N lines of a large file.
     *
     * @param string $filepath The path to the file.
     * @param int $lines The number of lines to read from the end.
     * @param int $buffer The buffer size to use when reading chunks.
     * @return array|false An array of lines, or false on failure.
     */
    private function tailFile(string $filepath, int $lines, int $buffer = 4096): array|false
    {
        $f = @fopen($filepath, "rb");
        if ($f === false) {
            return false;
        }

        try {
            fseek($f, -1, SEEK_END);
            if (fread($f, 1) != "\n") {
                $lines -= 1;
            }

            $output = '';
            $chunk = '';

            while (ftell($f) > 0 && $lines >= 0) {
                $seek = min(ftell($f), $buffer);
                fseek($f, -$seek, SEEK_CUR);
                $chunk = fread($f, $seek);
                $output = $chunk . $output;
                fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
                $lines -= substr_count($chunk, "\n");
            }

            while ($lines++ < 0) {
                $output = substr($output, strpos($output, "\n") + 1);
            }
        } finally {
            fclose($f);
        }
        
        // Memecah menjadi baris dan membalik urutannya agar kronologis
        $result = preg_split('/\R/', trim($output));
        return array_reverse($result);
    }

    

    private function getPublicLogFilePath(): string
    {
        return __DIR__ . '/../../../public/error_log.txt';
    }

    private function readLogFile(string $logFilePath, ?string &$errorMessage): string|false
    {
        $logger = App::getLogger();
        if (!file_exists($logFilePath)) {
            $errorMessage = "File log tidak ditemukan: " . htmlspecialchars($logFilePath);
            $logger->warning('Log file not found: ' . $logFilePath);
            return false;
        }

        $logContent = file_get_contents($logFilePath);

        if ($logContent === false) {
            $errorMessage = "Gagal membaca isi file log: " . htmlspecialchars($logFilePath);
            $logger->error('Error reading log file: ' . $logFilePath);
            return false;
        }

        if (empty($logContent)) {
            $errorMessage = "File log kosong.";
            return false;
        }

        return $logContent;
    }

    public function clearPublicErrorLog(): void
    {
        $logger = App::getLogger();

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

    public function clearFileLog(string $logFileName): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /xoradmin/file_logs/' . $logFileName);
            exit();
        }

        $logger = App::getLogger();

        try {
            // Validate log file name to prevent directory traversal
            if (!preg_match('/^[a-zA-Z0-9_.-]+\.log$/', $logFileName)) {
                throw new Exception("Nama file log tidak valid.");
            }

            $baseLogPath = __DIR__ . '/../../../logs/';
            $logFilePath = realpath($baseLogPath . $logFileName);

            // Ensure the resolved path is within the intended logs directory
            if ($logFilePath === false || strpos($logFilePath, realpath($baseLogPath)) !== 0) {
                throw new Exception("Akses ke file log tidak diizinkan.");
            }

            if (file_exists($logFilePath)) {
                if (file_put_contents($logFilePath, '') !== false) {
                    $_SESSION['flash_message'] = "Log file '{$logFileName}' berhasil dibersihkan.";
                    $logger->info("Log file '{$logFileName}' berhasil dibersihkan oleh admin.");
                } else {
                    throw new Exception("Gagal membersihkan isi file log '{$logFileName}'.");
                }
            } else {
                throw new Exception("File log '{$logFileName}' tidak ditemukan.");
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Gagal membersihkan log file: " . $e->getMessage();
            $logger->error("Gagal membersihkan log file '{$logFileName}': " . $e->getMessage());
        }

        header("Location: /xoradmin/file_logs/" . $logFileName);
        exit;
    }
}
