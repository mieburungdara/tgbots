# Changelog

## [4.3.0] - 2025-08-25

### Diubah (Refactoring Besar)
- **Arsitektur Webhook Didesain Ulang**: File `webhook.php` yang monolitik telah direfaktor secara besar-besaran menjadi arsitektur berbasis *Dispatcher* yang lebih bersih, modular, dan mudah dipelihara.
  - **`UpdateDispatcher`**: Memperkenalkan kelas `UpdateDispatcher` baru yang sekarang berfungsi sebagai otak utama, bertanggung jawab untuk menerima pembaruan, menentukan jenisnya, dan mendelegasikannya ke handler yang sesuai.
  - **Kontainer `App`**: Membuat kelas `App` baru yang berfungsi sebagai wadah dependensi sederhana untuk menampung objek-objek bersama (seperti koneksi PDO, instance TelegramAPI, dan data pengguna), menyederhanakan cara dependensi diteruskan ke handler.
  - **`HandlerInterface`**: Menstandardisasi semua handler (`MessageHandler`, `CallbackQueryHandler`, dll.) untuk mengimplementasikan `HandlerInterface` umum, memastikan mereka memiliki metode `handle()` yang konsisten.
  - **`webhook.php` yang Ramping**: `webhook.php` sekarang hanya bertanggung jawab untuk validasi awal, inisialisasi, dan memanggil dispatcher. Semua logika bisnis, penanganan transaksi, dan routing telah dipindahkan ke kelas-kelas yang sesuai.

### Peningkatan
- **Sentralisasi Logika**: Logika yang sebelumnya tersebar di `webhook.php` (seperti menyimpan pesan masuk, menangani state machine, atau kasus-kasus khusus untuk `channel_post`) sekarang telah dipindahkan ke dalam handler masing-masing, membuat setiap kelas lebih mandiri.

## [4.2.23] - 2025-08-23

### Peningkatan
- **Refactoring API Log Transaksi**: Mengubah semua endpoint API log transaksi (`get_balance_log.php`, `get_sales_log.php`, `get_purchases_log.php`) untuk menggunakan `telegram_id` sebagai parameter identifikasi pengguna, bukan `id` internal database.
  - **Alasan**: Meningkatkan konsistensi dan keamanan dengan menggunakan ID publik yang tidak mengekspos struktur database internal.
  - **Implementasi**: Backend API dan panggilan AJAX di frontend pada halaman `admin/balance.php` telah disesuaikan untuk menggunakan `telegram_id`.

### Diperbaiki
- **Fatal Error di Halaman Pengguna**: Memperbaiki error `SQLSTATE[HY093]: Invalid parameter number` yang terjadi saat menggunakan fungsi pencarian di halaman `admin/users.php`.
  - **Penyebab**: Penggunaan placeholder bernama yang sama untuk beberapa kondisi `WHERE` dan `LIKE` dalam query pencarian tidak didukung secara konsisten oleh semua driver PDO.
  - **Solusi**: Mengubah query untuk menggunakan placeholder bernama yang unik untuk setiap kondisi, memastikan query berjalan dengan benar.
- **Fatal Error `Cannot redeclare get_sort_link()`**: Memperbaiki error fatal yang terjadi di halaman `admin/users.php`.
  - **Penyebab**: Fungsi `get_sort_link()` didefinisikan secara lokal di `admin/users.php` dan juga di `core/helpers.php`, menyebabkan konflik saat kedua file di-include.
  - **Solusi**: Menghapus definisi fungsi yang duplikat dari `admin/users.php` dan memastikan halaman tersebut menggunakan versi global dari `core/helpers.php`.

## [4.2.22] - 2025-08-23

### Fitur
- **Log Transaksi Interaktif**: Menambahkan modal pop-up interaktif di halaman `admin/balance.php` untuk melihat riwayat transaksi secara detail.
  - **Riwayat Saldo**: Mengklik jumlah "Saldo Saat Ini" akan menampilkan riwayat penyesuaian saldo oleh admin, lengkap dengan deskripsi.
  - **Riwayat Pemasukan**: Mengklik "Total Pemasukan" akan menampilkan daftar konten yang telah dijual oleh pengguna.
  - **Riwayat Pengeluaran**: Mengklik "Total Pengeluaran" akan menampilkan daftar konten yang telah dibeli oleh pengguna.
  - **Implementasi AJAX**: Data untuk modal ini diambil secara dinamis menggunakan API endpoint baru di `admin/api/`, sehingga tidak memperlambat waktu muat halaman awal.

## [4.2.21] - 2025-08-23

### Peningkatan
- **Sorting di Halaman Saldo**: Menambahkan fungsionalitas pengurutan pada tabel di halaman `admin/balance.php`.
  - Admin sekarang dapat mengurutkan daftar pengguna berdasarkan `Saldo Saat Ini`, `Total Pemasukan`, dan `Total Pengeluaran` dengan mengklik header kolom.
  - **Dukungan Kolom Terhitung**: Logika pengurutan diimplementasikan untuk bekerja dengan benar pada kolom yang nilainya dihitung melalui subquery (aliased columns).
  - **Helper Terpusat**: Membuat fungsi `get_sort_link()` yang dapat digunakan kembali di `core/helpers.php` untuk menyederhanakan pembuatan tautan pengurutan di seluruh panel admin.

## [4.2.20] - 2025-08-23

### Peningkatan
- **Alur Kerja Penyesuaian Saldo**: Mengubah alur kerja pada halaman Manajemen Saldo (`admin/balance.php`) untuk meningkatkan efisiensi.
  - **Formulir Berbasis Modal**: Mengganti form di bagian atas halaman dengan tombol "Ubah Saldo" di setiap baris pengguna. Tombol ini akan membuka jendela modal (pop-up) untuk memasukkan jumlah penyesuaian saldo, membuat proses lebih cepat dan lebih terkontekstual.

