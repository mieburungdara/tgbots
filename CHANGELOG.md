# Changelog

## [5.2.17] - 2025-09-07

### Diperbaiki
- **Syntax Error di CommandRouter.php**: Memperbaiki `syntax error` yang disebabkan oleh penempatan yang salah dari instansiasi `AdminCommand` dan duplikasi inisialisasi array `$this->commands` di `core/handlers/CommandRouter.php`. Logika telah direstrukturisasi untuk memastikan instansiasi yang benar dan menghindari duplikasi.

## [5.2.18] - 2025-09-07

### Fitur
- **Endpoint API getMe untuk Bot**: Menambahkan rute API baru `/api/xoradmin/bots/get-me` yang mengarah ke `Admin/BotController@getMe`. Ini memungkinkan pengambilan informasi bot terbaru dari Telegram API dan memperbarui data bot di database secara langsung melalui endpoint ini.

## [5.2.19] - 2025-09-07

### Diperbaiki
- **Fatal Error Class Not Found di Autoloader**: Memperbaiki masalah `Class not found` untuk kelas-kelas di direktori `handlers/` dan `database/` yang disebabkan oleh penanganan path yang tidak konsisten pada autoloader. Mengubah `strtolower($directoryPath)` menjadi `strtolower(str_replace('\\', '/', $directoryPath))` untuk memastikan path direktori selalu dalam huruf kecil dan menggunakan forward slashes, sesuai dengan struktur file pada sistem operasi case-sensitive seperti Linux.

## [5.2.21] - 2025-09-07

### Diperbaiki
- **Syntax Error di StartCommand.php**: Memperbaiki `syntax error` pada baris 66 di `core/handlers/Commands/StartCommand.php` yang disebabkan oleh tanda kutip ganda yang tidak tertutup pada variabel `$caption`, menyebabkan `unexpected variable`.

## [5.2.20] - 2025-09-07

### Diperbaiki
- **Parse Error di Autoloader**: Memperbaiki `parse error` pada `core/autoloader.php` baris 30 yang disebabkan oleh kesalahan escaping backslash dalam string. Mengubah `str_replace('\', '/', $directoryPath)` menjadi `str_replace('\\', '/', $directoryPath)` untuk memastikan sintaks yang benar.


## [5.2.16] - 2025-09-06

### Diperbaiki
- **Duplikasi Instansiasi AdminCommand**: Merefaktor `CommandRouter.php` untuk menggunakan satu instansi `AdminCommand` untuk perintah `/dev_addsaldo` dan `/feature`, menghilangkan duplikasi dan meningkatkan efisiensi.
- **Duplikasi Teks Perintah Umum**: Merefaktor `HelpCommand.php` untuk menghilangkan duplikasi teks "PERINTAH UMUM" dalam pesan bantuan. Teks umum sekarang didefinisikan sekali dan ditambahkan secara terpusat, sesuai prinsip DRY.

## [5.2.15] - 2025-09-06

### Peningkatan
- **Logging Diagnostik Backup Media**: Menambahkan trace log yang lebih rinci pada alur backup media di `MessageHandler::handleState`.
  - **Tujuan**: Membantu mendiagnosis mengapa media tidak berhasil disalin ke private channel backup, dengan mencatat setiap langkah proses, termasuk pemilihan channel, status penyalinan, dan pembaruan informasi penyimpanan.

## [5.2.14] - 2025-09-06

### Diperbaiki
- **Thumbnail Tidak Ditemukan**: Memperbaiki masalah "Tidak ditemukan thumbnail yang valid" pada paket media.
  - **Penyebab**: `storage_channel_id` dan `storage_message_id` tidak disimpan ke tabel `media_files` setelah media berhasil disalin ke channel penyimpanan pribadi.
  - **Solusi**:
    1.  Memperbarui `MediaFileRepository::saveMediaFile` untuk menyertakan kolom `storage_channel_id` dan `storage_message_id`.
    2.  Menambahkan metode `MediaFileRepository::updateStorageInfo` untuk memperbarui informasi penyimpanan media.
    3.  Memodifikasi `MessageHandler::handleState` untuk memanggil `MediaFileRepository::updateStorageInfo` setelah media berhasil disalin ke channel penyimpanan, memastikan `storage_channel_id` dan `storage_message_id` tercatat dengan benar.

## [5.2.13] - 2025-09-06

### Dokumentasi
- **Alur Fitur /sell**: Menambahkan dokumentasi baru `alur_sell.md` yang menjelaskan secara rinci alur kerja dan logika penggunaan perintah `/sell`.

## [5.2.12] - 2025-09-06

### Diubah
- **Logika Backup Media /sell**: Mengubah alur backup media yang dijual dari channel moderasi ke channel penyimpanan pribadi (private channel). Media sekarang disalin ke channel pribadi pertama yang ditemukan di database `private_channels` setelah harga divalidasi, bukan ke channel yang dikonfigurasi melalui `findSystemChannelByFeature`.

## [5.2.11] - 2025-09-06

### Dokumentasi
- **Alur Perintah /sell**: Merevisi `alur.md` untuk menjelaskan secara lebih mudah dipahami alur kerja dan langkah-langkah untuk memeriksa serta menganalisis bagaimana perintah `/sell` diproses dalam sistem bot, mulai dari penerimaan webhook hingga logika penanganan state pengguna.

## [5.2.10] - 2025-09-05

### Fitur
- **Perintah /about**: Menambahkan perintah baru `/about` yang menampilkan informasi tentang pengembang bot dan menyediakan link kontak untuk kerja sama.

## [5.2.9] - 2025-09-05

### Peningkatan
- **Wizard Pendaftaran Channel**: Formulir pendaftaran channel di panel member telah diubah menjadi format wizard multi-langkah yang lebih interaktif dan menarik, meningkatkan pengalaman pengguna saat menambahkan channel baru.

