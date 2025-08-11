# Cara Kerja Sistem Monetisasi Bot Marketplace

Dokumen ini menjelaskan alur kerja dan cara menggunakan sistem monetisasi marketplace media yang telah diimplementasikan pada bot Telegram Anda.

## Konsep Utama

1.  **Paket Media (Media Package):**
    *   Ini adalah "produk" yang dijual. Sebuah paket bisa terdiri dari satu atau beberapa file media (foto/video).
    *   Setiap paket memiliki deskripsi dan harga yang ditetapkan oleh penjual.

2.  **Saldo Internal (Internal Balance):**
    *   Sistem tidak terhubung langsung dengan uang sungguhan untuk transaksi jual-beli. Sebagai gantinya, setiap pengguna memiliki "saldo" di dalam bot.
    *   Pembelian akan mengurangi saldo pembeli dan menambah saldo penjual.
    *   Saat ini, saldo hanya dapat ditambahkan oleh admin untuk tujuan pengujian.

---

## Alur Pengguna

Sistem ini memiliki tiga peran utama: Penjual, Pembeli, dan Admin.

### 1. Alur Penjual (Cara Menjual Media)

Setiap pengguna bisa menjadi penjual. Berikut adalah langkah-langkahnya:

1.  **Mulai Menjual:** Kirim perintah `/sell` ke bot.
    *   Bot akan merespons dan meminta Anda untuk mengirimkan file media.

2.  **Kirim Media:** Kirim satu atau lebih file media (foto atau video).
    *   **Penting:** Jika Anda ingin menjual beberapa file dalam satu paket, kirimkan sebagai **album** (pilih beberapa media sekaligus saat mengirim).
    *   Bot akan mengonfirmasi setiap media yang diterima.

3.  **Selesaikan Pengiriman:** Setelah semua file untuk satu paket terkirim, kirim perintah `/done`.

4.  **Tulis Deskripsi:** Bot akan meminta Anda untuk menulis deskripsi untuk paket media tersebut. Tulis dan kirim deskripsi sebagai pesan biasa.

5.  **Tetapkan Harga:** Setelah deskripsi, bot akan meminta Anda untuk menetapkan harga. Kirim harga dalam bentuk angka saja (misalnya: `50000` untuk Rp 50.000).

6.  **Selesai!** Paket media Anda sekarang telah dibuat dan siap untuk dipromosikan oleh admin.

*Untuk membatalkan proses penjualan kapan saja, kirim perintah `/cancel`.*

### 2. Alur Pembeli (Cara Membeli Media)

1.  **Menemukan Item:** Pembeli akan melihat item yang dijual ketika seorang admin mempromosikannya di sebuah channel. Postingan tersebut akan memiliki tombol "Lihat & Beli di Bot".

2.  **Masuk ke Bot:** Klik tombol tersebut. Anda akan dialihkan ke percakapan pribadi dengan bot, yang akan menampilkan detail item (deskripsi, harga) dan saldo Anda saat ini.

3.  **Membeli:** Jika Anda setuju, klik tombol "Beli Sekarang".
    *   Sistem akan memeriksa saldo Anda.
    *   Jika saldo mencukupi, saldo Anda akan dipotong sesuai harga, dan semua file media dalam paket tersebut akan langsung dikirimkan kepada Anda.
    *   Jika saldo tidak cukup, pembelian akan gagal.

*Anda bisa mengecek sisa saldo Anda kapan saja dengan mengirim perintah `/balance`.*

### 3. Alur Admin (Cara Mengelola Marketplace)

Admin (yang ID Telegram-nya diatur di `SUPER_ADMIN_TELEGRAM_ID` dalam `config.php`) memiliki perintah khusus:

1.  **Menambah Saldo (Untuk Uji Coba):**
    *   **Perintah:** `/dev_addsaldo <user_telegram_id> <jumlah>`
    *   **Contoh:** `/dev_addsaldo 12345678 100000`
    *   **Fungsi:** Menambahkan saldo ke pengguna tertentu. Ini penting untuk memungkinkan pembeli melakukan transaksi pertama mereka.

2.  **Mempromosikan Paket Media:**
    *   **Perintah:** `/feature <package_id> <channel_id>`
    *   **Contoh:** `/feature 17 @namachannelanda`
    *   **Fungsi:** Memposting pratinjau sebuah paket media ke channel yang ditentukan. `package_id` adalah ID yang diterima penjual saat berhasil membuat paket. `channel_id` bisa berupa `@usernamechannel` atau ID numerik channel. Bot harus menjadi admin di channel tersebut.

---