### Diperbaiki
- **Fatal Error di Halaman Saldo**: Memperbaiki error `SQLSTATE[HY093]: Invalid parameter number` yang terjadi saat menggunakan fungsi pencarian di halaman `admin/balance.php`.
  - **Penyebab**: Penggunaan placeholder bernama yang sama (`:search`) untuk beberapa kondisi `LIKE` dalam query pencarian tidak didukung secara konsisten oleh semua driver PDO.
  - **Solusi**: Mengubah query untuk menggunakan placeholder bernama yang unik (`:search1`, `:search2`, `:search3`) untuk setiap kondisi `LIKE`, memastikan query berjalan dengan benar.

## [4.2.19] - 2025-08-23

### Fitur
- **Halaman Manajemen Saldo**: Menambahkan halaman baru (`admin/balance.php`) yang didedikasikan untuk manajemen saldo pengguna.
  - **Penyesuaian Saldo**: Admin dapat menambah atau mengurangi saldo pengguna secara manual melalui form. Setiap transaksi dicatat dalam tabel `balance_transactions` yang baru.
  - **Tabel Ringkasan**: Menampilkan tabel paginasi dari semua pengguna yang menunjukkan saldo mereka saat ini, total pemasukan (dari penjualan konten), dan total pengeluaran (dari pembelian konten).
  - **Navigasi**: Menambahkan tautan "Manajemen Saldo" ke sidebar utama admin untuk akses mudah.

### Diperbaiki
- **Error Migrasi `balance_transactions`**: Memperbaiki error `errno: 150 "Foreign key constraint is incorrectly formed"` saat menjalankan migrasi untuk membuat tabel `balance_transactions`.
  - **Penyebab**: Tipe data kolom `user_id` di tabel baru (`BIGINT`) tidak cocok dengan tipe data kolom `id` di tabel `users` (`INT(11)`).
  - **Solusi**: Menyamakan tipe data `user_id` di file migrasi menjadi `INT(11)` agar sesuai dengan kolom yang direferensikan.
- **Fatal Error di Halaman Saldo**: Memperbaiki error `SQLSTATE[42S22]: Unknown column 'seller_id'` yang terjadi saat memuat halaman `admin/balance.php`.
  - **Penyebab**: Subquery untuk menghitung total pemasukan dan pengeluaran menggunakan nama kolom yang salah (`seller_id`, `buyer_id`).
  - **Solusi**: Mengubah nama kolom di dalam query menjadi `seller_user_id` dan `buyer_user_id` agar cocok dengan skema tabel `sales`.
- **Fatal Error `format_currency()`**: Memperbaiki error `Call to undefined function format_currency()` di halaman `admin/balance.php`.
  - **Penyebab**: Fungsi helper untuk memformat mata uang dipanggil tetapi belum pernah didefinisikan.
  - **Solusi**: Menambahkan fungsi `format_currency()` baru ke dalam `core/helpers.php` agar tersedia secara global.

## [4.2.18] - 2025-08-23

### Peningkatan
- **Peningkatan Halaman Manajemen Pengguna**: Merombak halaman `admin/users.php` untuk meningkatkan fungsionalitas dan kegunaan.
  - **Pagination**: Mengimplementasikan pagination sisi server untuk menangani daftar pengguna yang besar secara efisien.
  - **Pencarian & Pengurutan**: Mempertahankan dan mengintegrasikan fungsionalitas pencarian dan pengurutan yang ada dengan sistem pagination baru.
  - **Tombol Aksi yang Terintegrasi**: Menambahkan tombol "Lihat Percakapan" yang mengarahkan admin ke dasbor percakapan (`index.php`) dengan filter pengguna yang sudah diterapkan, menyederhanakan alur kerja untuk melihat riwayat chat pengguna.

## [4.2.17] - 2025-08-22

### Peningkatan
- **Konsistensi UI untuk Channel Chat**: Halaman riwayat pesan untuk channel dan grup (`admin/channel_chat.php`) telah diperbarui agar memiliki tampilan dan fungsionalitas yang sama dengan halaman riwayat pesan pengguna.
  - **Tampilan Tabel & Pagination**: Mengganti antarmuka lama dengan tampilan tabel yang padat informasi dan menyertakan pagination sisi server.
  - **Fitur Hapus Massal**: Menambahkan fungsionalitas hapus massal dengan checkbox dan menu aksi, sama seperti pada halaman chat pengguna.
  - **Konsistensi Kode**: `delete_messages_handler.php` telah digeneralisasi untuk menangani permintaan hapus dari kedua jenis halaman (pengguna dan channel), mengurangi duplikasi kode.

## [4.2.16] - 2025-08-22

### Fitur
- **Hapus Pesan Massal**: Menambahkan fungsionalitas untuk menghapus beberapa pesan sekaligus di halaman detail percakapan (`admin/chat.php`).
  - **Seleksi Pesan**: Admin dapat memilih pesan satu per satu menggunakan checkbox atau memilih semua pesan di halaman dengan satu klik.
  - **Menu Aksi**: Sebuah menu aksi memungkinkan admin untuk memilih tiga jenis penghapusan: hanya dari database lokal, hanya dari Telegram, atau dari keduanya.
  - **Implementasi**: Fitur ini didukung oleh handler backend baru (`delete_messages_handler.php`) dan penambahan metode `deleteMessage` pada `TelegramAPI.php`.

## [4.2.15] - 2025-08-22

### Diubah
- **Tampilan Detail Chat menjadi Tabel**: Mengubah total halaman detail percakapan (`admin/chat.php`) dari format obrolan menjadi tampilan tabel yang padat informasi, sesuai permintaan pengguna.
  - **Integrasi Tata Letak**: Halaman sekarang terintegrasi penuh dengan tata letak admin utama, menampilkan sidebar navigasi yang konsisten.
  - **Tampilan Tabel**: Pesan sekarang ditampilkan dalam tabel dengan kolom untuk ID, Waktu, Arah (Masuk/Keluar), Tipe, dan Konten.
  - **Fitur Pagination**: Mengimplementasikan pagination sisi server untuk menangani riwayat percakapan yang panjang secara efisien. Pesan ditampilkan per 50 item, dengan kontrol navigasi "Sebelumnya" dan "Berikutnya".