## [5.2.8] - 2025-09-04

### Diubah
- **Pendaftaran Channel Hanya via Web**: Perintah `/register_channel` telah dihapus dari bot. Pendaftaran dan pengelolaan channel penjualan sekarang hanya dapat dilakukan melalui panel member di website untuk menyederhanakan alur kerja.

## [5.2.7] - 2025-09-04

### Peningkatan
- **Tampilan Nama Channel & Grup**: Pada halaman "Daftar Channel Jualan" di panel member, sekarang nama channel dan nama grup diskusi ditampilkan di samping ID mereka. Ini membuat identifikasi channel menjadi lebih mudah bagi pengguna.
- **Penyimpanan Nama Otomatis**: Logika pendaftaran channel (baik melalui bot maupun web) telah diperbarui untuk secara otomatis mengambil dan menyimpan nama channel dan grup dari Telegram API.
- **Migrasi Data**: Menambahkan skrip migrasi untuk secara otomatis mengisi nama untuk channel dan grup yang sudah terdaftar sebelumnya.

## [5.2.6] - 2025-09-04

### Fitur
- **Dukungan Multi-Channel untuk Penjual**: Member sekarang dapat mendaftarkan dan mengelola beberapa channel penjualan secara bersamaan.
  - Tampilan panel member di `/member/channels` telah diubah untuk menampilkan daftar semua channel yang terdaftar.
  - Member dapat menambahkan channel baru kapan saja, dan menghapus channel yang sudah ada.
  - Fitur "Post ke Channel" sekarang akan menampilkan pilihan channel jika member memiliki lebih dari satu, memberikan kontrol penuh kepada penjual.

## [5.2.5] - 2025-09-04

### Diperbaiki
- **Moderasi Otomatis untuk Fitur /sell**: Memperbaiki alur kerja fitur `/sell` yang sebelumnya secara keliru mengirimkan semua item untuk moderasi manual. Sekarang, item yang dijual melalui `/sell` akan secara otomatis ditandai sebagai `available` dan langsung dapat dibeli, sesuai dengan alur kerja yang diinginkan. Alur moderasi manual kini hanya berlaku untuk fitur `/rate` dan `/tanya`.
- **Logika Channel Penjualan**: Memperbaiki logika saat mendaftarkan channel untuk fitur `/sell` (baik melalui bot maupun web). Kolom `moderation_channel_id` sekarang diatur ke `NULL` dengan benar untuk channel penjualan, karena moderasi tidak berlaku untuk fitur ini. Juga menyertakan migrasi database untuk membersihkan data lama.

## [5.2.4] - 2025-09-04

### Fitur
- **Pemilihan Bot Pengelola Channel**: Member sekarang dapat memilih bot pengelola untuk channel jualan mereka dari daftar bot yang tersedia di halaman `/member/channels`.

## [5.2.3] - 2025-09-04

### Fitur
- **Pendaftaran Channel via Web**: Menambahkan formulir dan logika backend pada halaman `/member/channels` agar member dapat mendaftarkan atau memperbarui channel jualan mereka langsung dari panel member di website.

## [5.2.2] - 2025-09-04

### Fitur
- **Halaman Channel Member**: Menambahkan halaman baru `/member/channels` yang memungkinkan member (penjual) untuk melihat detail channel jualan yang telah mereka daftarkan melalui bot.

## [5.2.1] - 2025-09-04

### Diperbaiki
- **Login Token Tanpa Batas Waktu**: Menghapus validasi masa berlaku token (5 menit) pada alur login member. Token sekarang tidak akan kedaluwarsa, memperbaiki masalah login yang gagal karena token dianggap hangus.

### Diubah
- **Login Token Lebih Fleksibel**: Menghapus pengecekan peran (role) dari alur validasi token di halaman web. Sekarang, semua pengguna yang memiliki token valid dapat login, tidak terbatas hanya pada 'Member'.
- **Alur Perintah /login Disatukan**: Menyatukan respon perintah `/login` di sisi bot. Semua pengguna, terlepas dari perannya, sekarang menerima satu jenis pesan dan tautan login yang konsisten.

## [5.2.0] - 2025-09-04

### Diperbaiki
- **Login Member Gagal**: Memperbaiki masalah kritis yang menghalangi member untuk login menggunakan token. Logika validasi token di `Member/LoginController.php` diganti dengan satu kueri SQL yang andal untuk memeriksa validitas token, masa berlaku, dan peran 'Member' secara bersamaan di level database.

### Diubah
- **Implementasi Ulang Panel Admin**: Panel admin telah diimplementasikan ulang dengan arsitektur multi-halaman yang lebih standar dan aman.
  - **URL**: Semua halaman admin sekarang diakses melalui prefix `/xoradmin/`.
  - **Otentikasi**: Sistem login token untuk admin telah digantikan dengan halaman login berbasis kata sandi statis (`/xoradmin/login`).
  - **Navigasi**: Panel admin sekarang menggunakan layout terpusat dengan sidebar navigasi yang terorganisir untuk semua fitur.

## [5.1.31] - 2025-09-03

### Fitur
- **Pemeriksa Skema Database Komprehensif**: Menambahkan fitur canggih di `Admin > Database > Periksa Skema` untuk membandingkan skema database live dengan file `updated_schema.sql`.
  - **Deteksi Perbedaan Total**: Alat ini sekarang tidak hanya mendeteksi tabel atau kolom yang hilang, tetapi juga mendeteksi:
    - Tabel tambahan di database live yang tidak ada di skema.
    - Kolom tambahan di tabel live yang tidak ada di skema.
    - Kolom yang definisinya (tipe data, nullability, default, dll.) tidak cocok antara database live dan skema.
  - **Generasi Query Otomatis**: Untuk setiap perbedaan yang ditemukan, sistem secara otomatis menghasilkan query SQL (`CREATE TABLE`, `DROP TABLE`, `ALTER TABLE ADD/DROP/MODIFY`) yang dapat disalin oleh admin untuk menyinkronkan database secara manual.
  - **Tampilan Laporan yang Jelas**: Halaman hasil telah didesain ulang total untuk menyajikan semua perbedaan dalam format yang terstruktur dan mudah dibaca, dipisahkan berdasarkan jenisnya.

