# Changelog

## [1.4.0] - 2025-08-10

### Ditambahkan
- Sistem migrasi database untuk pembaruan skema yang aman di `admin/settings.php`.
- Halaman Pengaturan baru (`admin/settings.php`) di panel admin.
- Direktori `migrations/` untuk menyimpan file-file pembaruan database.
- Tautan "Pengaturan" di navigasi utama panel admin.

### Diubah
- Logika pembuatan tabel `members` dipindahkan dari `setup.sql` ke file migrasi pertama. `setup.sql` sekarang hanya berisi skema dasar awal.

## [1.3.0] - 2025-08-10

### Ditambahkan
- Halaman panel member baru di direktori `/member` yang dapat diakses dengan token sekali pakai.
- Perintah `/login` pada bot untuk menghasilkan token login bagi member.
- Pendaftaran member otomatis saat pengguna pertama kali berinteraksi dengan bot.
- Tabel `members` baru di database untuk menyimpan informasi terkait member dan token login.

## [1.2.0] - 2025-08-10

### Ditambahkan
- Tombol "Edit" di halaman daftar bot untuk masuk ke halaman manajemen bot.
- Halaman manajemen bot baru (`admin/edit_bot.php`).
- Fitur untuk `Set Webhook`, `Check Webhook`, dan `Delete Webhook` dari panel admin untuk setiap bot.
- Penggunaan AJAX dan modal untuk menampilkan hasil aksi webhook tanpa me-refresh halaman.
- Metode baru (`setWebhook`, `getWebhookInfo`, `deleteWebhook`) di kelas `TelegramAPI`.

### Diubah
- Struktur URL untuk halaman percakapan (`chat.php`) telah disempurnakan untuk menggunakan ID Bot asli dari Telegram (bagian numerik dari token) sebagai parameter `bot_id`, bukan ID internal database. Perubahan ini membuat URL lebih konsisten dengan bagian lain dari aplikasi dan lebih mudah dibagikan.

### Diperbaiki
- URL untuk halaman edit bot dan untuk endpoint webhook sekarang menggunakan ID Bot Telegram asli (misalnya 7715036030) untuk identifikasi yang unik dan dinamis, bukan ID database internal. Ini meningkatkan keandalan dan konsistensi.

## [1.1.0] - 2025-08-10

### Ditambahkan
- Fitur setup database otomatis. Aplikasi sekarang akan secara otomatis membuat tabel database yang diperlukan jika belum ada, sehingga tidak perlu lagi mengimpor `setup.sql` secara manual.