### Diperbaiki
- **Fatal Error di Halaman Detail Chat**: Memperbaiki error `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'mf.bot_id'` yang terjadi saat melihat riwayat chat.
  - **Penyebab**: Kondisi `JOIN` pada query SQL salah, mencoba menghubungkan tabel `messages` dan `media_files` menggunakan `bot_id` yang tidak ada di tabel media.
  - **Solusi**: Mengubah kondisi `JOIN` untuk menggunakan `m.chat_id = mf.chat_id`, yang secara akurat menautkan pesan ke media yang sesuai berdasarkan ID chat yang sama.

## [4.2.14] - 2025-08-22

### Peningkatan
- **Desain Ulang Halaman Percakapan**: Merombak total halaman daftar percakapan (`admin/index.php`) untuk pengalaman pengguna yang lebih baik dan lebih informatif.
  - **Tata Letak Dua Kolom**: Mengganti pemilihan bot berbasis dropdown dengan tata letak dua kolom yang persisten. Sidebar kiri sekarang menampilkan daftar semua bot, memungkinkan admin untuk beralih antar bot dengan cepat.
  - **Daftar Percakapan Berbasis Kartu**: Mengubah tampilan daftar percakapan dari tabel HTML standar menjadi daftar berbasis kartu yang modern.
  - **Fitur Informatif**: Setiap kartu sekarang menampilkan avatar berbasis inisial, nama pengguna, cuplikan pesan terakhir, dan waktu, sehingga lebih mudah untuk memindai dan mengidentifikasi percakapan penting.

## [4.2.13] - 2025-08-22

### Diubah
- **Tata Letak Panel Admin**: Merombak total tata letak panel admin dengan memindahkan navigasi utama dari header ke sidebar vertikal di sisi kiri.
  - **Tujuan**: Untuk meningkatkan pengalaman pengguna dengan menyediakan navigasi yang lebih terstruktur, mudah diakses, dan dapat diskalakan seiring penambahan fitur baru.
  - **Implementasi**:
    - Membuat file `partials/sidebar.php` baru untuk menampung menu navigasi admin.
    - Mengubah `partials/header.php` dan `partials/footer.php` untuk secara kondisional menerapkan tata letak dua kolom (sidebar dan konten utama) hanya untuk halaman admin, tanpa memengaruhi panel member.

## [4.2.12] - 2025-08-22

### Dokumentasi
- **Dokumentasi Kode Komprehensif**: Menambahkan dokumentasi lengkap (docstrings dan komentar sebaris) di seluruh basis kode PHP.
  - **Tujuan**: Untuk secara signifikan meningkatkan keterbacaan, pemeliharaan, dan kemudahan pemahaman kode bagi pengembang di masa depan.
  - **Cakupan**:
    - **Direktori `core/`**: Semua kelas `Handler` dan `Repository`, serta file-file inti seperti `TelegramAPI.php`, `database.php`, dan `helpers.php` telah didokumentasikan sepenuhnya, menjelaskan tujuan setiap kelas, metode, parameter, dan nilai kembalian.
    - **Direktori `admin/`**: Semua file halaman panel admin (misalnya, `index.php`, `bots.php`, `users.php`) dan file-file handler AJAX terkait telah diberikan blok komentar di tingkat file yang merangkum fungsionalitasnya, serta komentar pada blok logika utama.
    - **Direktori `member/`**: Semua file di panel anggota telah didokumentasikan dengan cara yang sama seperti direktori `admin`.
    - **File Root**: File `webhook.php` yang krusial telah didokumentasikan secara mendalam untuk menjelaskan alur kerja pemrosesan pembaruan dari awal hingga akhir.

## [4.2.11] - 2025-08-18

### Fitur
- **Balasan Otomatis di Grup Diskusi**: Bot sekarang akan secara otomatis membalas postingan yang di-forward dari channel ke grup diskusi yang terhubung.
  - **Pemicu**: Fitur ini aktif ketika sebuah pesan `is_automatic_forward` terdeteksi di grup.
  - **Aksi**: Bot akan membalas dengan pesan "Klik tombol di bawah untuk membeli" dan menyertakan tombol inline "Beli Sekarang".

### Diperbaiki
- **Logika Pendeteksian Forward Channel**: Memperbaiki logika di `MessageHandler` untuk mendeteksi pesan yang di-forward secara otomatis dari channel.
  - **Penyebab**: Implementasi sebelumnya salah menggunakan `forward_from_chat` untuk mendapatkan detail pesan asli, padahal spesifikasi dari Telegram untuk jenis forward ini menggunakan objek `forward_origin`.
  - **Solusi**: Mengubah `handleAutomaticForward` untuk membaca `forward_origin.chat.id` dan `forward_origin.message_id` untuk mencocokkan postingan asli di channel dengan paket yang tersimpan di database.


## [4.2.10] - 2025-08-16

### Diubah
- **Mengembalikan ke Mode `Markdown` Legacy**: Sesuai permintaan pengguna, semua penggunaan `parse_mode` diubah kembali dari `MarkdownV2` ke mode `Markdown` legacy.
  - **Penyebab**: Pengguna meminta untuk menggunakan mode parsing Markdown yang biasa, bukan `MarkdownV2`.
  - **Solusi**:
    1.  Mengganti nama fungsi `escapeMarkdownV2` menjadi `escapeMarkdown` di `TelegramAPI.php` dan menyesuaikan logikanya untuk hanya melakukan escape pada karakter `_`, `*`, `\``, dan `[`.
    2.  Mengubah semua `parse_mode` dari `'MarkdownV2'` menjadi `'Markdown'` di semua file handler (`MessageHandler`, `CallbackQueryHandler`, `ChannelPostHandler`) dan `webhook.php`.
    3.  Menghapus escaping manual untuk karakter yang tidak relevan dengan mode `Markdown` legacy (seperti `.`, `!`, `(`, `)`).

## [4.2.9] - 2025-08-16