## [5.1.30] - 2025-09-01

### Diperbaiki
- **Fatal Error `Class not found` (Global Repositories)**: Memperbaiki serangkaian error fatal `Class ... not found` yang terjadi di berbagai bagian aplikasi karena file-file Repository di `core/database/` tidak memiliki deklarasi namespace yang benar.
  - **Penyebab**: Beberapa file repository (`RoleRepository`, `PrivateChannelBotRepository`, `PrivateChannelRepository`, `SaleRepository`, `SellerSalesChannelRepository`) tidak mendeklarasikan `namespace TGBot\Database;` dan tidak mengimpor dependensi seperti `PDO` dengan benar.
  - **Solusi**: Melakukan audit dan perbaikan global pada semua file di `core/database/` untuk memastikan setiap file memiliki deklarasi `namespace` dan pernyataan `use` yang lengkap dan benar, menstabilkan proses autoloading di seluruh aplikasi.

## [5.1.29] - 2025-09-01

### Fitur
- **Fatal Error `Interface not found` (Global)**: Memperbaiki serangkaian error fatal `Interface "TGBot\Handlers\HandlerInterface" not found` yang terjadi di semua file handler.
  - **Penyebab**: File `core/handlers/HandlerInterface.php` tidak memiliki deklarasi namespace, dan semua file handler yang mengimplementasikannya (`MessageHandler`, `CallbackQueryHandler`, dll.) tidak mengimpor interface tersebut dengan benar menggunakan pernyataan `use`.
  - **Solusi**:
    1.  Menambahkan `namespace TGBot\Handlers;` dan `use TGBot\App;` ke `HandlerInterface.php`.
    2.  Menambahkan `use TGBot\Handlers\HandlerInterface;` ke semua kelas handler yang relevan untuk memastikan konsistensi dan autoloading yang benar.

## [5.1.26] - 2025-09-01

### Diperbaiki
- **Fatal Error `Class not found` (UpdateDispatcher)**: Memperbaiki fatal error `Class "TGBot\UpdateHandler" not found` di `core/UpdateDispatcher.php`.
  - **Penyebab**: File `core/UpdateDispatcher.php` tidak mengimpor kelas `UpdateHandler` dengan benar menggunakan pernyataan `use`.
  - **Solusi**: Menambahkan `use TGBot\Handlers\UpdateHandler;` ke bagian atas file `core/UpdateDispatcher.php` untuk memastikan kelas tersebut dapat ditemukan oleh autoloader.

## [5.1.25] - 2025-09-01

### Peningkatan
- **Logging Diagnostik di Webhook**: Menambahkan logging yang lebih detail di `WebhookController.php` untuk membantu mendiagnosis masalah 500 Internal Server Error yang persisten.
  - **Detail Log**: Sistem sekarang akan mencatat ID bot yang memanggil, data bot yang ditemukan di database, dan payload JSON mentah yang diterima dari Telegram. Ini akan memberikan visibilitas penuh pada alur eksekusi webhook untuk identifikasi masalah yang lebih cepat.

## [5.1.24] - 2025-09-01

### Diperbaiki
- **Fatal Error `Class not found` (Autoloader)**: Memperbaiki autoloader di `core/autoloader.php` yang gagal menemukan kelas di subdirektori karena masalah case-sensitivity pada sistem file Linux.
  - **Penyebab**: Autoloader tidak mengubah namespace menjadi huruf kecil saat membuat path file, sehingga `TGBot\Database\BotRepository` dicari di `core/Database/` padahal direktori sebenarnya adalah `core/database/`.
  - **Solusi**: Menambahkan `strtolower()` pada path relatif untuk memastikan path yang dihasilkan selalu huruf kecil, sesuai dengan struktur direktori.

## [5.1.23] - 2025-09-01

