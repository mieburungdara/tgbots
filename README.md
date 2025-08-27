# TGBots Marketplace

Platform berbasis PHP untuk membuat marketplace menggunakan bot Telegram. Pengguna dapat menjual konten digital seperti foto, video, dan file lainnya, sementara pembeli dapat dengan mudah membeli dan mengakses konten tersebut melalui antarmuka bot yang intuitif.

## Fitur Utama

*   **Pendaftaran Penjual:** Pengguna dapat dengan mudah mendaftar sebagai penjual melalui bot.
*   **Penjualan Fleksibel:** Mendukung penjualan item tunggal maupun album (media group).
*   **Paket Besar:** Memungkinkan pembuatan paket dengan lebih dari 10 media menggunakan perintah khusus.
*   **Edit Paket:** Penjual dapat menambahkan media baru ke paket yang sudah ada.
*   **Pratinjau Konten:** Pembeli dapat melihat pratinjau konten sebelum membeli.
*   **Navigasi Mudah:** Penampil konten dengan paginasi untuk paket besar.
*   **Panel Admin:** Antarmuka web untuk mengelola pengguna, bot, dan pengaturan lainnya.
*   **Perintah Admin:** Perintah khusus di dalam bot untuk admin, seperti menambah saldo pengguna untuk uji coba.

## Instalasi

Berikut adalah langkah-langkah untuk menginstal dan mengkonfigurasi proyek ini:

1.  **Prasyarat:**
    *   Web server (Apache, Nginx, atau sejenisnya) dengan PHP.
    *   Database MySQL.

2.  **Clone Repositori:**
    ```bash
    git clone <url_repositori>
    cd <nama_direktori_proyek>
    ```

3.  **Konfigurasi Aplikasi:**
    *   Salin file `config.php.example` menjadi `config.php`.
    *   Buka `config.php` dan isi detail koneksi database Anda (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).
    *   Atur `BASE_URL` sesuai dengan URL tempat Anda meng-host aplikasi ini (contoh: `https://domainanda.com/tgbots/`).
    *   (Opsional) Atur `SUPER_ADMIN_TELEGRAM_ID` dengan ID Telegram Anda untuk mendapatkan akses admin secara otomatis.
    *   (Opsional) Ubah `XOR_ADMIN_PASSWORD` untuk mengamankan panel admin `xoradmin.php`.

4.  **Database Migration:**
    *   Jalankan skrip migrasi untuk membuat tabel-tabel yang diperlukan di database Anda. Anda bisa menjalankan ini melalui command line:
        ```bash
        php run_migrations_cli.php
        ```

5.  **Setup Telegram Bot:**
    *   Buat bot baru menggunakan [@BotFather](https://t.me/BotFather) di Telegram untuk mendapatkan token bot Anda.
    *   Atur webhook bot Anda agar menunjuk ke file `webhook.php` di server Anda. Anda bisa melakukannya dengan mengakses URL berikut di browser Anda:
        ```
        https://api.telegram.org/bot<TOKEN_BOT_ANDA>/setWebhook?url=<URL_WEBHOOK_ANDA>
        ```
        Ganti `<TOKEN_BOT_ANDA>` dengan token bot Anda, dan `<URL_WEBHOOK_ANDA>` dengan URL lengkap ke file `webhook.php` (contoh: `https://domainanda.com/tgbots/webhook.php`).

## Penggunaan Bot

### Untuk Penjual

1.  **Mendaftar:** Kirim perintah `/sell` untuk pertama kali dan ikuti instruksi untuk mendaftar sebagai penjual.
2.  **Menjual Item:**
    *   Kirim media (foto/video/album) ke bot.
    *   Balas (reply) media tersebut dengan perintah `/sell`.
    *   Masukkan harga saat diminta.
3.  **Membuat Paket Besar:**
    *   Mulai proses penjualan dengan `/sell` pada media pertama.
    *   Kirim media tambahan, lalu balas media tersebut dengan `/addmedia`.
    *   Setelah semua media ditambahkan, kirimkan harga.
4.  **Mengedit Paket:**
    *   Kirim media baru.
    *   Balas media baru tersebut dengan `/addmedia <ID_PAKET>`.

### Untuk Pembeli & Penjual

*   **Melihat Konten:** Gunakan perintah `/konten <ID_PAKET>` untuk melihat pratinjau dan mengakses konten yang telah dibeli.

## Administrasi

*   **Panel Admin:** Panel admin utama dapat diakses di `/admin`.
*   **Panel Admin Alternatif:** Terdapat juga panel admin sederhana di `xoradmin.php` yang dilindungi oleh password yang diatur di `config.php`.
*   **Perintah Admin di Bot:**
    *   `/dev_addsaldo <user_telegram_id> <jumlah>`: Menambah saldo ke pengguna.
    *   `/feature <package_id> <channel_id>`: Mempromosikan paket ke channel.