### Diperbaiki
- **Fatal Error `can't parse entities` pada Callback Handler**: Memperbaiki error `can't parse entities` yang terlewat pada beberapa alur kerja yang ditangani oleh `CallbackQueryHandler`.
  - **Penyebab**: Beberapa metode di `CallbackQueryHandler` (seperti `handleRegisterSeller` dan `handlePostToChannel`) masih mengirim pesan dengan `parse_mode` `Markdown` atau tidak menyertakan `parse_mode` saat mengirim caption dengan markup, serta tidak melakukan escaping pada konten. Selain itu, metode `copyMessage` di `TelegramAPI.php` tidak mendukung parameter `parse_mode`.
  - **Solusi**:
    1.  Menambahkan dukungan parameter `parse_mode` ke metode `copyMessage` di `TelegramAPI.php`.
    2.  Di `CallbackQueryHandler.php`, mengubah semua pemanggilan `sendMessage` dan `copyMessage` yang relevan untuk menggunakan `parse_mode=MarkdownV2`.
    3.  Menerapkan fungsi `escapeMarkdownV2()` pada semua konten dinamis (seperti ID publik, deskripsi, harga) sebelum dikirim.

## [4.2.8] - 2025-08-16

### Diperbaiki
- **Fatal Error `can't parse entities` pada Berbagai Perintah**: Memperbaiki error `can't parse entities` yang disebabkan oleh karakter khusus (`=`, `!`, `.`, `(`, `)`, dll.) dalam data dinamis (seperti nama pengguna, deskripsi item) atau teks statis di berbagai perintah.
  - **Penyebab**: Pesan yang dikirim dengan `parse_mode=Markdown` atau `MarkdownV2` tidak melakukan escaping pada karakter-karakter khusus yang dicadangkan oleh Telegram, menyebabkan API gagal mem-parsing pesan.
  - **Solusi**:
    1.  Membuat fungsi helper baru `escapeMarkdownV2()` di `TelegramAPI.php` untuk melakukan escaping pada semua karakter khusus sesuai standar `MarkdownV2`.
    2.  Mengubah semua `parse_mode` yang relevan di `MessageHandler.php` dan `webhook.php` menjadi `MarkdownV2`.
    3.  Menerapkan fungsi `escapeMarkdownV2()` pada semua data dinamis yang dimasukkan ke dalam pesan.
    4.  Melakukan escaping manual pada karakter khusus di semua teks pesan statis untuk memastikan konsistensi.

## [4.2.7] - 2025-08-15

### Diperbaiki
- **Fatal Error `can't parse entities` pada Perintah /help**: Memperbaiki error Markdown yang tersisa pada perintah `/help`.
  - **Penyebab**: Penggunaan `Markdown` legacy yang tidak konsisten dan karakter `-` yang tidak di-escape menyebabkan error saat parsing di `MarkdownV2`.
  - **Solusi**: Secara eksplisit mengubah `parse_mode` untuk perintah `/help` menjadi `MarkdownV2` dan melakukan escaping pada semua karakter khusus (`-`, `.`, `(`, `)`, dll.) sesuai dengan aturan `MarkdownV2`.

## [4.2.6] - 2025-08-15

### Peningkatan
- **Pesan Bantuan /help Disederhanakan**: Teks bantuan yang ditampilkan oleh perintah `/help` telah ditulis ulang sepenuhnya menjadi lebih ringkas, jelas, dan mudah dibaca menggunakan format daftar (bullet points) untuk meningkatkan pengalaman pengguna.

## [4.2.5] - 2025-08-15

### Diperbaiki
- **Format Markdown Rusak pada Perintah /help**: Memperbaiki masalah format pada pesan `/help` yang menyebabkan teks tidak ditampilkan dengan benar.
  - **Penyebab**: Karakter garis bawah (`_`) dalam contoh ID paket (misalnya, `ABCD_0001`) tidak di-escape, sehingga merusak parser Markdown Telegram.
  - **Solusi**: Melakukan escaping pada karakter `_` di semua contoh ID dalam teks bantuan.

## [4.2.4] - 2025-08-15

### Diperbaiki
- **Fatal Error di Halaman "Konten Dibeli"**: Memperbaiki error `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'mf.file_id'` yang terjadi saat member membuka halaman "Konten Dibeli".
  - **Penyebab**: Query database untuk mengambil daftar konten yang dibeli (`findPackagesByBuyerId`) masih mencoba memilih kolom `file_id` yang sudah dihapus.
  - **Solusi**: Menghapus referensi ke kolom `mf.file_id` dari query di `SaleRepository.php`.

## [4.2.3] - 2025-08-15

### Diperbaiki
- **Fatal Error di Halaman "Konten Dijual"**: Memperbaiki error `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'mf.file_id'` yang terjadi saat member membuka halaman "Konten Dijual".
  - **Penyebab**: Query database untuk mengambil daftar konten yang dijual (`findAllBySellerId`) masih mencoba memilih kolom `file_id` yang sudah dihapus.
  - **Solusi**: Menghapus referensi ke kolom `mf.file_id` dari query di `PackageRepository.php`.

## [4.2.2] - 2025-08-15

### Diperbaiki
- **Fatal Error pada Perintah /start dengan Deep Link**: Memperbaiki error `TypeError` yang terjadi saat pengguna menggunakan tautan `start` yang berisi ID paket (misalnya, dari tombol "Beli").
  - **Penyebab**: ID paket diekstrak sebagai string, tetapi metode `PackageRepository::find()` mengharapkan integer.
  - **Solusi**: Melakukan konversi tipe data (casting) ID paket menjadi integer sebelum memanggil metode `find()`.

## [4.2.1] - 2025-08-15

### Diperbaiki
- **Fatal Error pada Callback Tombol "Post ke Channel"**: Memperbaiki error `Undefined constant "BOT_USERNAME"` yang terjadi saat pengguna menekan tombol "Post ke Channel".
  - **Penyebab**: Konstanta `BOT_USERNAME` hanya didefinisikan untuk satu jenis update (inline query) dan tidak tersedia untuk callback query.
  - **Solusi**: Logika untuk mendefinisikan konstanta `BOT_USERNAME` telah dipindahkan ke bagian awal skrip `webhook.php` sehingga tersedia secara global untuk semua jenis update.

## [4.2.0] - 2025-08-15

