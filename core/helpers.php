<?php

/**
 * Memeriksa apakah tabel-tabel dasar yang diperlukan oleh aplikasi sudah ada.
 * Fungsi ini memeriksa keberadaan tabel 'bots' sebagai indikator.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @return bool True jika tabel ada, false jika tidak.
 */
function check_tables_exist(PDO $pdo) {
    try {
        // Coba jalankan query sederhana ke tabel 'bots'.
        // Jika gagal dengan exception (khususnya kode untuk tabel tidak ditemukan),
        // berarti tabelnya belum dibuat.
        $pdo->query("SELECT 1 FROM `bots` LIMIT 1");
    } catch (PDOException $e) {
        // Kode '42S02' adalah standar SQLSTATE untuk "base table or view not found".
        if ($e->getCode() === '42S02') {
            return false;
        }
        // Lemparkan kembali exception lain jika bukan error "tabel tidak ditemukan".
        throw $e;
    }
    return true;
}

/**
 * Mencatat pesan ke database.
 *
 * @param string $message Pesan yang akan dicatat.
 * @param string $level Tipe log (misal: 'info', 'debug', 'error').
 * @param array|null $context Data kontekstual tambahan dalam bentuk array.
 * @return void
 */
function app_log(string $message, string $level = 'info', ?array $context = null): void {
    try {
        $pdo = get_db_connection();
        if (!$pdo) {
            // Fallback to file logging if DB connection fails
            error_log("DB_LOG_FALLBACK: [$level] $message");
            return;
        }

        $sql = "INSERT INTO app_logs (level, message, context) VALUES (:level, :message, :context)";
        $stmt = $pdo->prepare($sql);

        $context_json = $context ? json_encode($context) : null;

        $stmt->bindParam(':level', $level, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt->bindParam(':context', $context_json, PDO::PARAM_STR);

        $stmt->execute();

    } catch (Throwable $e) {
        // Fallback to error_log if anything goes wrong with DB logging
        error_log("Failed to write to DB log. Error: " . $e->getMessage() . ". Original log message: [$level] $message");
    }
}

/**
 * Menghasilkan string acak dengan panjang tertentu dari karakter yang diberikan.
 *
 * @param int $length Panjang string yang diinginkan.
 * @param string $characters Kumpulan karakter yang akan digunakan.
 * @return string String acak yang dihasilkan.
 */
function generate_random_string(int $length, string $characters): string {
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Menghasilkan ID penjual unik 4 karakter.
 *
 * @return string ID penjual 4 karakter.
 */
function generate_seller_id(): string {
    return generate_random_string(4, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
}

/**
 * Memformat angka menjadi string mata uang Rupiah (IDR).
 *
 * @param float|int|string $number Angka yang akan diformat.
 * @param string $currency Simbol mata uang (default: 'Rp').
 * @return string String mata uang yang diformat.
 */
function format_currency($number, string $currency = 'Rp'): string {
    if (!is_numeric($number)) {
        // Kembalikan nilai asli jika bukan angka, atau bisa juga return default value
        return $number;
    }
    return $currency . ' ' . number_format($number, 0, ',', '.');
}

/**
 * Menghasilkan URL untuk header tabel yang dapat diklik untuk pengurutan.
 * Mempertahankan parameter query string yang ada.
 *
 * @param string $column Nama kolom untuk diurutkan.
 * @param string $current_sort_by Kolom yang saat ini digunakan untuk mengurutkan.
 * @param string $current_order Arah urutan saat ini ('asc' atau 'desc').
 * @param array $existing_params Parameter GET yang sudah ada.
 * @return string URL lengkap untuk tautan pengurutan.
 */
function get_sort_link(string $column, string $current_sort_by, string $current_order, array $existing_params = []): string {
    $new_order = ($column === $current_sort_by && $current_order === 'asc') ? 'desc' : 'asc';
    $arrow = ($column === $current_sort_by) ? ($current_order === 'asc' ? ' &#9650;' : ' &#9660;') : '';

    // Gabungkan parameter baru dengan yang sudah ada
    $query_params = array_merge($existing_params, [
        'sort' => $column,
        'order' => $new_order
    ]);

    return basename($_SERVER['PHP_SELF']) . '?' . http_build_query($query_params) . $arrow;
}

/**
 * Mendapatkan ID bot default dari database.
 * Saat ini mengambil bot pertama yang ditemukan.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @return int|null ID bot atau null jika tidak ada.
 */
function get_default_bot_id(PDO $pdo): ?int
{
    $stmt = $pdo->query("SELECT id FROM bots ORDER BY id ASC LIMIT 1");
    $result = $stmt->fetchColumn();
    return $result ? (int)$result : null;
}

/**
 * Mendapatkan token API untuk bot tertentu.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @param int $bot_id ID bot.
 * @return string|null Token bot atau null jika tidak ditemukan.
 */
function get_bot_token(PDO $pdo, int $bot_id): ?string
{
    $stmt = $pdo->prepare("SELECT token FROM bots WHERE id = ?");
    $stmt->execute([$bot_id]);
    $token = $stmt->fetchColumn();
    return $token ?: null;
}
