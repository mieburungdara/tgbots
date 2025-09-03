## Alur Fitur `/rate`

1. **User Mengirim Media**

   * User harus **reply ke media** (photo/video/media group) miliknya sendiri.
   * User hanya bisa kirim **1 postingan dalam satu waktu** (nda boleh bikin posting baru sebelum yang lama selesai).

2. **Pemilihan Kategori Awal**

   * Setelah user reply dengan perintah `/rate`, bot membalas postingan user dengan tombol kategori:

     * `Cewek`
     * `Cowok`
   * User **wajib pilih kategori**.

3. **Konfirmasi Post**

   * Setelah kategori dipilih, bot mengirim **preview postingan** kembali ke user:

     * Quote berisi info tambahan: `id user`, jenis & jumlah media (`1P`, `3V`, `1P2V`).
     * Caption asli tetap ditampilkan (tanpa quote).
   * Inline keyboard:

     * `✅ Konfirmasi`
     * `❌ Batal`

4. **Jika User Membatalkan**

   * Post batal diproses, **tidak diteruskan ke admin**.
   * Auto aprove **tidak berlaku** karena posting belum masuk ke moderasi.
   * User bebas kirim ulang post baru.

5. **Jika User Mengonfirmasi**

   * Post diteruskan ke **channel private admin (moderasi)** sesuai kategori (`cewek` / `cowok`).
   * Format di channel admin: caption asli + info tambahan (id user, jenis & jumlah media).
   * Inline keyboard untuk admin:

     * `Reject` → alasan cepat: `Iklan`, `Judi`, `Kekerasan`, `Tidak Sesuai Kategori`, `Duplikat`.
     * `Ban User` → alasan cepat: `Iklan`, `Judi Online`, `Spam`, `Kekerasan`.

6. **Auto Aprove (Cronjob)**

   * Cronjob hanya berlaku pada post yg **sudah dikirim ke channel private admin**.
   * Jika dalam **5 menit admin tidak merespon**, post otomatis **approved**.
   * Post diteruskan ke channel publik sesuai kategori.

7. **Reject oleh Admin**

   * Post tetap tersimpan di channel private admin (arsip), tapi **tidak dikirim ke publik**.
   * User menerima notifikasi alasan reject.

8. **Ban oleh Admin**

   * User langsung diblokir dari bot.
   * User menerima notifikasi alasan ban.

9. **Jika Post Diapprove (manual atau auto)**

   * Post dikirim ke channel publik sesuai kategori.
   * Bot mengirim **notifikasi pribadi ke user**:

     * Status posting diterima.
     * Link ke postingan publik.
     * Inline keyboard untuk user: `Tarik Post`.

       * Jika ditekan, post akan dihapus dari channel publik, **tapi arsip tetap ada di channel admin**.

---

## Alur Fitur `/tanya`

1. User reply ke teks/media miliknya dengan perintah `/tanya`.
2. Bot menampilkan pilihan kategori (misalnya: `Mutualan`, `Tanya`, `Dll`).
3. Setelah kategori dipilih, bot mengirim **preview + aturan singkat** (dilarang iklan, judi, kekerasan).
4. User diberikan tombol `✅ Konfirmasi` & `❌ Batal`.
5. Jika konfirmasi:

   * Post masuk ke channel private admin khusus `/tanya`.
   * Flow sama seperti `/rate`: bisa `Reject`, `Ban User`, atau auto aprove lewat cronjob 5 menit.
6. Jika approve:

   * Post diteruskan ke channel publik khusus `/tanya`.
   * Bot kirim **notifikasi ke user** berisi link postingan + tombol `Tarik Post`.

---

## Ringkasan Penting

* Cronjob **hanya berlaku** untuk post yg sudah masuk channel private admin, bukan sebelum user konfirmasi.
* User dapat **membatalkan sebelum konfirmasi** (post tidak masuk ke admin).
* Setelah approve (manual/auto), **notifikasi pribadi ke user** selalu menyertakan tombol `Tarik Post`.
* Tombol `Tarik Post` **tidak muncul di channel publik**, hanya ada di pesan pribadi user.
* Post yg ditarik dihapus dari publik, tapi **tetap tersimpan di channel admin** sebagai arsip.

---