### Fitur
- **Posting Konten ke Channel Jualan**: Penjual sekarang dapat mendaftarkan channel jualan mereka dan mem-posting konten ke sana.
  - **Pendaftaran Channel**: Perintah baru `/register_channel <ID Channel>` memungkinkan penjual untuk mendaftarkan channel mereka melalui chat pribadi dengan bot. Bot akan memverifikasi bahwa ia adalah admin di channel tersebut.
  - **Tombol Posting Manual**: Saat melihat detail konten milik sendiri via `/konten`, penjual akan melihat tombol baru "Post ke Channel".
  - **Penanganan Kegagalan Otomatis**: Jika bot gagal mem-posting ke channel (misalnya, karena sudah bukan admin), channel tersebut akan otomatis di-unregister dari sistem untuk mencegah error berulang.

## [4.1.0] - 2025-08-14

### Fitur
- **Berbagi Konten via Inline Mode**: Bot sekarang mendukung Inline Mode. Pengguna dapat mengetik `@nama_bot <ID Konten>` di grup atau chat mana pun untuk secara instan mencari dan membagikan pratinjau konten, lengkap dengan tombol "Beli". Ini memberikan cara yang cepat dan ramah privasi untuk mempromosikan konten.

## [4.0.2] - 2025-08-14

### Keamanan
- **Menyembunyikan Perintah Admin**: Perintah `/help` sekarang hanya akan menampilkan bagian "Perintah Admin" kepada pengguna yang memiliki peran sebagai admin. Ini mencegah pengguna biasa melihat perintah-perintah sensitif yang dapat disalahgunakan.

## [4.0.1] - 2025-08-14

### Diperbaiki
- **Pesan Perintah /help Terlalu Panjang**: Memperbaiki error `Bad Request: message is too long` yang terjadi saat menggunakan perintah `/help`. Pesan bantuan yang panjang sekarang secara otomatis dipecah menjadi beberapa pesan yang lebih kecil untuk mematuhi batas karakter API Telegram.

## [4.0.0] - 2025-08-14

### Fitur
- **Logging Kesalahan API ke Database**: Semua kegagalan saat berkomunikasi dengan API Telegram sekarang dicatat secara otomatis ke dalam tabel `telegram_error_logs` baru di database. Ini memberikan catatan permanen dan terstruktur untuk analisis masalah.
- **Halaman Log Kesalahan di Panel Admin**: Menambahkan halaman baru (`admin/telegram_logs.php`) yang menampilkan semua log kesalahan API Telegram dari database. Halaman ini dilengkapi dengan paginasi untuk navigasi yang mudah.

### Peningkatan
- **Penanganan Kesalahan API**: Merombak total mekanisme penanganan kesalahan di `TelegramAPI.php`. Sekarang menggunakan blok `try-catch` yang lebih tangguh untuk menangkap semua jenis kegagalan, termasuk error koneksi cURL, timeout, dan respons error dari Telegram.
- **Logika Penanganan Error Spesifik**: Menambahkan logika cerdas untuk menangani kode error spesifik dari Telegram:
  - **400 (Bad Request)**: Memberikan log yang lebih deskriptif untuk masalah umum seperti "chat not found" atau "can't parse entities".
  - **403 (Forbidden)**: Secara spesifik mendeteksi saat bot diblokir oleh pengguna.
  - **429 (Too Many Requests)**: Mendeteksi permintaan rate-limit dan mencatatnya dengan status `pending_retry` untuk potensi pemrosesan ulang di masa depan.
- **Navigasi Panel Admin**: Menambahkan tautan "Log Error Telegram" ke menu navigasi di semua halaman panel admin untuk akses yang cepat dan konsisten.
- **Status Pengguna Diblokir**: Saat bot mendeteksi bahwa ia diblokir oleh pengguna, sistem sekarang secara otomatis akan memperbarui status pengguna tersebut menjadi 'blocked' di database, bukan hanya mencatatnya di log.

## [3.12.4] - 2025-08-14

### Diperbaiki
- **Fatal Error di MessageHandler**: Memperbaiki error fatal `Call to a member function hasUserPurchased() on null` yang terjadi saat memproses pesan biasa.
  - **Penyebab**: `MessageHandler` tidak menginisialisasi `SaleRepository` di dalam constructor-nya, sehingga properti `$this->sale_repo` bernilai `null` saat coba diakses.
  - **Solusi**: Menambahkan inisialisasi `SaleRepository` di dalam constructor `MessageHandler.php` untuk memastikan semua dependensi tersedia.

## [3.12.3] - 2025-08-14

### Diperbaiki
- **Fatal Error di Perintah /konten**: Memperbaiki error fatal `Column not found: 1054 Unknown column 'file_id'` yang terjadi saat menggunakan perintah `/konten`.
  - **Penyebab**: Serupa dengan error sebelumnya, query untuk mengambil data thumbnail di dalam `handleKontenCommand` masih mencoba memilih kolom `file_id` yang sudah dihapus.
  - **Solusi**: Menghapus referensi ke `file_id` dari query SQL di `MessageHandler.php`.

## [3.12.2] - 2025-08-14

### Diperbaiki
- **Fatal Error di Halaman Log Media**: Memperbaiki error fatal `Column not found: 1054 Unknown column 'mf.file_id'` pada halaman `admin/media_logs.php`.
  - **Penyebab**: Halaman log media masih mencoba memilih kolom `file_id` dari database, padahal kolom tersebut sudah dihapus pada optimisasi skema sebelumnya (v3.10.0).
  - **Solusi**: Menghapus referensi ke `mf.file_id` dari query SQL di halaman log media.

## [3.12.1] - 2025-08-14

### Diperbaiki
- **Error di Panel Admin**: Memperbaiki `Warning: Undefined array key "first_name"` dan error `htmlspecialchars()` terkait yang muncul di dropdown pemilihan bot pada halaman utama panel admin.
  - **Penyebab**: Query database di `admin/index.php` masih memilih kolom `name` yang lama, padahal seharusnya `first_name` sesuai dengan skema database terbaru.
  - **Solusi**: Mengubah query SQL untuk memilih `first_name` dan menambahkan null coalescing operator (`??`) pada `htmlspecialchars()` untuk mencegah error jika nama bot kosong.

