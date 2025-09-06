## Alur Fitur `/sell`

1.  **Pengecekan Fitur Bot:**
    *   Memeriksa apakah bot yang digunakan memiliki fitur `sell` yang diizinkan (`$app->bot['assigned_feature'] !== 'sell'`).
    *   Jika tidak, bot akan memberitahu pengguna bahwa perintah `/sell` tidak tersedia di bot ini dan menyarankan bot lain yang memiliki fitur `sell`.

2.  **Pengecekan Reply Media:**
    *   Memeriksa apakah perintah `/sell` digunakan sebagai balasan (reply) terhadap sebuah pesan (`!isset($message['reply_to_message'])`).
    *   Jika tidak, bot akan meminta pengguna untuk me-reply media yang ingin dijual.

3.  **Pengecekan Pendaftaran Penjual:**
    *   Memeriksa apakah pengguna sudah terdaftar sebagai penjual (`empty($app->user['public_seller_id'])`).
    *   Jika belum, bot akan menawarkan pendaftaran sebagai penjual dengan tombol "Ya, Daftar Sekarang" (callback `register_seller`).

4.  **Validasi Media yang Dibalas:**
    *   Memeriksa apakah pesan yang dibalas adalah pesan media yang sudah tersimpan di bot (`media_files` table).
    *   Jika tidak, bot akan menampilkan pesan error "Gagal. Pastikan Anda me-reply pesan media (foto/video) yang sudah tersimpan di bot."

5.  **Pengambilan Informasi Media:**
    *   Mengambil `media_group_id` dan `caption` dari pesan media yang dibalas dari tabel `media_files`.
    *   Jika media adalah bagian dari grup media, caption akan diambil dari salah satu media dalam grup tersebut.

6.  **Pengaturan State Pengguna:**
    *   Mengatur state pengguna menjadi `awaiting_price` (`user_repo->setUserState($app->user['id'], 'awaiting_price', $state_context)`). Ini berarti bot akan menunggu input harga dari pengguna.
    *   `$state_context` menyimpan informasi `message_id` dan `chat_id` dari media yang dibalas.

7.  **Permintaan Harga:**
    *   Bot mengirim pesan kepada pengguna yang menyatakan bahwa media siap dijual dan meminta pengguna untuk memasukkan harga untuk paket tersebut.
    *   Pengguna juga diinformasikan bahwa mereka bisa mengetik `/cancel` untuk membatalkan operasi.

### Logika `handleState` (ketika state `awaiting_price`):

*   Jika pengguna memasukkan `/cancel`, state akan direset dan operasi dibatalkan.
*   Jika pengguna memasukkan harga:
    *   Harga divalidasi sebagai integer positif.
    *   Paket baru dibuat di `media_packages` dengan `post_type` 'sell' dan `thumbnail_media_id` dari media yang dibalas.
    *   Harga paket diperbarui.
    *   `package_id` di-update di tabel `media_files` untuk media yang terkait (baik tunggal maupun grup media).
    *   State pengguna direset.
    *   **Backup Media ke Private Channel:** Media paket akan disalin ke private channel backup yang dikonfigurasi (menggunakan round-robin).
    *   Bot mengirim pesan konfirmasi kepada pengguna dengan ID paket publik dan harga.
