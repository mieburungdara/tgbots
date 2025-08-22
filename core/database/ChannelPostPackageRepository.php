<?php

/**
 * Repositori untuk mengelola hubungan antara post di channel dengan paket konten.
 * Tabel `channel_post_packages` digunakan untuk melacak paket mana yang diiklankan
 * oleh pesan tertentu di sebuah channel.
 */
class ChannelPostPackageRepository
{
    private $pdo;

    /**
     * Membuat instance ChannelPostPackageRepository.
     *
     * @param PDO $pdo Objek koneksi database.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Membuat catatan baru untuk menautkan sebuah post di channel ke sebuah paket.
     *
     * @param int $channel_id ID dari channel.
     * @param int $message_id ID dari pesan di channel.
     * @param int $package_id ID dari paket yang ditautkan.
     * @return bool True jika berhasil, false jika gagal.
     */
    public function create(int $channel_id, int $message_id, int $package_id): bool
    {
        $sql = "INSERT INTO channel_post_packages (channel_id, message_id, package_id) VALUES (?, ?, ?)";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$channel_id, $message_id, $package_id]);
        } catch (PDOException $e) {
            // Log error
            app_log("Error creating channel post package link: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Menemukan detail paket berdasarkan ID channel dan ID pesan.
     * Berguna untuk menemukan paket mana yang terkait dengan forward otomatis dari channel.
     *
     * @param int $channel_id ID dari channel tempat pesan asli berada.
     * @param int $message_id ID dari pesan asli di channel tersebut.
     * @return array|null Data paket sebagai array asosiatif, atau null jika tidak ditemukan.
     */
    public function findByChannelAndMessage(int $channel_id, int $message_id): ?array
    {
        $sql = "SELECT p.* FROM media_packages p
                JOIN channel_post_packages cpp ON p.id = cpp.package_id
                WHERE cpp.channel_id = ? AND cpp.message_id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$channel_id, $message_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}
