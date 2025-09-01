<?php

namespace TGBot\Database;

use PDO;
use Exception;

/**
 * Repositori untuk mengelola data pengguna (`users`, `rel_user_bot`).
 * Menangani pembuatan pengguna, pencarian, pengelolaan state, dan peran.
 */
class UserRepository
{
    private PDO $pdo;
    private $bot_id;

    /**
     * Membuat instance UserRepository.
     *
     * @param PDO $pdo Objek koneksi database.
     * @param int $bot_id ID Telegram bot yang sedang berinteraksi dengan pengguna.
     */
    public function __construct(PDO $pdo, int $bot_id)
    {
        $this->pdo = $pdo;
        $this->bot_id = $bot_id;
    }

    /**
     * Mencari pengguna berdasarkan ID Telegram mereka. Jika tidak ditemukan, pengguna baru
     * akan dibuat. Metode ini juga memastikan relasi user-bot dan entri member ada,
     * serta menetapkan peran 'admin' jika ID pengguna cocok dengan SUPER_ADMIN_TELEGRAM_ID.
     *
     * @param int $telegram_user_id ID Telegram pengguna.
     * @param string $first_name Nama depan pengguna.
     * @param string|null $username Username Telegram pengguna (jika ada).
     * @return array|false Data pengguna yang lengkap dan terbaru, atau false jika gagal.
     */
    public function findOrCreateUser(int $telegram_user_id, string $first_name, ?string $username)
    {
        $current_user = $this->findUserByTelegramId($telegram_user_id);

        if (!$current_user) {
            // Pengguna tidak ada, buat baru.
            // Transaksi sekarang dikelola oleh UpdateDispatcher, jadi kita tidak perlu memulainya di sini.
            try {
                // 1. Masukkan ke tabel `users`
                // Gunakan INSERT IGNORE untuk menghindari error jika pengguna sudah ada (misal, dari request konkuren)
                $stmt_insert = $this->pdo->prepare("INSERT IGNORE INTO users (id, first_name, username) VALUES (?, ?, ?)");
                $stmt_insert->execute([$telegram_user_id, $first_name, $username]);

                // 2. Tentukan peran awal
                $is_super_admin = defined('SUPER_ADMIN_TELEGRAM_ID') && (string)$telegram_user_id === (string)SUPER_ADMIN_TELEGRAM_ID;
                $initial_role_name = $is_super_admin ? 'Admin' : 'User';

                // 3. Dapatkan role_id dari nama peran
                $stmt_role = $this->pdo->prepare("SELECT id FROM roles WHERE name = ?");
                $stmt_role->execute([$initial_role_name]);
                $role_id = $stmt_role->fetchColumn();

                // 4. Masukkan ke `user_roles`
                if ($role_id) {
                    $stmt_user_role = $this->pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt_user_role->execute([$telegram_user_id, $role_id]);
                }

                app_log("Pengguna baru dibuat atau diverifikasi: telegram_id: {$telegram_user_id}, user: {$first_name}, peran: {$initial_role_name}", 'bot');
            } catch (Exception $e) {
                // Jangan rollback di sini. Lemparkan kembali agar dispatcher bisa melakukan rollback.
                app_log("Gagal membuat pengguna baru: " . $e->getMessage(), 'error');
                throw $e;
            }
        }

        // Periksa dan berikan peran super admin jika belum ada.
        $is_super_admin = defined('SUPER_ADMIN_TELEGRAM_ID') && !empty(SUPER_ADMIN_TELEGRAM_ID) && (string)$telegram_user_id === (string)SUPER_ADMIN_TELEGRAM_ID;
        if ($is_super_admin) {
            $latest_user_data = $this->findUserByTelegramId($telegram_user_id);
            if (($latest_user_data['role'] ?? null) !== 'Admin') {
                 // Dapatkan role_id untuk 'Admin'
                $stmt_role = $this->pdo->prepare("SELECT id FROM roles WHERE name = 'Admin'");
                $stmt_role->execute();
                $admin_role_id = $stmt_role->fetchColumn();

                if ($admin_role_id) {
                    // Gunakan INSERT IGNORE untuk menghindari error jika relasi sudah ada
                    $stmt_grant = $this->pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt_grant->execute([$telegram_user_id, $admin_role_id]);
                    app_log("Peran Admin diberikan kepada super admin: {$telegram_user_id}", 'bot');
                }
            }
        }

        // Pastikan relasi user-bot ada (gunakan INSERT IGNORE untuk efisiensi)
        $this->pdo->prepare("INSERT IGNORE INTO rel_user_bot (user_id, bot_id) VALUES (?, ?)")
                  ->execute([$telegram_user_id, $this->bot_id]);

        // Entri member tidak lagi diperlukan karena tabelnya digabungkan.

        // Ambil kembali data pengguna yang terbaru setelah semua operasi
        return $this->findUserByTelegramId($telegram_user_id);
    }

