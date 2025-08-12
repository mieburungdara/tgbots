# Changelog

## [2.4.0] - 2025-08-11

### Ditambahkan
- **Fitur Thumbnail Konten**: Setiap paket konten sekarang memiliki satu media yang ditunjuk sebagai thumbnail.
- **Callback `view_full_`**: Menambahkan handler callback baru untuk tombol "Lihat Selengkapnya", yang memungkinkan pengguna yang berhak untuk melihat semua media dalam sebuah paket.

### Diubah
- **Logika Perintah `/sell`**: Perintah `/sell` sekarang secara otomatis menetapkan media yang di-reply sebagai thumbnail untuk paket konten yang baru dibuat.
- **Logika Perintah `/konten`**: Perintah `/konten` sekarang hanya menampilkan thumbnail pratinjau. Tombol di bawah thumbnail akan berubah secara dinamis: "Beli" untuk pengguna baru, dan "Lihat Selengkapnya" untuk penjual atau pembeli yang sudah ada.

## [2.3.1] - 2025-08-11

### Diubah
- **Pesan Perintah /start**: Memperbarui pesan selamat datang untuk perintah `/start` menjadi lebih informatif dan menarik. Pesan baru ini mencakup daftar perintah utama yang tersedia bagi pengguna, lengkap dengan emoji dan instruksi singkat.

## [2.3.0] - 2025-08-11

### Ditambahkan
- **Tabel `sales`**: Membuat tabel baru `sales` untuk mencatat setiap transaksi yang berhasil. Ini memberikan catatan historis yang jelas tentang siapa membeli apa, kapan, dan dengan harga berapa.
- **Perintah `/konten <ID>`**: Menambahkan perintah baru bagi pengguna untuk mengambil kembali konten yang telah mereka beli atau yang mereka jual. Sistem akan memverifikasi kepemilikan sebelum mengirim konten.

### Diubah
- **Logika Pembelian**: Logika pembelian via tombol `buy_` sekarang juga akan membuat catatan transaksi di tabel `sales` yang baru.

## [2.2.0] - 2025-08-11

### Diubah
- **Alur Perintah `/sell`**: Proses penjualan konten dirombak total menjadi lebih intuitif.
  - Penjual sekarang hanya perlu me-reply media (foto/video/album) dengan perintah `/sell`.
  - Bot akan secara otomatis menggunakan caption dari media tersebut sebagai deskripsi produk.
  - Alur multi-langkah yang lama (meminta media, lalu deskripsi) telah dihapus, membuat proses penjualan lebih cepat dan sederhana.

## [2.1.2] - 2025-08-11

### Diubah
- **Struktur Tabel `media_files`**: Menghapus kolom `file_unique_id` dan *unique constraint*-nya dari tabel `media_files` berdasarkan permintaan pengguna untuk menyederhanakan skema. Penanganan duplikat media sekarang tidak lagi dikelola di level database.

## [2.1.1] - 2025-08-11

### Diperbaiki
- **Penanganan Error Webhook**: Membungkus seluruh logika pemrosesan webhook dalam blok `try-catch` tunggal. Ini memastikan bahwa semua jenis error (termasuk error fatal) ditangkap dengan benar, dicatat ke dalam file log, dan mencegah server merespons dengan `500 Internal Server Error` yang tidak informatif.

## [2.1.0] - 2025-08-10

### Ditambahkan
- **Penerimaan Media**: Bot sekarang dapat menerima berbagai jenis media (foto, video, audio, suara, dokumen, animasi, dan catatan video).
- **Penyimpanan Metadata Media**: Semua metadata dari file media yang diterima (seperti ID file, ukuran, durasi, caption, dll.) sekarang disimpan ke dalam tabel database baru bernama `media_files`.

## [2.0.0] - 2025-08-10

### Diubah (Perubahan Besar / Breaking Change)
- **Struktur Database**: Merombak total skema database untuk mendukung relasi many-to-many antara pengguna dan bot, serta memperbaiki model data untuk pelacakan pesan dan member yang akurat.
  - Tabel `chats` diubah namanya menjadi `users` dan kolomnya disesuaikan untuk informasi pengguna yang lebih lengkap (`last_name`, `language_code`).
  - Tabel `bots` diperkaya dengan kolom `username` dan `first_name`.
  - Tabel `messages` sekarang memiliki kolom `bot_id` untuk menautkan setiap pesan ke bot yang relevan.
  - Tabel `members` diperbarui untuk menautkan ke `users(id)` (sebelumnya `chats(id)`), menyelaraskannya dengan skema baru.

### Ditambahkan
- **Tabel `rel_user_bot`**: Tabel baru untuk mengelola hubungan antara `users` dan `bots`, memungkinkan satu pengguna terhubung ke banyak bot dan sebaliknya. Tabel ini juga mencatat status blokir dan waktu interaksi terakhir.
- **File Migrasi Baru**: Menambahkan skrip migrasi (`migrations/002_...` dan `migrations/003_...`) untuk menerapkan perubahan skema ini pada instalasi yang sudah ada.

## [1.8.0] - 2025-08-10

### Ditambahkan
- Pencatatan log untuk proses pembuatan token login via perintah `/login`. Keberhasilan dan kegagalan (termasuk error database) sekarang dicatat di log `bot` dan `database`.

## [1.7.0] - 2025-08-10

### Ditambahkan
- Pencatatan log untuk setiap upaya login member (berhasil atau gagal) ke dalam file `logs/member.log`. Ini meningkatkan kemampuan untuk memantau dan men-debug proses login member.

## [1.6.0] - 2025-08-10

### Ditambahkan
- Sistem logging terpusat dengan file log terpisah untuk kategori yang berbeda (misalnya, `bot`, `database`, `app`).
- Halaman penampil log baru di `admin/logs.php` untuk melihat, menyaring, dan membersihkan log.
- Tautan "Logs" di navigasi utama panel admin.

### Diubah
- Semua panggilan `error_log` di seluruh aplikasi telah diganti dengan fungsi `app_log` yang baru untuk memastikan semua pesan dicatat secara terpusat.

## [1.5.0] - 2025-08-10

### Diubah
- Perintah `/login` sekarang mengirimkan tombol login dengan URL langsung ke panel member, bukan hanya token teks.
- Halaman login member sekarang dapat secara otomatis memproses token dari URL untuk login yang lebih mulus.
- Token login sekarang dihapus dari database setelah digunakan untuk meningkatkan keamanan.

### Ditambahkan
- Opsi konfigurasi `BASE_URL` di `config.php.example` untuk menentukan URL dasar aplikasi.
- Penanganan error di `webhook.php` jika `BASE_URL` tidak diatur.

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