### Diperbaiki
- **Fatal Error `Class not found` (Autoloader)**: Merombak total `core/autoloader.php` untuk memperbaiki serangkaian error fatal `Class not found` yang terjadi di seluruh aplikasi, termasuk untuk `TGBot\TelegramAPI` dan `TGBot\Database\BotRepository`.
  - **Penyebab**: Logika autoloader sebelumnya tidak benar-benar mengikuti standar PSR-4. Autoloader gagal menerjemahkan namespace yang mengandung sub-direktori (seperti `TGBot\Database`) ke path file yang benar (`core/database/`) dan salah mengasumsikan semua class berada di direktori `src/` atau `core/` tingkat atas.
  - **Solusi**: Autoloader baru sekarang secara cerdas memetakan namespace ke struktur direktori. Logika baru membedakan antara *Controllers* (di `src/`) dan semua class *Core* lainnya (di `core/`), dan dengan benar mengubah `\` dari namespace ke `/` di path file. Ini memastikan bahwa class seperti `TGBot\Database\BotRepository` sekarang dapat ditemukan di `core/database/BotRepository.php`, menyelesaikan semua masalah autoloading.

## [5.1.21] - 2025-08-31

### Diperbaiki
- **Fatal Error `undefined function` (Final Fix)**: Menambahkan `require_once` untuk `core/database.php` ke dalam `core/autoloader.php`. Ini adalah perbaikan definitif untuk error `Call to undefined function get_db_connection()` yang terus-menerus terjadi.
  - **Penyebab Akar**: Fungsi `get_db_connection()` didefinisikan di `core/database.php`, tetapi file ini tidak pernah di-include oleh autoloader, sehingga tidak tersedia secara global.
  - **Solusi**: Dengan secara eksplisit me-require `core/database.php` di autoloader, fungsi koneksi database sekarang dijamin tersedia untuk semua bagian aplikasi yang membutuhkannya.



### Diperbaiki
- **Diagnostik Tambahan**: Menambahkan kode diagnostik untuk memverifikasi bahwa titik masuk aplikasi (`public/index.php` dan `run_migrations_cli.php`) dieksekusi dengan benar. Ini untuk membantu melacak masalah lingkungan server yang persisten yang menyebabkan error `undefined function`.



### Diperbaiki
- **Fatal Error `undefined function` (Workaround)**: Menambahkan `require_once` untuk `core/helpers.php` secara eksplisit di `BaseController.php` dan `AppController.php`. Ini adalah workaround untuk memastikan fungsi-fungsi helper selalu tersedia, mengatasi error fatal `Call to undefined function` yang dilaporkan oleh pengguna, yang kemungkinan disebabkan oleh masalah lingkungan server.



### Diperbaiki
- **Fatal Error `require_once` dan `undefined function`**: Melakukan refactoring besar dengan mengganti semua pemanggilan `require_once` yang berulang dengan autoloader terpusat. Ini memperbaiki serangkaian error fatal, termasuk `Class not found` dan `Call to undefined function`.
  - **Penyebab**: Ketergantungan pada `require_once` manual menyebabkan file tidak dimuat dalam urutan yang benar, dan pemanggilan fungsi global dari dalam namespace menyebabkan konflik.
  - **Solusi**:
    1.  Membuat `core/autoloader.php` baru yang secara cerdas memuat kelas dari direktori `src` dan `core` berdasarkan namespace `TGBot`.
    2.  Menghapus semua `require_once` yang berlebihan dari seluruh file controller dan file inti.
    3.  Memperbaiki semua pemanggilan fungsi helper global (seperti `get_db_connection`, `app_log`) dengan menambahkan prefix `\` untuk memanggilnya dari namespace global, menyelesaikan semua error `undefined function`.

## [5.1.17] - 2025-08-31

### Diperbaiki
- **Fatal Error Class Not Found**: Memperbaiki error fatal `Class "TGBot\\Controllers\\BaseController" not found` yang terjadi secara sporadis di berbagai controller di dalam panel admin.
  - **Penyebab**: Kurangnya autoloader terpusat (seperti Composer) menyebabkan `BaseController.php` tidak selalu dimuat sebelum controller turunan yang membutuhkannya.
  - **Solusi**: Menambahkan `require_once __DIR__ . \'/../BaseController.php\';` secara eksplisit di semua controller admin yang mewarisi `BaseController`. Ini memastikan kelas dasar selalu tersedia, menstabilkan seluruh panel admin dan mencegah error serupa di masa depan.

## [5.1.16] - 2025-08-30

### Fitur
- **Halaman Laporan Keuangan**: Menambahkan halaman "Laporan Keuangan" baru di panel admin (`/admin/reports/financial`). Halaman ini menampilkan ringkasan pendapatan (harian, mingguan, bulanan, total) serta tabel rincian pendapatan harian dan bulanan, berdasarkan data dari tabel `sales`.

## [5.1.15] - 2025-08-30

### Dokumentasi
- **Dokumentasi Struktur Database**: Menambahkan bagian baru "Struktur Database" ke dalam `DOCUMENTATION.md`. Bagian ini memberikan penjelasan rinci tentang setiap tabel utama (seperti `users`, `bots`, `media_packages`, `sales`) dan kolom-kolomnya, membantu pengembang memahami model data aplikasi.

## [5.1.14] - 2025-08-30

### Dokumentasi
- **Panduan Pembaruan (Upgrade)**: Menambahkan file `UPGRADE.md` baru. File ini menyediakan instruksi langkah-demi-langkah yang aman bagi pengguna untuk memperbarui aplikasi mereka, mencakup proses pencadangan file dan database, penggantian file inti, dan menjalankan migrasi database.

## [5.1.13] - 2025-08-30

### Dokumentasi
- **Panduan Praktik Terbaik**: Menambahkan bagian baru "💡 Praktik Terbaik (Best Practices)" ke dalam `DOCUMENTATION.md`. Bagian ini memberikan tips penting tentang manajemen keamanan (backup, password), pengelolaan konten (channel penyimpanan, deskripsi), dan pemeliharaan rutin (monitoring log, migrasi) untuk membantu pengguna mengelola bot mereka secara efisien dan aman.

## [5.1.12] - 2025-08-30

### Dokumentasi
- **Langkah Verifikasi Instalasi**: Menambahkan bagian "Langkah 7: Verifikasi Instalasi" ke dalam `DOCUMENTATION.md`. Bagian ini memberikan daftar periksa (checklist) bagi pengguna untuk memastikan semua komponen (panel admin, respons bot, koneksi database) berfungsi dengan benar setelah proses instalasi selesai.

## [5.1.11] - 2025-02-30

### Dokumentasi
- **Panduan Troubleshooting**: Menambahkan bagian "Penyelesaian Masalah Umum" (Troubleshooting) baru ke dalam `DOCUMENTATION.md`. Bagian ini memberikan solusi langkah-demi-langkah untuk masalah umum seperti bot yang tidak merespons, error 500, masalah routing URL (`.htaccess`), dan kegagalan koneksi database.

## [5.1.10] - 2025-09-05

### Dokumentasi
- **Konsolidasi Dokumentasi**: Menggabungkan file `INSTALL.md` dan `howto.md` ke dalam satu file `DOCUMENTATION.md` yang komprehensif. File-file lama dihapus untuk menyederhanakan struktur dokumentasi dan menyediakan satu sumber informasi terpusat.

## [5.1.9] - 2025-08-30