## [3.12.0] - 2025-08-13

### Ditambahkan
- **Mode Edit Paket dengan /addmedia <ID>**: Perintah `/addmedia` sekarang memiliki fungsionalitas ganda untuk mengedit paket yang sudah ada.
  - **Alur Kerja Baru**: Penjual dapat menggunakan `/addmedia <ID_PAKET>` sambil me-reply media baru untuk menambahkannya ke paket yang sudah ada dan tersedia.
  - **Otorisasi**: Sistem akan memverifikasi bahwa pengguna yang menjalankan perintah adalah pemilik sah dari paket tersebut.
  - **Penambahan Konten**: Media baru (termasuk seluruh item dari media group) akan disalin ke channel penyimpanan yang sama dengan konten lama dan ditautkan ke paket yang ada di database.
  - **Tujuan**: Memberikan fleksibilitas kepada penjual untuk memperbarui dan memperluas konten paket mereka bahkan setelah paket tersebut dirilis.

## [3.11.0] - 2025-08-13

### Ditambahkan
- **Perintah /addmedia untuk Paket Multi-bagian**: Penjual sekarang dapat membuat paket konten yang besar dengan menambahkan media secara bertahap.
  - **Alur Kerja Baru**: Setelah memulai dengan `/sell`, penjual dapat menggunakan perintah `/addmedia` baru sambil me-reply media atau album tambahan. Proses ini dapat diulang beberapa kali.
  - **State Management**: Logika state pengguna (`awaiting_price`) telah direfaktor untuk dapat menampung beberapa media atau media group dalam satu sesi pembuatan paket.
  - **Finalisasi**: Saat penjual akhirnya memasukkan harga, bot akan memproses semua media yang telah ditambahkan, menyalin semuanya ke channel penyimpanan, dan menggabungkannya ke dalam satu paket tunggal.
  - **Tujuan**: Memungkinkan penjual untuk melampaui batas 10 item per media group di Telegram, sehingga dapat menjual paket konten yang jauh lebih besar (hingga 100 item atau lebih).

## [3.10.0] - 2025-08-13

### Peningkatan
- **Optimisasi Skema Database**: Menghapus kolom `file_id` yang tidak lagi diperlukan dari tabel `media_files`.
  - **Alasan**: Sejak implementasi `copyMessage` dan `copyMessages` sebagai metode utama untuk mengirim media, `file_id` menjadi berlebihan. Referensi ke media sekarang secara konsisten ditangani menggunakan `chat_id` dan `message_id`.
  - **Keuntungan**: Mengurangi ukuran database dan menyederhanakan logika `MediaHandler` dan `MediaFileRepository` dengan menghapus penyimpanan dan penanganan data yang tidak terpakai.

## [3.9.0] - 2025-08-13

### Diubah
- **Penanganan Penjualan Album/Media Group**: Mengubah logika inti dari proses penjualan untuk mendukung album (media group) secara penuh.
  - **Sebelumnya**: Saat menjual sebuah album, bot hanya akan menyalin satu media (yang di-reply oleh pengguna) ke channel penyimpanan.
  - **Sekarang**: Jika bot mendeteksi penjualan sebuah media group, bot akan menyalin *setiap* media dari grup tersebut ke channel penyimpanan, memastikan pembeli menerima semua konten.

### Peningkatan
- **Optimisasi Penyalinan Album**: Proses penyalinan media group sekarang menggunakan metode `copyMessages` dari API Telegram, yang menyalin semua media dalam satu panggilan API tunggal, bukan satu per satu. Ini secara signifikan mengurangi jumlah panggilan API, membuat proses penjualan album lebih cepat dan efisien.

## [3.8.1] - 2025-08-13

### Peningkatan
- **Pesan Konfirmasi /sell yang Lebih Informatif**: Saat pengguna memulai perintah `/sell`, pesan yang meminta harga sekarang juga menampilkan rincian konten yang akan dijual.
  - **Rincian Konten**: Pesan sekarang menyertakan pratinjau deskripsi (caption) dan rincian jumlah media berdasarkan jenisnya (misal: `1 📹, 3 🖼️`).
  - **Instruksi Pembatalan**: Pesan juga secara eksplisit memberitahu pengguna bahwa mereka dapat mengetik `/cancel` untuk membatalkan proses penjualan.
  - **Tujuan**: Memberikan konfirmasi yang jelas kepada penjual tentang item apa yang sedang mereka proses, meningkatkan kejelasan alur kerja, dan mengurangi kesalahan.

## [3.8.0] - 2025-08-13

### Diubah
- **Refactoring Alur Perintah /sell**: Merombak total alur kerja perintah `/sell` untuk meningkatkan efisiensi dan keandalan, terutama dalam menangani pembatalan.
  - **Sebelumnya**: Saat `/sell` digunakan, bot akan langsung menyalin media ke channel penyimpanan dan membuat entri paket `pending` di database (menaikkan nomor urut ID konten) *sebelum* meminta harga. Jika pengguna membatalkan, media sampah dan ID yang terlewat akan tertinggal.
  - **Sekarang**: Proses dibalik. Perintah `/sell` sekarang hanya akan menangkap konteks media dan meminta harga. Semua aksi berat—menyalin media, membuat paket, dan menaikkan nomor urut ID—ditunda dan hanya akan dijalankan *setelah* penjual memberikan harga yang valid.
  - **Keuntungan**: Pembatalan (`/cancel`) sekarang bersih dan tidak meninggalkan data sisa. ID konten hanya akan digunakan dan di-increment saat transaksi benar-benar dikonfirmasi dengan harga, mencegah adanya celah dalam urutan ID.

## [3.7.0] - 2025-08-13