    /**
     * Mencari data lengkap pengguna berdasarkan ID Telegram mereka, termasuk data
     * spesifik bot seperti state percakapan.
     *
     * @param int $telegram_user_id ID Telegram pengguna.
     * @return array|false Data pengguna sebagai array asosiatif, atau false jika tidak ditemukan.
     */
    public function findUserByTelegramId(int $telegram_user_id)
    {
        $stmt_user = $this->pdo->prepare(
            "SELECT
                u.id, u.public_seller_id, u.balance,
                r.state, r.state_context,
                roles.name as role
             FROM users u
             LEFT JOIN rel_user_bot r ON u.id = r.user_id AND r.bot_id = ?
             LEFT JOIN user_roles ON u.id = user_roles.user_id
             LEFT JOIN roles ON user_roles.role_id = roles.id
             WHERE u.id = ?
             LIMIT 1"
        );
        $stmt_user->execute([$this->bot_id, $telegram_user_id]);
        return $stmt_user->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mengatur state percakapan untuk seorang pengguna dalam konteks bot saat ini.
     * Berguna untuk alur percakapan multi-langkah, seperti proses penjualan.
     *
     * @param int $telegram_user_id ID Telegram pengguna.
     * @param string|null $state State baru (misal: 'awaiting_price') atau null untuk menghapus state.
     * @param array|null $context Data kontekstual tambahan yang akan disimpan sebagai JSON.
     * @return void
     */
    public function setUserState(int $telegram_user_id, ?string $state, ?array $context = null)
    {
        $stmt = $this->pdo->prepare("UPDATE rel_user_bot SET state = ?, state_context = ? WHERE user_id = ? AND bot_id = ?");
        $stmt->execute([$state, $context ? json_encode($context) : null, $telegram_user_id, $this->bot_id]);
    }

    /**
     * Mendapatkan ID Telegram bot yang terkait dengan instance repositori ini.
     *
     * @return int ID Telegram bot.
     */
    public function getBotId(): int
    {
        return $this->bot_id;
    }

    /**
     * Menetapkan ID publik yang unik untuk seorang pengguna, efektif mendaftarkannya sebagai penjual.
     * Terus mencoba menghasilkan ID unik jika terjadi tabrakan.
     *
     * @param int $telegram_user_id ID Telegram pengguna.
     * @return string ID publik yang baru dibuat dan disimpan.
     * @throws Exception Jika gagal membuat ID unik setelah beberapa kali percobaan.
     */
    public function setPublicId(int $telegram_user_id): string
    {
        $max_retries = 5;
        for ($i = 0; $i < $max_retries; $i++) {
            $public_id = generate_seller_id();

            // Cek apakah ID sudah ada
            $stmt_check = $this->pdo->prepare("SELECT 1 FROM users WHERE public_seller_id = ?");
            $stmt_check->execute([$public_id]);
            if ($stmt_check->fetch()) {
                continue; // Coba lagi jika sudah ada
            }

            // Simpan ID yang unik
            $stmt_update = $this->pdo->prepare("UPDATE users SET public_seller_id = ? WHERE id = ?");
            if ($stmt_update->execute([$public_id, $telegram_user_id])) {
                return $public_id;
            }
        }

        throw new Exception("Gagal menghasilkan ID penjual yang unik.");
    }

    /**
     * Memperbarui status pengguna (misalnya, menjadi 'blocked') berdasarkan ID Telegram mereka.
     * Berguna ketika bot mendeteksi diblokir oleh pengguna.
     *
     * @param int $telegram_user_id ID Telegram pengguna.
     * @param string $status Status baru, harus 'active' atau 'blocked'.
     * @return bool True jika berhasil, false jika gagal atau status tidak valid.
     */
    public function updateUserStatusByTelegramId(int $telegram_user_id, string $status): bool
    {
        // Validasi status untuk keamanan
        if (!in_array($status, ['active', 'blocked'])) {
            return false;
        }

        $sql = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $telegram_user_id]);
    }
}
