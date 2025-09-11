# TODO LIST

Berikut adalah daftar fitur atau perbaikan yang perlu dipertimbangkan untuk pengembangan di masa depan:

- **Sistem Autentikasi Admin**:
  - Membuat sistem login untuk admin.
  - Setiap admin memiliki akun sendiri.
  - Ini akan memungkinkan pelacakan aksi spesifik per admin (misalnya, siapa yang mengubah saldo pengguna).

- **Peningkatan Halaman Analitik**:
  - Menambahkan lebih banyak metrik dan visualisasi data.
  - Filter berdasarkan rentang tanggal.

- **Manajemen Notifikasi**:
  - Pengaturan untuk mengaktifkan/menonaktifkan notifikasi tertentu dari bot.


Akses Ditolak. Silakan login melalui bot Telegram menggunakan perintah /login.
buat agar yg tak punya hak akses diarahkan ke halaman tertenut yg mengatakan jika perlu login atau sesi anda berakhir, gunakan bot dibawah ini untuk login


tambahkan kolom user last interaction pada table users, untuk mengetahui kapan terakhir kali user aktif dan berinteraksi dengan bot

pada dor admin, tambahkan fitur manajemen hak akses setiap role

1. Paket Berlangganan (Subscription-based Packages): Alih-alih
      menjual konten sekali beli, perkenalkan tipe paket baru:
      "Berlangganan". Penjual bisa menetapkan harga per bulan (misal:
      Rp 50.000/bulan). Saat pengguna membeli, mereka akan mendapatkan
       akses ke semua konten yang ditambahkan penjual ke paket
      tersebut selama periode langganan aktif. Ini memerlukan tabel
      baru untuk melacak status langganan (subscriptions) dan cron job
       untuk memeriksa dan menonaktifkan akses saat langganan
      berakhir.

   2. Analitik Per Paket untuk Penjual: Saat ini, penjual hanya bisa
      melihat statistik penjualan agregat melalui /me. Kita bisa
      tingkatkan perintah /konten <ID_PAKET> untuk penjual. Selain
      menampilkan detail dasar, bot bisa menambahkan bagian analitik
      yang lebih dalam untuk paket tersebut, seperti: "Dilihat oleh:
      150 pengguna", "Upaya tawar: 12 kali", "Tingkat konversi: 5%".
      Ini memberikan data berharga bagi penjual untuk strategi harga
      dan promosi mereka.

   3. Fitur "Hadiahkan" (Gifting): Di samping tombol "Beli" dan
      "Tawar", tambahkan tombol "🎁 Hadiahkan". Saat ditekan, bot akan
       meminta pengguna untuk memasukkan @username teman yang ingin
      mereka beri hadiah. Setelah pembayaran berhasil, bot akan
      mengirim pesan ke teman tersebut yang berisi: "Selamat! Anda
      menerima hadiah konten NAMA_KONTEN dari @username_pengirim. Klik
       di sini untuk melihatnya." Ini membuka model bisnis baru dan
      mendorong penyebaran konten secara viral.