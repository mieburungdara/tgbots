<?php

class UserRepository
{
    private $pdo;
    private $bot_id;

    public function __construct(PDO $pdo, int $internal_bot_id)
    {
        $this->pdo = $pdo;
        $this->bot_id = $internal_bot_id;
    }

    /**
     * Cari pengguna berdasarkan ID Telegram, atau buat jika tidak ada.
     * Mengelola relasi dan peran admin.
     *
     * @param int $telegram_user_id
     * @param string $first_name
     * @param string|null $username
     * @return array|false
     */
    public function findOrCreateUser(int $telegram_user_id, string $first_name, ?string $username)
    {
        $current_user = $this->findUserByTelegramId($telegram_user_id);

        if ($current_user) {
            $internal_user_id = $current_user['id'];
        } else {
            // Buat pengguna baru jika tidak ditemukan
            $initial_role = (defined('SUPER_ADMIN_TELEGRAM_ID') && (string)$telegram_user_id === (string)SUPER_ADMIN_TELEGRAM_ID) ? 'admin' : 'user';
            $stmt_insert = $this->pdo->prepare("INSERT INTO users (telegram_id, first_name, username, role) VALUES (?, ?, ?, ?)");
            $stmt_insert->execute([$telegram_user_id, $first_name, $username, $initial_role]);
            $internal_user_id = $this->pdo->lastInsertId();
            app_log("Pengguna baru dibuat: telegram_id: {$telegram_user_id}, user: {$first_name}, peran: {$initial_role}", 'bot');
        }

        // Periksa dan tetapkan peran super admin
        if (defined('SUPER_ADMIN_TELEGRAM_ID') && !empty(SUPER_ADMIN_TELEGRAM_ID) && (string)$telegram_user_id === (string)SUPER_ADMIN_TELEGRAM_ID) {
            if ($current_user && $current_user['role'] !== 'admin') {
                $this->pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$internal_user_id]);
                app_log("Peran admin diberikan kepada super admin: {$telegram_user_id}", 'bot');
            }
        }

        // Pastikan relasi user-bot ada
        $stmt_rel_check = $this->pdo->prepare("SELECT 1 FROM rel_user_bot WHERE user_id = ? AND bot_id = ?");
        $stmt_rel_check->execute([$internal_user_id, $this->bot_id]);
        if ($stmt_rel_check->fetch() === false) {
             $this->pdo->prepare("INSERT INTO rel_user_bot (user_id, bot_id) VALUES (?, ?)")->execute([$internal_user_id, $this->bot_id]);
        }

        // Pastikan entri member ada
        $stmt_member = $this->pdo->prepare("SELECT 1 FROM members WHERE user_id = ?");
        $stmt_member->execute([$internal_user_id]);
        if (!$stmt_member->fetch()) {
            $this->pdo->prepare("INSERT INTO members (user_id) VALUES (?)")->execute([$internal_user_id]);
        }

        // Ambil kembali data pengguna yang terbaru setelah semua operasi
        return $this->findUserByTelegramId($telegram_user_id);
    }

    /**
     * Cari data lengkap pengguna berdasarkan ID Telegram.
     *
     * @param int $telegram_user_id
     * @return array|false
     */
    public function findUserByTelegramId(int $telegram_user_id)
    {
        $stmt_user = $this->pdo->prepare(
            "SELECT u.id, u.telegram_id, u.public_seller_id, u.role, u.balance, r.state, r.state_context
             FROM users u
             LEFT JOIN rel_user_bot r ON u.id = r.user_id AND r.bot_id = ?
             WHERE u.telegram_id = ?"
        );
        $stmt_user->execute([$this->bot_id, $telegram_user_id]);
        return $stmt_user->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Atur state percakapan untuk pengguna.
     *
     * @param int $user_id
     * @param string|null $state
     * @param array|null $context
     */
    public function setUserState(int $user_id, ?string $state, ?array $context = null)
    {
        $stmt = $this->pdo->prepare("UPDATE rel_user_bot SET state = ?, state_context = ? WHERE user_id = ? AND bot_id = ?");
        $stmt->execute([$state, $context ? json_encode($context) : null, $user_id, $this->bot_id]);
    }

    public function getBotId(): int
    {
        return $this->bot_id;
    }

    /**
     * Menetapkan ID publik yang unik untuk seorang penjual.
     *
     * @param int $userId ID internal pengguna.
     * @return string ID publik yang baru dibuat.
     * @throws Exception Jika gagal membuat ID unik setelah beberapa kali percobaan.
     */
    public function setPublicId(int $userId): string
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
            if ($stmt_update->execute([$public_id, $userId])) {
                return $public_id;
            }
        }

        throw new Exception("Gagal menghasilkan ID penjual yang unik.");
    }
}