### Dokumentasi
- **Panduan Kontributor**: Menambahkan file `CONTRIBUTING.md` baru. File ini berisi panduan lengkap bagi kontributor manusia, mencakup standar kode, alur kerja kontribusi menggunakan Pull Request, format pesan commit, dan cara melaporkan bug. Tujuannya adalah untuk memudahkan kontributor baru bergabung dan menjaga konsistensi proyek.

## [5.1.8] - 2025-08-30

### Refactoring
- **Konsolidasi Panduan Kontribusi**: Menggabungkan beberapa file panduan (`GEMINI.md`, `AGENTS.md`, `.gemini/styleguide.md`) yang tumpang tindih ke dalam satu file `GEMINI.md` tunggal sebagai sumber kebenaran utama. File-file yang berlebihan telah dihapus untuk menyederhanakan dokumentasi proyek dan menghindari kebingungan.

## [5.1.7] - 2025-08-30

### Dokumentasi
- **Panduan Instalasi**: Menambahkan file `INSTALL.md` baru yang berisi panduan lengkap dan terperinci untuk menginstal dan mengkonfigurasi aplikasi dari awal, termasuk pengaturan server, database, dan webhook Telegram.

## [5.1.6] - 2025-08-30

### Peningkatan
- **Keterbacaan Kode Paginasi**: Melakukan refactoring pada logika paginasi di beberapa view admin. Variabel lokal `$currentPage` dan `$totalPages` sekarang didefinisikan di dalam blok paginasi untuk meningkatkan keterbacaan dan menghindari pengulangan, sejalan dengan prinsip DRY dan praktik terbaik kode.

## [5.1.5] - 2025-08-30

### Peningkatan
- **Konsistensi Gaya Kode**: Memperbarui beberapa file view untuk menggunakan tag echo pendek `<?= ... ?>` daripada `<?php echo ...; ?>`. Perubahan ini menyelaraskan kode dengan standar PSR-12 dan praktik yang sudah ada di seluruh proyek, meningkatkan keterbacaan dan konsistensi.

## [5.1.4] - 2025-08-30

### Peningkatan
- **Konsistensi Kode View**: Melakukan refactoring pada `debug_feed/index.php` untuk memastikan konsistensi dengan view lain. Variabel paginasi sekarang diakses langsung dari array `$data['pagination']` daripada mendefinisikan variabel lokal, sejalan dengan praktik terbaik di seluruh aplikasi.

## [5.1.3] - 2025-08-30

### Peningkatan
- **Konsistensi Tipe Exception**: Mengubah tipe `Exception` menjadi `\RuntimeException` di `StorageChannelController` saat terjadi kegagalan penambahan bot ke channel. Perubahan ini membuat penanganan error lebih konsisten dengan bagian lain dari file yang sudah menggunakan `\RuntimeException` untuk error saat runtime.

## [5.1.2] - 2025-08-30

### Diubah (Keamanan)
- **Refactoring View**: Menghapus penggunaan fungsi `extract()` yang berisiko dari `AppController`. Semua variabel sekarang secara eksplisit diakses melalui array `$data` di dalam view (misalnya, `$data['nama_variabel']`). Perubahan ini meningkatkan keamanan dengan mencegah potensi *variable overwriting* dan membuat alur data ke dalam template lebih jelas dan mudah untuk di-debug.

## [5.1.1] - 2025-08-29

### Diperbaiki
- **Penanganan Error Verifikasi Bot**: Menambahkan pemeriksaan error setelah memanggil `addBotToChannel` di dalam `StorageChannelController`. Sebelumnya, jika penambahan bot ke channel gagal di database, sistem akan tetap melanjutkan ke proses verifikasi, yang kemungkinan besar juga akan gagal. Sekarang, sebuah `Exception` akan dilempar lebih awal, memberikan pesan error yang jelas dan mencegah kegagalan berantai.

## [5.1.0] - 2025-08-29

### Peningkatan

- **Pesan Status yang Lebih Akurat**: Memperbaiki logika penambahan peran di `RoleController`. Sistem sekarang memberikan pesan yang jelas kepada pengguna jika peran berhasil ditambahkan, jika peran tersebut sudah ada, atau jika terjadi kesalahan, daripada hanya menampilkan pesan sukses umum.
- **Perbaikan Bug Variabel**: Memperbaiki bug di `RoleController` di mana variabel `$roleRepo` dipanggil alih-alih properti kelas `$this->roleRepo`, yang akan menyebabkan error fatal.
- **Pesan Status Hapus yang Lebih Akurat**: Mengikuti pembaruan sebelumnya, logika penghapusan peran di `RoleController` kini juga telah diperbaiki. Sistem sekarang memberikan pesan yang jelas kepada pengguna jika peran berhasil dihapus, jika peran tidak ditemukan (sehingga tidak ada yang dihapus), atau jika terjadi kesalahan database.

## [5.0.8] - 2025-08-29

### Diperbaiki

