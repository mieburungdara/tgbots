<?php

namespace TGBot\Database;

use PDO;
use PDOException;

/**
 * Repositori untuk mengelola log kesalahan yang spesifik dari interaksi dengan API Telegram.
 * Ini membantu dalam melacak dan menganalisis kegagalan panggilan API.
 */
class TelegramErrorLogRepository
{
    private $pdo;

    /**
     * Membuat instance TelegramErrorLogRepository.
     * @param PDO $pdo Objek koneksi database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Menyimpan log kesalahan API Telegram ke database.
     *
     * @param array $data Data log yang akan disimpan. Kunci yang diharapkan:
     * - method (string)
     * - request_data (array)
     * - http_code (int)
     * - error_code (int)
     * - description (string)
     * - status (string, default: 'failed')
     * - retry_after (int, opsional)
     * - chat_id (int, opsional)
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create(array $data): bool
    {
        $sql = "INSERT INTO telegram_error_logs (method, request_data, http_code, error_code, description, status, retry_after, chat_id)
                VALUES (:method, :request_data, :http_code, :error_code, :description, :status, :retry_after, :chat_id)";

        try {
            $stmt = $this->pdo->prepare($sql);

            $stmt->bindValue(':method', $data['method'] ?? null);
            $stmt->bindValue(':request_data', isset($data['request_data']) ? json_encode($data['request_data']) : null);
            $stmt->bindValue(':http_code', $data['http_code'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':error_code', $data['error_code'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':description', $data['description'] ?? null);
            $stmt->bindValue(':status', $data['status'] ?? 'failed');
            $stmt->bindValue(':retry_after', $data['retry_after'] ?? null, PDO::PARAM_INT);
            $stmt->bindValue(':chat_id', $data['chat_id'] ?? null);

            return $stmt->execute();
        } catch (PDOException $e) {
            // Jika terjadi error saat menyimpan ke DB, catat ke log error PHP.
            error_log('Gagal menyimpan log error Telegram ke DB: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mengambil semua log kesalahan dari database.
     *
     * @param int $limit Jumlah log yang akan diambil.
     * @param int $offset Offset untuk paginasi.
     * @return array Daftar log kesalahan.
     */
    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM telegram_error_logs ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Menghitung jumlah total log kesalahan.
     *
     * @return int Jumlah total log.
     */
    public function countAll(): int
    {
        $sql = "SELECT COUNT(*) FROM telegram_error_logs";
        $stmt = $this->pdo->query($sql);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Menghapus log kesalahan Telegram berdasarkan ID.
     *
     * @param int $id ID dari log yang akan dihapus.
     * @return bool True jika berhasil dihapus, false jika gagal.
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM telegram_error_logs WHERE id = :id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Gagal menghapus log error Telegram dari DB: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mengosongkan seluruh tabel log kesalahan Telegram.
     *
     * @return bool True jika berhasil, false jika gagal.
     */
    public function truncate(): bool
    {
        $sql = "TRUNCATE TABLE telegram_error_logs";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Gagal mengosongkan tabel log error Telegram: ' . $e->getMessage());
            return false;
        }
    }
}
