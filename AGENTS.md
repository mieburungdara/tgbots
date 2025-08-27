# Do & Don’t Guidelines

## ✅ Do

1. Gunakan **bahasa pemrograman PHP**.
2. Tulis semua instruksi dan jawaban dalam **bahasa Indonesia**.
3. Selalu **perbarui `CHANGELOG.md`** setiap ada pembaruan, perubahan, atau perbaikan.
4. Pastikan kode **selalu dibungkus `try-catch`** untuk menangani dan mencatat error potensial.
5. Sesuaikan solusi agar **cocok dengan lingkungan shared hosting**.
6. Setelah perubahan diverifikasi, **kirim commit** dengan pesan jelas dalam bahasa Indonesia.
7. Jawaban harus **singkat, jelas, langsung ke akar masalah**.
8. Jika penyebab masalah tidak jelas, berikan **beberapa kemungkinan penyebab & solusi alternatif**.
9. Selalu **sarankan validasi tambahan atau optimasi** agar bug serupa tidak terulang.
10. **Lakukan publish** agar kode bisa langsung dicek.
11. Selalu lakukan pemeriksaan menyeluruh pada seluruh basis kode setiap kali ada proses generate atau perbaikan, bukan hanya di satu file.
12. Identifikasi pola masalah secara global (misalnya inkonsistensi antar file), lalu langsung perbaiki secara sistematis.
13. Selalu lakukan pemeriksaan menyeluruh setiap kali proses generate/perbaikan → pastikan semua file terkait ikut diperiksa (tidak boleh ada yg terlewat).
14. Jika ada error tambahan, langsung analisis global untuk mencari potensi file lain yg mungkin terdampak.

---

## ❌ Don’t

1. **Jangan melakukan pengujian** (pengujian dilakukan secara manual di luar agent).
2. **Jangan meminta maaf** atau menyalahkan diri sendiri.
3. **Jangan menggunakan kalimat basa-basi**.
4. **Jangan menuliskan janji tindakan** seperti “akan mengerjakan” → langsung tampilkan langkah perbaikan yang bisa dipakai.
5. **Jangan membuat asumsi tunggal tanpa dasar** → jika tidak pasti, berikan beberapa opsi solusi.
6. **Jangan menulis kalimat retrospektif seperti “Seharusnya saya melakukan pemeriksaan sejak awal”.
7. **Jangan berhenti di satu titik error saja → selalu periksa keseluruhan project.
8. **Jangan meminta maaf** atau menyalahkan diri sendiri.
9. **Jangan menyalahkan kelalaian** seperti “melewatkan file” → cukup nyatakan fakta teknis.
10. **Jangan membuat rencana** (misalnya: “akan memperbaiki nanti”, “akan membuat rencana baru”).
11. **Jangan berhenti di error saat ini saja** → selalu cek apakah ada file lain yg mungkin bermasalah agar masalah tuntas sekali jalan.

---
