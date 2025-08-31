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
    // --- TEMPORARY DEBUGGING MODIFICATION ---
    // This will stop execution and print the first captured error to the screen.
    header('Content-Type: text/plain; charset=utf-8');
    die(
        "DEBUG - Error Captured:\n\n" .
        "Level: " . htmlspecialchars($level) . "\n" .
        "Message: " . htmlspecialchars($message) . "\n\n" .
        "Context: " . print_r($context, true)
    );
    // --- END OF TEMPORARY MODIFICATION ---
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
    $stmt = $pdo->query("SELECT id FROM bots ORDER BY created_at ASC LIMIT 1");
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

/**
 * Mendapatkan detail lengkap sebuah bot berdasarkan ID-nya.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @param int $bot_id ID bot.
 * @return array|null Detail bot sebagai array asosiatif, atau null jika tidak ditemukan.
 */
function get_bot_details(PDO $pdo, int $bot_id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM bots WHERE id = ?");
    $stmt->execute([$bot_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Mendapatkan semua bot yang terdaftar di sistem.
 *
 * @param PDO $pdo Objek koneksi PDO.
 * @return array Daftar semua bot.
 */
function get_all_bots(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, first_name, username FROM bots ORDER BY first_name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mendapatkan inisial dari sebuah nama atau judul.
 * Contoh: "John Doe" -> "JD", "Grup Diskusi" -> "GD"
 *
 * @param string $name Nama atau judul.
 * @param int $max_initials Jumlah maksimal inisial yang dihasilkan.
 * @return string Inisial yang dihasilkan.
 */
function get_initials(string $name, int $max_initials = 2): string
{
    $words = explode(' ', trim($name));
    $initials = '';
    $i = 0;
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
            $i++;
        }
        if ($i >= $max_initials) {
            break;
        }
    }
    return $initials;
}

/**
 * Checks if the current navigation link should be active for the new routing system.
 *
 * @param string $path The base path to check (e.g., 'admin/bots').
 * @param string $current_path The current URL path.
 * @param bool $exact If true, matches the path exactly. Otherwise, matches if the current path starts with it.
 * @return bool True if the link should be considered active.
 */
function is_active_nav(string $path, string $current_path, bool $exact = false): bool {
    if ($exact) {
        return $current_path === $path;
    }
    // Check if the current path starts with the given path, and also handles the base case e.g. /admin for /admin/dashboard
    return strpos($current_path, $path) === 0 || ($path === 'admin/dashboard' && $current_path === 'admin');
}
