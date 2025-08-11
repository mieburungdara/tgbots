# Changelog

## [1.2.0] - 2025-08-10

### Ditambahkan
- Tombol "Edit" di halaman daftar bot untuk masuk ke halaman manajemen bot.
- Halaman manajemen bot baru (`admin/edit_bot.php`).
- Fitur untuk `Set Webhook`, `Check Webhook`, dan `Delete Webhook` dari panel admin untuk setiap bot.
- Penggunaan AJAX dan modal untuk menampilkan hasil aksi webhook tanpa me-refresh halaman.
- Metode baru (`setWebhook`, `getWebhookInfo`, `deleteWebhook`) di kelas `TelegramAPI`.

### Diubah
- Struktur URL untuk halaman percakapan (`chat.php`) diubah dari `?id=` menjadi `?bot_id=&chat_id=`. Ini membuat URL lebih deskriptif dan memastikan bahwa setiap percakapan dapat diidentifikasi secara unik hanya dengan ID bot dan ID pengguna Telegram.

### Diperbaiki
- URL untuk halaman edit bot dan untuk endpoint webhook sekarang menggunakan ID Bot Telegram asli (misalnya 7715036030) untuk identifikasi yang unik dan dinamis, bukan ID database internal. Ini meningkatkan keandalan dan konsistensi.

## [1.1.0] - 2025-08-10

### Ditambahkan
- Fitur setup database otomatis. Aplikasi sekarang akan secara otomatis membuat tabel database yang diperlukan jika belum ada, sehingga tidak perlu lagi mengimpor `setup.sql` secara manual.