### Ditambahkan
- **Sinkronisasi Caption yang Diedit**: Bot sekarang dapat mendeteksi saat pengguna mengedit caption sebuah media.
  - **Logika Baru**: Menambahkan handler baru (`EditedMessageHandler`) yang akan aktif saat menerima pembaruan `edited_message` dari Telegram.
  - **Pembaruan Database**: Jika pesan yang diedit terhubung ke sebuah file media yang tersimpan di database, bot akan secara otomatis memperbarui kolom `caption` di tabel `media_files` dengan teks yang baru. Ini memastikan bahwa deskripsi produk selalu sinkron, bahkan jika penjual mengubahnya setelah pengiriman awal.

## [3.6.2] - 2025-08-13

### Peningkatan
- **Deteksi Caption pada Media Group**: Meningkatkan logika perintah `/sell` secara signifikan.
  - **Sebelumnya**: Bot hanya mengambil caption dari satu media spesifik yang di-reply oleh pengguna. Jika pengguna me-reply pada gambar atau video dalam sebuah album yang tidak memiliki caption, maka deskripsi produk akan menjadi kosong.
  - **Sekarang**: Jika bot mendeteksi media yang di-reply adalah bagian dari `media_group_id`, bot akan secara otomatis memindai semua item dalam grup tersebut di database untuk menemukan caption yang ada. Ini memastikan deskripsi produk terisi dengan benar, tidak peduli item mana dalam album yang di-reply oleh pengguna.

## [3.6.1] - 2025-08-13

### Diperbaiki
- **Masalah Transaksi Bersarang**: Memperbaiki bug kritis di mana perintah `/sell` akan gagal dengan error `There is already an active transaction`.
  - **Penyebab**: Logika webhook utama sudah memulai sebuah transaksi database untuk setiap permintaan. Namun, `PackageRepository` mencoba memulai transaksi kedua secara internal, yang menyebabkan konflik.
  - **Solusi**: Menghapus logika transaksi (panggilan `beginTransaction`, `commit`, `rollBack`) yang berlebihan dari dalam metode `createPackageWithPublicId` dan `hardDeletePackage` di `PackageRepository`. Transaksi tunggal yang dikelola oleh `webhook.php` sekarang mencakup seluruh operasi, memastikan integritas data tanpa menyebabkan error.

## [3.6.0] - 2025-08-13

### Ditambahkan
- **Fitur Bersihkan Database (Admin)**: Menambahkan fitur berisiko tinggi di halaman `admin/database.php` untuk membersihkan semua data transaksional.
  - **Aksi**: Menghapus semua data dari tabel `users`, `sales`, `media_packages`, `media_files`, `messages`, dan tabel terkait lainnya.
  - **Tujuan**: Memungkinkan admin untuk memulai ulang (reset) data aplikasi tanpa menghapus konfigurasi penting seperti `bots` dan `private_channels`.
  - **Keamanan**: Fitur ini ditempatkan di "Zona Berbahaya" dengan teks peringatan yang jelas dan dialog konfirmasi ganda untuk mencegah penggunaan yang tidak disengaja.

## [3.5.0] - 2025-08-13

### Diubah
- **Format ID Konten**: Mengubah format ID konten yang dilihat pengguna menjadi `XXXX_YYYY` (4 huruf acak unik per penjual, diikuti 4 angka berurutan per penjual).
  - **Alur Registrasi Penjual**: Saat pengguna pertama kali menggunakan `/sell`, bot akan memandu mereka melalui proses pendaftaran singkat untuk mendapatkan ID Penjual publik yang unik (misal: `ASDF`).
  - **ID Konten Berurutan per Penjual**: Setiap konten yang dibuat oleh penjual akan mendapatkan nomor urut, menghasilkan ID seperti `ASDF_0001`, `ASDF_0002`, dst.
  - **Pembaruan Database & Kode**: Menambahkan kolom `public_seller_id` dan `seller_package_sequence` ke tabel `users`, serta `public_id` ke `media_packages`. Semua antarmuka pengguna diperbarui untuk menggunakan format ID baru ini.
  - **Skrip Back-fill**: Menyertakan skrip `migrations/populate_public_ids.php` untuk menghasilkan ID baru ini bagi semua data penjual dan konten yang sudah ada.

## [3.4.0] - 2025-08-13

### Ditambahkan
- **Fitur Analitik Penjualan**: Menambahkan dasbor analitik baru untuk admin dan penjual.
  - **Halaman Analitik Admin (`admin/analytics.php`)**: Halaman baru yang menampilkan ringkasan penjualan global, termasuk total pendapatan, jumlah penjualan, grafik pendapatan harian, dan daftar konten terlaris.
  - **Analitik di Dasbor Member**: Penjual sekarang dapat melihat ringkasan pendapatan dan jumlah penjualan pribadi mereka langsung di halaman dasbor member.
  - **`AnalyticsRepository`**: Membuat repository baru yang didedikasikan untuk menangani query analitik yang kompleks.

## [3.3.0] - 2025-08-12

### Ditambahkan
- **Fitur Proteksi Konten**: Penjual sekarang dapat melindungi konten mereka agar tidak dapat disimpan atau diteruskan oleh pembeli.
  - **Tombol Toggle di Panel Member**: Di halaman "Konten Dijual", setiap paket memiliki tombol *toggle* untuk mengaktifkan/menonaktifkan proteksi.
  - **Penerapan via `protect_content`**: Saat pembeli mengakses konten yang diproteksi, sistem akan menggunakan parameter `protect_content=true` dari API Telegram, yang mencegah penyimpanan, penerusan, dan *screenshot* pada sebagian besar perangkat.
  - **Perubahan Database**: Menambahkan kolom `protect_content` ke tabel `media_packages`.

## [3.2.0] - 2025-08-12

### Ditambahkan
- **Fitur Hard-Delete Konten (Admin)**: Memberikan admin kemampuan untuk menghapus konten secara permanen.
  - **Halaman Manajemen Konten**: Membuat halaman baru `admin/packages.php` yang menampilkan semua paket konten dalam sistem.
  - **Tombol Hapus Permanen**: Setiap konten di halaman baru ini memiliki tombol "Hard Delete". Tombol ini dinonaktifkan untuk konten yang sudah terjual untuk melindungi riwayat transaksi.
  - **Logika Penghapusan Penuh**: Aksi ini akan menghapus paket dari database, menghapus semua file media terkait dari database, dan juga menghapus pesan media dari channel penyimpanan pribadi di Telegram.

