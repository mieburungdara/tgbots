# Panduan Instalasi Lengkap

Dokumen ini menjelaskan cara menginstal dan mengkonfigurasi aplikasi ini di server Anda, dengan fokus pada lingkungan shared hosting.

## Persyaratan Server

Pastikan server Anda memenuhi persyaratan berikut:

-   **PHP**: Versi 7.4 atau lebih baru.
-   **Database**: MySQL atau MariaDB.
-   **Akses**: Kemampuan untuk mengelola file (misalnya via File Manager atau FTP) dan mengelola database (misalnya via phpMyAdmin). Akses ke command line (SSH) sangat membantu tetapi tidak wajib.

---

## Langkah 1: Unggah File Aplikasi

1.  Unduh file aplikasi dalam format ZIP.
2.  Unggah file ZIP ke direktori root web Anda (biasanya `public_html` atau `www`).
3.  Ekstrak file ZIP tersebut. Ini akan membuat sebuah direktori baru yang berisi semua file aplikasi.
4.  (Opsional) Anda bisa memindahkan semua file dari direktori tersebut ke `public_html` jika Anda ingin aplikasi diakses langsung dari domain utama Anda.

---

## Langkah 2: Konfigurasi Aplikasi

Aplikasi ini dikonfigurasi melalui file `config.php`.

1.  Di dalam direktori aplikasi, temukan file bernama `config.php.example`.
2.  Salin (copy) file ini dan ganti namanya menjadi `config.php`.
3.  Buka file `config.php` yang baru Anda buat dan edit isinya. Gunakan `https://example.my.id/` sebagai contoh jika aplikasi Anda ada di root domain.

    ```php
    <?php
    // Salin file ini ke config.php dan isi nilainya.

    // --- KONFIGURASI DATABASE ---
    // Ganti dengan detail koneksi database Anda.
    define('DB_HOST', 'localhost'); // Biasanya 'localhost'
    define('DB_USER', 'user_database_anda'); // Username database Anda
    define('DB_PASS', 'password_database_anda'); // Password database Anda
    define('DB_NAME', 'nama_database_anda'); // Nama database Anda

    // --- KONFIGURASI URL ---
    // URL lengkap ke direktori root aplikasi Anda.
    // Harus diakhiri dengan garis miring (/).
    // Contoh: 'https://example.my.id/' atau 'https://example.my.id/subfolder/'
    define('BASE_URL', 'https://example.my.id/');

    // --- (OPSIONAL) ADMIN SUPER ---
    // ID Telegram numerik Anda. Jika diisi, akun Telegram Anda akan
    // otomatis mendapatkan hak akses admin saat pertama kali menggunakan bot.
    define('SUPER_ADMIN_TELEGRAM_ID', '123456789');

    // --- (OPSIONAL) PASSWORD ADMIN XOR ---
    // Password untuk mengakses panel admin alternatif.
    // Ganti dengan password yang kuat dan unik. Sangat disarankan menggunakan
    // password generator untuk membuat password yang tidak mudah ditebak.
    define('XOR_ADMIN_PASSWORD', 'ganti_dengan_password_yang_sangat_kuat');

    ?>
    ```

4.  Simpan perubahan pada file `config.php`.

---

## Langkah 3: Pengaturan Database

Selanjutnya, Anda perlu membuat database dan mengimpor struktur tabel awal.

1.  **Buat Database**:
    -   Masuk ke panel kontrol hosting Anda (misalnya cPanel).
    -   Buka "MySQL Databases" atau alat serupa.
    -   Buat database baru. Catat nama database, username, dan password.
    -   Tambahkan user ke database tersebut dan berikan semua hak akses (`ALL PRIVILEGES`).

2.  **Impor Skema Awal**:
    -   Buka **phpMyAdmin** dari panel kontrol Anda dan pilih database yang baru saja Anda buat.
    -   Klik tab **"Import"**.
    -   Di bawah "File to import", klik "Choose File" dan pilih file `setup.sql` dari direktori aplikasi Anda.
    -   Klik tombol **"Go"** atau **"Import"** di bagian bawah halaman untuk memulai proses impor. Ini akan membuat semua tabel yang diperlukan oleh aplikasi.

---

## Langkah 4: Menjalankan Migrasi Database

Setelah setup awal, mungkin ada pembaruan skema database yang perlu diterapkan. Ini dilakukan melalui skrip migrasi.

### Cara 1: Menggunakan Command Line (Disarankan)

Jika Anda memiliki akses SSH ke server Anda:

1.  Buka terminal atau klien SSH dan masuk ke server Anda.
2.  Navigasi ke direktori root aplikasi Anda menggunakan perintah `cd`.
    ```bash
    cd /path/to/your/application
    ```
3.  Jalankan perintah berikut:
    ```bash
    php run_migrations_cli.php
    ```
4.  Skrip ini akan secara otomatis memeriksa dan menjalankan file migrasi baru yang ada di dalam direktori `/migrations`. Skrip ini aman untuk dijalankan kapan saja.

### Cara 2: Menggunakan Cron Job (Alternatif)

Jika Anda tidak punya akses SSH, Anda bisa menggunakan fitur Cron Job di cPanel:

1.  Buka "Cron Jobs" di cPanel.
2.  Buat cron job baru.
3.  Atur jadwal agar hanya berjalan satu kali di masa depan (misalnya, 5 menit dari sekarang).
4.  Di kolom "Command", masukkan perintah berikut (pastikan untuk mengganti `/path/to/your/application` dengan path absolut yang benar):
    ```bash
    php /home/username/public_html/folder-aplikasi/run_migrations_cli.php
    ```
5.  Setelah cron job berjalan, Anda bisa menghapusnya.

---

## Langkah 5: Konfigurasi Web Server (Routing)

Agar aplikasi dapat diakses dengan benar melalui browser, Anda perlu mengarahkan semua permintaan ke file `public/index.php`.

### Untuk Apache (Lingkungan Shared Hosting)

Jika Anda menggunakan server web Apache (yang paling umum di shared hosting), Anda bisa menggunakan file `.htaccess`.

1.  Pastikan `mod_rewrite` diaktifkan di server Anda (biasanya sudah aktif).
2.  Buat file baru bernama `.htaccess` di dalam direktori **root** aplikasi Anda (di direktori yang sama dengan `config.php`).
3.  Isi file `.htaccess` dengan kode berikut:

    ```apache
    <IfModule mod_rewrite.c>
        RewriteEngine On

        # Jika aplikasi Anda berada di subdirektori, uncomment dan sesuaikan baris di bawah ini
        # RewriteBase /subfolder/

        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . public/index.php [L,QSA]
    </IfModule>
    ```
    **Catatan**: Aturan ini mengarahkan semua permintaan ke `public/index.php`, sementara flag `[QSA]` (Query String Append) memastikan parameter seperti `?page=2` tetap utuh.

### Untuk Nginx

Jika Anda menggunakan Nginx, tambahkan konfigurasi berikut ke dalam blok `server` Anda:

```nginx
location / {
    try_files $uri $uri/ /public/index.php?$query_string;
}
```

---

## Langkah 6: Menyiapkan Bot Telegram & Webhook

Langkah terakhir adalah mendaftarkan bot Anda ke aplikasi dan menghubungkannya dengan Telegram.

1.  **Dapatkan Token Bot**:
    -   Buka aplikasi Telegram, cari bot bernama `@BotFather`.
    -   Mulai percakapan dan kirim perintah `/newbot`.
    -   Ikuti instruksinya untuk memberi nama dan username pada bot Anda.
    -   BotFather akan memberikan Anda sebuah **token API**. Simpan token ini baik-baik.

2.  **Daftarkan Bot di Aplikasi**:
    -   Buka panel admin aplikasi Anda. Biasanya dapat diakses melalui `https://example.my.id/admin`.
    -   Navigasi ke menu "Bots".
    -   Klik tombol untuk menambahkan bot baru.
    -   Masukkan nama untuk bot Anda (untuk identifikasi di panel) dan tempelkan **token API** yang Anda dapatkan dari BotFather.
    -   Simpan bot tersebut.

3.  **Atur Webhook secara Otomatis**:
    -   Setelah bot disimpan, Anda akan melihatnya dalam daftar.
    -   Di samping nama bot, akan ada tombol untuk "Set Webhook" atau "Atur Webhook".
    -   Klik tombol tersebut. Aplikasi akan secara otomatis mengatur webhook untuk Anda ke URL yang benar, yaitu `https://example.my.id/webhook/<BOT_ID>`.
    -   Aplikasi juga akan menyediakan tombol untuk memeriksa status webhook saat ini. Gunakan ini untuk memastikan webhook berhasil diatur.

---

## Instalasi Selesai!

Aplikasi Anda sekarang seharusnya sudah terinstal sepenuhnya dan siap digunakan. Bot Telegram Anda akan meneruskan pembaruan ke aplikasi, dan Anda dapat mengelolanya melalui panel admin.
