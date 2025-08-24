<?php

/**
 * Repositori untuk menyimpan dan mengambil data mentah (raw) pembaruan dari Telegram.
 * Berguna untuk keperluan debugging dan analisis.
 */
class RawUpdateRepository
{
    private $pdo;

    /**
     * Membuat instance RawUpdateRepository.
     *
     * @param PDO $pdo Objek koneksi database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Menyimpan payload pembaruan JSON mentah ke dalam database.
     *
     * @param string $payload String JSON yang diterima dari Telegram.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create(string $payload): bool
    {
        $sql = "INSERT INTO raw_updates (payload) VALUES (?)";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$payload]);
        } catch (PDOException $e) {
            // Tidak bisa menggunakan app_log dengan mudah jika errornya adalah koneksi DB itu sendiri
            // Untuk saat ini, kembalikan false saja.
            return false;
        }
    }

    /**
     * Mengambil semua data pembaruan mentah dengan paginasi, diurutkan dari yang terbaru.
     *
     * @param int $limit Jumlah maksimum catatan per halaman.
     * @param int $offset Jumlah catatan yang akan dilewati (untuk paginasi).
     * @return array Sebuah array berisi catatan pembaruan.
     */
    public function findAll(int $limit, int $offset): array
    {
        $sql = "SELECT * FROM raw_updates ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Menghitung jumlah total semua catatan pembaruan mentah.
     *
     * @return int Jumlah total catatan.
     */
    public function countAll(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM raw_updates");
        return (int) $stmt->fetchColumn();
    }
}