- **Penanganan Kesalahan Database**: Memperbaiki blok `catch` yang kosong di `BaseController`. Sebelumnya, kesalahan saat mengambil nama pengguna bot untuk halaman "Akses Ditolak" akan diabaikan secara diam-diam. Sekarang, kesalahan tersebut dicatat menggunakan `app_log()` untuk memastikan semua masalah dapat ditelusuri. (#303)
- **Penanganan Perintah di Grup**: Memperbaiki bug di `MessageHandler` yang menyebabkan bot tidak merespons perintah yang dikirim di dalam grup jika perintah tersebut menyertakan nama bot (misalnya, `/start@nama_bot_saya`). Logika parsing sekarang dengan benar memisahkan perintah dari nama bot, memastikan perintah dikenali dan diproses dengan benar di semua jenis chat.


## [5.0.7] - 2025-08-29

### Peningkatan
- **Refactoring Controller**: Memindahkan inisialisasi `RoleRepository` ke dalam `__construct` di `RoleController`. Ini menghilangkan duplikasi kode di setiap metode dan meningkatkan pemeliharaan dengan mengikuti prinsip DRY.

## [5.0.6] - 2025-08-29

### Diperbaiki (Keamanan)
- **Validasi Input**: Memperkuat keamanan di `RoleController` dengan mengganti pemeriksaan `empty()` yang tidak aman dengan `filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT)`. Ini memastikan bahwa hanya nilai integer yang valid yang diproses saat menghapus peran, mencegah potensi manipulasi input.

## [5.0.5] - 2025-08-29

### Diperbaiki (Keamanan)
- **Sanitasi Input**: Memperkuat keamanan di `RoleController` dengan mengganti `trim()` sederhana dengan `htmlspecialchars()` untuk membersihkan input `role_name` dari pengguna. Ini mencegah potensi serangan XSS.

## [5.0.4] - 2025-08-29

### Peningkatan
- **Refactoring Otentikasi**: Memusatkan logika pemeriksaan otentikasi yang berulang di `XorAdminController.php` ke dalam satu metode privat `requireAuth()`. Ini mengurangi duplikasi kode dan meningkatkan keterbacaan serta pemeliharaan.

## [5.0.3] - 2025-08-29

### Diperbaiki
- **Kode Redundan Dihapus**: Menghapus pemeriksaan `if ($result === false)` yang tidak perlu dari `XorAdminController.php`. Pemeriksaan ini tidak akan pernah tercapai karena `TelegramAPI` dirancang untuk melempar `Exception` saat terjadi kegagalan, yang sudah ditangani oleh blok `try-catch` di sekitarnya.

## [5.0.2] - 2025-08-29

### Diperbaiki (Keamanan)
- **Perbandingan Password Aman**: Mengganti perbandingan password yang rentan terhadap serangan waktu (`===`) dengan fungsi `hash_equals()` di `XorAdminController.php`. Ini memastikan perbandingan string dilakukan dalam waktu konstan, yang merupakan praktik keamanan standar untuk melindungi rahasia.

## [5.0.1] - 2025-08-29

### Peningkatan
- **Halaman Akses Ditolak yang Lebih Baik**: Mengganti pesan "Akses Ditolak" berbasis teks biasa dengan halaman HTML yang ramah pengguna. Halaman baru ini memberikan instruksi yang jelas kepada pengguna untuk login kembali melalui bot Telegram dan secara dinamis menyertakan tautan ke bot jika tersedia.

## [5.0.0] - 2025-08-28

### Diubah (Refactoring Arsitektur)
- **Implementasi Pola Model-View-Controller-Router (MVCR)**: Memulai refactoring besar-besaran pada arsitektur aplikasi dari struktur PHP native menjadi pola MVCR untuk meningkatkan modularitas, keterbacaan, dan kemudahan pemeliharaan.
  - **Router & Front Controller**: Memperkenalkan `public/index.php` sebagai satu-satunya titik masuk (front controller) dan `core/Router.php` untuk menangani semua permintaan web. Ini memungkinkan penggunaan URL yang bersih (misalnya, `/admin/dashboard`).
  - **Struktur Direktori Baru**: Membuat struktur direktori baru (`src/Controllers`, `src/Views`, `src/Models`, `public`) untuk memisahkan tanggung jawab dengan jelas.
  - **BaseController**: Membuat `BaseController` yang menangani logika umum seperti otentikasi sesi admin dan rendering view, yang akan diwarisi oleh semua controller lain.
  - **Refactor Dasbor Admin**: Halaman pertama yang berhasil di-refactor adalah Dasbor Percakapan Admin (`admin/index.php`). Logika bisnisnya sekarang berada di `Admin/DashboardController.php`, dan presentasinya di `src/Views/admin/dashboard/index.php` dengan layout terpusat.
- **Lanjutan Refactoring**: Melanjutkan refactoring dengan memigrasikan halaman "Kelola Bot" (`admin/bots.php`) dan "Edit Bot" (`admin/edit_bot.php`) ke `BotController` baru. Juga memulai refactoring area member dengan membuat `MemberBaseController` dan memigrasikan halaman "Dasbor Member" (`member/dashboard.php`).
- **Alur Login Member**: Menyelesaikan refactoring area member dengan memigrasikan halaman login (`member/index.php`) ke `Member/LoginController` yang baru, membuat alur otentikasi member sepenuhnya dikelola oleh arsitektur MVCR.
- **Manajemen Pengguna**: Halaman manajemen pengguna (`admin/users.php`) telah dimigrasikan ke `UserController`, mendukung fungsionalitas pencarian, pengurutan, dan paginasi dalam arsitektur baru.
- **Manajemen Konten Member**: Melanjutkan refactoring area member dengan memigrasikan halaman "Konten Saya" (`member/my_content.php`) dan "Edit Paket" (`member/edit_package.php`) ke dalam `ContentController`.
- **Halaman Chat Admin**: Memigrasikan halaman chat admin (`admin/chat.php`) dan fungsionalitas hapus pesan ke `ChatController`, membuat alur kerja inti ini sesuai dengan arsitektur MVCR.
- **Halaman Chat Channel**: Melengkapi refactoring fitur chat dengan memigrasikan halaman chat channel (`admin/channel_chat.php`) ke metode baru di dalam `ChatController`.
- **Penyelesaian Area Member**: Menyelesaikan semua halaman di area member (`channels.php`, `purchased.php`, `sold.php`, `view_package.php`) dengan memigrasikannya ke dalam controller dan view yang sesuai.
- **Lanjutan Refactor Admin**: Melanjutkan migrasi area admin dengan merefaktor halaman 'Manajemen Konten' (`admin/packages.php`).
- **Halaman Log Admin**: Memigrasikan semua halaman penampil log (`logs.php`, `media_logs.php`, `telegram_logs.php`) ke dalam `LogController` baru untuk konsistensi.
- **Migrasi AJAX ke API Controller**: Memigrasikan semua endpoint AJAX lama (misalnya, `api/update_user_roles.php`, `webhook_manager.php`, `package_manager.php`) ke dalam metode API yang sesuai di dalam controller yang ada, membuat semua interaksi backend konsisten dengan pola MVCR.
- **Lanjutan Refactor Admin**: Melanjutkan migrasi dengan merefaktor halaman 'Analitik' (`admin/analytics.php`) dan 'Manajemen Saldo' (`admin/balance.php`) beserta endpoint-endpoint API terkaitnya.
- **Penyelesaian Refactor Admin**: Menyelesaikan migrasi penuh area admin dengan merefaktor halaman 'Channel Penyimpanan' (`admin/channels.php`) dan 'Manajemen Peran' (`admin/roles.php`), membuat semua fungsionalitas admin kini berjalan di bawah arsitektur MVCR yang baru.
- **Penyelesaian Refactoring Total**: Menyelesaikan migrasi semua sisa halaman legacy, termasuk halaman admin (`database`, `sales_channels`, `api_test`, `debug_feed`), halaman root (`login_choice`, `xoradmin`), dan yang paling penting, titik masuk utama `webhook.php`. Seluruh fungsionalitas web dan bot sekarang berjalan sepenuhnya di bawah arsitektur MVCR yang baru. Direktori `admin/` dan `partials/` yang usang telah dihapus.

## [4.7.0] - 2025-08-28

### Fitur
- **Manajemen & Verifikasi Bot untuk Channel Penyimpanan**: Merombak total halaman "Channel Penyimpanan" (`admin/channels.php`) untuk memperkenalkan alur kerja berbasis wizard untuk mengelola dan memverifikasi bot.
  - **Hubungan Banyak-ke-Banyak**: Sistem sekarang mendukung beberapa bot yang dikaitkan dengan satu channel penyimpanan, yang dikelola melalui tabel database `private_channel_bots` baru.
  - **Modal Manajemen Interaktif**: Mengklik tombol "Kelola Bot" baru pada sebuah channel akan membuka modal di mana admin dapat menambah, menghapus, dan memverifikasi bot untuk channel tersebut.
  - **Verifikasi Otomatis via API**: Fitur "Verifikasi" secara otomatis memanggil API Telegram untuk memeriksa apakah bot adalah admin di channel. Jika ya, statusnya diperbarui di UI.
  - **Endpoint API Modular**: Menambahkan beberapa endpoint API baru (`get_channel_bots.php`, `add_bot_to_channel.php`, `remove_bot_from_channel.php`, `verify_channel_bot.php`) untuk mendukung fungsionalitas modal secara dinamis.

## [4.6.2] - 2025-08-27

### Diperbaiki
- **Riwayat Chat Kosong Karena Integer Overflow**: Memperbaiki bug kritis di `admin/chat.php` yang menyebabkan riwayat chat tidak muncul untuk ID pengguna atau bot yang lebih besar dari batas 32-bit integer (sekitar 2.14 miliar).
  - **Penyebab**: ID pengguna dan bot diikat ke kueri SQL menggunakan `PDO::PARAM_INT`. Ketika ID aktual (seperti 7.6 miliar) melebihi batas maksimum integer 32-bit, PDO akan memotongnya menjadi nilai yang salah sebelum mengirim kueri ke database, sehingga tidak ada baris yang ditemukan. Kueri `COUNT(*)` berhasil karena menggunakan metode `execute()` yang berbeda yang tidak memaksa tipe data.
  - **Solusi**: Mengubah tipe parameter untuk `user_id` dan `bot_id` dari `PDO::PARAM_INT` menjadi `PDO::PARAM_STR` di `admin/chat.php`. Ini memastikan ID besar dikirim sebagai string dan tidak terpotong, memungkinkan database untuk mencocokkannya dengan benar terhadap kolom `BIGINT`.

## [4.6.1] - 2025-08-27

### Diperbaiki
- **Fatal Error di Halaman Channel Jualan**: Memperbaiki error `Undefined array key "bot_id"` dan `TypeError` di halaman `admin/sales_channels.php`.
  - **Penyebab**: Kueri database tidak menyertakan `bot_id` dalam hasil, yang menyebabkan error saat mencoba mengambil token bot untuk panggilan API.
  - **Solusi**: Menambahkan `ssc.bot_id` ke dalam `SELECT` pada kueri SQL untuk memastikan data yang diperlukan tersedia.

## [4.6.0] - 2025-08-27

### Fitur
- **Halaman Manajemen Channel Jualan**: Menambahkan halaman baru `admin/sales_channels.php` untuk menampilkan daftar semua channel jualan yang didaftarkan oleh penjual.
  - **Tampilan Komprehensif**: Menampilkan tabel dengan informasi lengkap termasuk nama channel, nama grup diskusi, pemilik channel (penjual), dan bot yang terhubung.
  - **Integrasi API**: Secara dinamis mengambil nama channel dan grup dari API Telegram untuk ditampilkan di samping ID numeriknya.
  - **Navigasi**: Menambahkan tautan "Channel Jualan" baru ke sidebar admin untuk akses mudah, dan memperjelas tautan lama menjadi "Channel Penyimpanan".

## [4.5.2] - 2025-08-27

### Diperbaiki
- **Fatal Error di Halaman Paket Admin**: Memperbaiki error `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'u.telegram_id' in 'ON'` yang terjadi saat mengakses halaman manajemen paket di panel admin.
  - **Penyebab**: Kueri `findAll` di `PackageRepository.php` salah melakukan JOIN pada `users` menggunakan `u.telegram_id` yang tidak ada.
  - **Solusi**: Mengubah kondisi JOIN menjadi `u.id` agar sesuai dengan skema database.

## [4.5.1] - 2025-08-27

### Diperbaiki
- **Inkonsistensi Kolom ID Pengguna (`id` vs `telegram_id`)**: Memperbaiki error `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'u.telegram_id'` dan masalah terkait di seluruh aplikasi.
  - **Penyebab**: Sebagian besar kode masih menggunakan `telegram_id` untuk merujuk pada ID pengguna, padahal skema database final (`updated_schema.sql`) menggunakan `id` sebagai primary key untuk tabel `users`.
  - **Solusi**: Melakukan refactoring global untuk mengganti semua referensi ke kolom `telegram_id` pengguna dengan `id`. Ini termasuk perbaikan pada query SQL, nama variabel, dan parameter fungsi di direktori `admin`, `core`, dan `member`.
  - **Perbaikan Tambahan**: Menonaktifkan atau memperbaiki file migrasi lama (`002_...`, `018_...`) dan `setup.sql` untuk memastikan konsistensi skema bagi instalasi baru.

## [4.5.0] - 2025-08-27

### Fitur
- **Reset Database dari File SQL**: Menambahkan fitur baru di halaman `admin/database.php` yang memungkinkan admin untuk me-reset database menggunakan file skema SQL yang dipilih.
  - **Dropdown Pilihan File**: Mengganti tombol "Bersihkan Data" dengan dropdown yang berisi `updated_schema.sql` (disarankan) dan `setup.sql` (lama).
  - **Logika Reset Penuh**: Aksi ini akan menghapus semua tabel yang ada dan menjalankan skrip dari file yang dipilih untuk membuat ulang seluruh skema database.

## [4.4.2] - 2025-08-27

### Diperbaiki
- **Fatal Error di Dasbor Percakapan**: Memperbaiki error `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'telegram_bot_id'` di halaman utama admin (`admin/index.php`).
  - **Penyebab**: Kode di `admin/index.php` mencoba mengambil kolom `telegram_bot_id` dari tabel `bots`, padahal skema database yang benar (`updated_schema.sql`) menamai kolom tersebut `id`.
  - **Solusi**: Mengubah semua referensi ke `telegram_bot_id` menjadi `id` di `admin/index.php`, termasuk query SQL dan variabel terkait, agar sesuai dengan struktur database.

## [4.4.1] - 2025-08-27

### Diperbaiki
- **Fatal Error di Halaman Pilihan Login**: Memperbaiki error `SQLSTATE[42S02]: Base table or view not found` pada `login_choice.php`.
  - **Penyebab**: Halaman ini masih mencoba mengakses tabel `members` yang sudah dihapus dan digabungkan ke dalam tabel `users` pada refactoring sebelumnya.
  - **Solusi**: Mengubah query database di `login_choice.php` untuk mengambil data token langsung dari tabel `users`.

## [4.4.0] - 2025-08-27

### Fitur
- **Tombol "Jadikan Admin" di XOR Admin Panel**: Menambahkan fungsionalitas sederhana untuk memberikan peran Admin kepada pengguna dari panel `xoradmin.php`.
  - **Tampilan Baru**: Menambahkan tab "Manajemen Peran" yang menampilkan daftar pengguna dan peran mereka saat ini.
  - **Aksi Cepat**: Untuk setiap pengguna yang belum menjadi admin, sebuah tombol "Jadikan Admin" tersedia. Mengklik tombol ini akan langsung memberikan peran Admin kepada pengguna tersebut.
  - **Backend**: Logika ini didukung oleh endpoint API `make_admin` baru di `xoradminapi.php`.

## [4.3.3] - 2025-08-27

### Diperbaiki
- **Inkonsistensi ID Pengguna di Seluruh Kode**: Memperbaiki serangkaian error fatal (`500 Internal Server Error`, `Column not found`, `Invalid Token`) yang disebabkan oleh penggunaan nama kolom yang tidak konsisten untuk ID pengguna (`id` vs. `telegram_id`) di seluruh basis kode.
  - **Penyebab**: Sebagian kode telah diperbarui untuk menggunakan skema database baru (dengan `telegram_id` sebagai primary key), tetapi database yang aktif dan sebagian besar kode lainnya masih menggunakan skema lama (dengan `id` sebagai primary key). Hal ini menyebabkan kegagalan query SQL di banyak bagian aplikasi.
  - **Solusi**: Melakukan audit dan perbaikan massal di seluruh basis kode untuk secara konsisten menggunakan kolom `id` sebagai primary key pengguna. File yang diperbaiki antara lain:
    - `core/UpdateDispatcher.php`: Menggunakan ID bot internal yang benar.
    - `core/database/UserRepository.php`: Mengoreksi query `INSERT` pengguna baru.
    - `core/handlers/MessageHandler.php`: Menyelaraskan semua penggunaan ID pengguna.
    - `core/handlers/CallbackQueryHandler.php`: Menyelaraskan semua penggunaan ID pengguna.
    - `core/handlers/MediaHandler.php`: Menggunakan ID pengguna yang benar saat menyimpan media.
    - `member/index.php`, `member/dashboard.php`: Memperbaiki alur login dan tampilan dasbor member.
    - `admin/auth.php`, `admin/chat.php`, `admin/balance.php`, dan file terkait lainnya: Menyelaraskan semua query dan logika di panel admin.

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
    2.  Mengubah semua `parse_mode` dari `\'MarkdownV2\'` menjadi `\'Markdown\'` di semua file handler (`MessageHandler`, `CallbackQueryHandler`, `ChannelPostHandler`) dan `webhook.php`.
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
  - **Aksi**: Menghapus semua data dari tabel `users`, `sales`, `media_packages`, `media_files`, dan tabel terkait lainnya.
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
  - **Penjual sekarang hanya perlu me-reply media (foto/video/album) dengan perintah `/sell`.
  - **Bot akan secara otomatis menggunakan caption dari media tersebut sebagai deskripsi produk.
  - **Alur multi-langkah yang lama (meminta media, lalu deskripsi) telah dihapus, membuat proses penjualan lebih cepat dan sederhana.

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