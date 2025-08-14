# Panduan Penggunaan Bot Marketplace

Selamat datang di panduan Bot Marketplace! Dokumen ini akan menjelaskan cara menggunakan berbagai fitur yang tersedia, mulai dari menjual konten hingga melihat item yang sudah dibeli.

## 1. Menjadi Penjual

Sebelum Anda dapat menjual, Anda harus terdaftar sebagai penjual. Proses ini otomatis dan hanya perlu dilakukan sekali.

- **Langkah 1:** Lakukan perintah `/sell` untuk pertama kalinya.
- **Langkah 2:** Bot akan bertanya apakah Anda ingin mendaftar sebagai penjual. Tekan tombol **"Ya, Daftar Sekarang"**.
- **Langkah 3:** Bot akan memberikan Anda **ID Penjual Publik** yang unik (contoh: `ABCD`). ID ini akan digunakan untuk membuat ID unik untuk setiap konten yang Anda jual.

Setelah terdaftar, Anda dapat mulai menjual.

## 2. Menjual Konten

Proses menjual konten dirancang agar cepat dan mudah.

### A. Menjual Item Tunggal atau Satu Album

Ini adalah cara menjual yang paling umum.

- **Langkah 1:** Kirim media (foto, video, atau album/media group) ke chat bot.
- **Langkah 2:** **Reply** (balas) media yang baru saja Anda kirim dengan perintah `/sell`.
- **Langkah 3:** Bot akan meminta Anda untuk memasukkan harga. Kirim harga dalam bentuk angka (contoh: `50000`).
- **Selesai!** Paket Anda sekarang tersedia untuk dijual dengan ID unik (contoh: `ABCD_0001`).

**Catatan Penting:**
- **Deskripsi:** Caption (teks) dari media yang Anda reply akan secara otomatis digunakan sebagai deskripsi produk. Jika Anda menjual album, bot akan secara cerdas mencari caption dari salah satu item di dalam album tersebut.
- **Mengedit Deskripsi:** Jika Anda mengedit caption media asli setelah paket dibuat, deskripsi produk di bot akan **otomatis diperbarui**.

### B. Membuat Paket Besar (Lebih dari 10 Media)

Telegram memiliki batas 10 item per media group. Untuk menjual paket yang lebih besar, Anda bisa menggunakan perintah `/addmedia`.

- **Langkah 1:** Mulai proses penjualan seperti biasa dengan me-reply media pertama (atau album pertama) dengan `/sell`.
- **Langkah 2:** **Jangan masukkan harga dulu.** Alih-alih, kirim media atau album berikutnya yang ingin Anda tambahkan ke paket.
- **Langkah 3:** Reply media/album tambahan tersebut dengan perintah `/addmedia`. Anda bisa mengulangi langkah ini beberapa kali untuk menambahkan lebih banyak media.
- **Langkah 4:** Setelah semua media ditambahkan, kirimkan harga untuk menyelesaikan proses. Semua media yang Anda tambahkan akan digabung menjadi satu paket besar.

## 3. Mengedit Paket yang Sudah Ada

Anda dapat menambahkan media baru ke paket yang sudah Anda jual.

- **Langkah 1:** Kirim media atau album baru yang ingin Anda tambahkan.
- **Langkah 2:** Reply media/album baru tersebut dengan perintah `/addmedia <ID_PAKET>`, di mana `<ID_PAKET>` adalah ID dari paket yang ingin Anda edit (contoh: `/addmedia ABCD_0001`).
- **Selesai!** Media baru akan ditambahkan ke paket yang sudah ada.

## 4. Melihat Konten

Baik sebagai penjual maupun pembeli, Anda dapat melihat konten yang Anda miliki.

- **Langkah 1:** Gunakan perintah `/konten <ID_PAKET>` (contoh: `/konten ABCD_0001`).
- **Langkah 2:** Bot akan menampilkan pratinjau konten. Jika Anda memiliki akses (sebagai penjual atau pembeli), Anda akan melihat tombol **"Lihat Selengkapnya 📂"**.
- **Langkah 3:** Tekan tombol tersebut untuk masuk ke mode penampil konten.

### Navigasi Konten (Pagination)

Untuk memudahkan melihat paket besar, konten ditampilkan per halaman. Setiap "halaman" adalah satu album atau satu media tunggal.

- Gunakan tombol bernomor `[1] [2] [3]...` untuk melompat langsung ke halaman (album/media) yang diinginkan.
- Nomor halaman yang sedang Anda lihat akan ditandai secara khusus (contoh: `- 2 -`).