## [3.1.0] - 2025-08-12

### Ditambahkan
- **Fitur Soft-Delete Konten**: Member sekarang dapat menghapus konten yang mereka jual dari halaman "Konten Dijual".
  - Ini adalah *soft delete*: status paket diubah menjadi `deleted` dan tidak lagi ditampilkan, tetapi data tetap ada di database untuk integritas arsip.
  - Konten yang sudah terjual (`sold`) tidak dapat dihapus.
  - Menambahkan status `deleted` baru ke kolom `status` pada tabel `media_packages`.

## [3.0.0] - 2025-08-12

### Ditambahkan
- **Fitur Panel Member**: Memperluas fungsionalitas panel member secara signifikan dengan menambahkan dua halaman baru.
  - **Halaman Konten Dijual (`member/sold.php`)**: Member sekarang dapat melihat riwayat semua konten yang telah mereka jual, lengkap dengan status (pending, available, sold).
  - **Halaman Konten Dibeli (`member/purchased.php`)**: Member dapat melihat riwayat semua konten yang telah mereka beli, beserta harga dan tanggal pembelian.
- **Peningkatan Navigasi Member**: Mendesain ulang header di seluruh panel member untuk menyertakan navigasi yang jelas antara halaman Dashboard, Dijual, dan Dibeli.
- **Metode Repository Baru**: Menambahkan `findAllBySellerId` ke `PackageRepository` dan `findPackagesByBuyerId` ke `SaleRepository` untuk mendukung halaman-halaman baru.

## [2.9.0] - 2025-08-12

### Peningkatan
- **Manajemen Bot Otomatis**: Proses penambahan bot di panel admin telah disederhanakan secara signifikan.
  - **Hapus Input Nama**: Admin tidak perlu lagi memasukkan nama bot secara manual. Cukup dengan memasukkan token bot.
  - **Ambil Info via `getMe`**: Sistem sekarang secara otomatis memanggil metode `getMe` dari API Telegram untuk mengambil `first_name` dan `username` bot, lalu menyimpannya ke database.
  - **Tombol Pembaruan**: Menambahkan tombol "Get Me & Update" di halaman edit bot, yang memungkinkan admin untuk menyinkronkan dan memperbarui informasi bot kapan saja.
- **Skema Database**: Memperbarui tabel `bots` dengan mengganti nama kolom `name` menjadi `first_name` dan menambahkan kolom `username` untuk menyelaraskan dengan data dari API Telegram.

## [2.8.0] - 2025-08-12

### Peningkatan
- **Refactoring Panel Admin**: Merapikan dan mengorganisir ulang halaman pengaturan di panel admin untuk meningkatkan kejelasan dan kemudahan penggunaan.
  - Halaman "Pengaturan" (`settings.php`) yang sebelumnya berisi beberapa fungsi, sekarang difokuskan hanya untuk manajemen channel dan diganti namanya menjadi `channels.php`.
  - Fungsionalitas migrasi database dipindahkan ke halamannya sendiri yang baru, yaitu `database.php`.
  - Menu navigasi di seluruh panel admin diperbarui untuk mencerminkan struktur baru ini, menggantikan tautan "Pengaturan" tunggal dengan tautan "Channel" dan "Database" yang lebih spesifik.

## [2.7.0] - 2025-08-12

### Ditambahkan
- **Fitur Channel Penyimpanan Pribadi**: Menambahkan kemampuan untuk mendaftarkan channel-channel pribadi sebagai tujuan penyimpanan file.
  - **Manajemen via Admin Panel**: Di halaman "Pengaturan", admin sekarang dapat menambah atau menghapus channel penyimpanan.
  - **Distribusi File Round-Robin per Bot**: Ketika pengguna menggunakan perintah `/sell`, file media akan secara otomatis disalin ke salah satu channel pribadi. Sistem menggunakan strategi round-robin yang dilacak untuk setiap bot secara individual untuk mendistribusikan beban dan menghindari *rate limit* Telegram.
- **Migrasi Database Baru**: Menambahkan tiga skrip migrasi baru untuk membuat tabel `private_channels`, `bot_channel_usage`, dan untuk menambahkan kolom `storage_channel_id` serta `storage_message_id` ke tabel `media_files`.

## [2.6.0] - 2025-08-12

### Diubah
- **Metode Pengiriman Media**: Sistem pengiriman media telah diubah sepenuhnya dari penggunaan `file_id` menjadi metode `copyMessage` dan `copyMessages`.
  - **Pengiriman Tunggal**: Pratinjau konten yang dikirim melalui perintah `/konten` sekarang menggunakan `copyMessage`.
  - **Pengiriman Massal**: Pengiriman konten lengkap setelah pembelian atau melalui tombol "Lihat Selengkapnya" sekarang menggunakan `copyMessages` untuk mengirim file sebagai batch.
- **Keuntungan**: Perubahan ini meningkatkan keandalan pengiriman media, karena `file_id` dapat menjadi tidak valid dari waktu ke waktu, sementara `copyMessage` memastikan media selalu dapat diakses selama ada di channel sumber.

## [2.5.0] - 2025-08-11

### Peningkatan
- **Struktur Kode Modular**: `webhook.php` telah direfaktor secara ekstensif menjadi arsitektur yang lebih modular dan mudah dikelola.
  - **Handlers**: Logika untuk setiap jenis pembaruan (pesan, callback query, media) telah dipisahkan ke dalam kelas-kelas `Handler` khusus (misalnya, `MessageHandler`, `CallbackQueryHandler`).
  - **Repositories**: Logika database telah diabstraksi ke dalam kelas-kelas `Repository` (misalnya, `UserRepository`, `PackageRepository`), memisahkan query database dari logika bisnis.

### Diubah
- **File `webhook.php`**: Sekarang berfungsi sebagai *controller* atau titik masuk utama yang bersih, yang tugasnya hanya untuk menginisialisasi dan mendelegasikan pembaruan ke handler yang sesuai. Ini secara signifikan mengurangi kompleksitasnya.

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
