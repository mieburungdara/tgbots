Buatkan alur berikut:

1. **Inline Keyboard "Post ke Channel"**

   * Saat penjual menekan tombol inline keyboard `"post ke channel"`, bot akan mengirim postingan ke **channel yang sudah ditentukan** menggunakan `sendMessage` atau `sendPhoto` (sesuai jenis konten).
   * Setelah sukses, simpan `message_id` dari hasil response API Telegram. Contoh response:

     ```json
     {
       "ok": true,
       "result": {
         "message_id": 33,
         "chat": {
           "id": -1002831430620,
           "title": "test channel bot",
           "username": "nyobachannelbuatbot",
           "type": "channel"
         },
         "date": 1755573201,
         "text": "test 1"
       }
     }
     ```
   * Data `message_id` ini harus dicatat dalam database atau memory untuk dicocokkan nanti.

2. **Menerima Update dari Group Diskusi**

   * Bot akan menerima update dari **group diskusi yang terhubung ke channel**. Contoh payload:

     ```json
     {
       "update_id": 844539704,
       "message": {
         "message_id": 48,
         "from": {
           "id": 777000,
           "is_bot": false,
           "first_name": "Telegram"
         },
         "sender_chat": {
           "id": -1002831430620,
           "title": "test channel bot",
           "username": "nyobachannelbuatbot",
           "type": "channel"
         },
         "chat": {
           "id": -1002768803723,
           "title": "test channel bot Chat",
           "type": "supergroup"
         },
         "date": 1755575735,
         "forward_origin": {
           "type": "channel",
           "chat": {
             "id": -1002831430620,
             "title": "test channel bot",
             "username": "nyobachannelbuatbot",
             "type": "channel"
           },
           "message_id": 33,
           "date": 1755575732
         },
         "is_automatic_forward": true,
         "forward_from_message_id": 33,
         "caption": "✨ Konten Baru Tersedia ✨\n\nHarga: Rp 1.000"
       }
     }
     ```

3. **Cek Kecocokan Forward Origin**

   * Jika pada `update.message.forward_origin.chat.id` berasal dari **channel bot** DAN
     `forward_origin.message_id` **sama dengan** `message_id` yang sebelumnya diposting ke channel, maka bot melakukan aksi lanjut.

4. **Reply di Group Diskusi dengan Inline Keyboard "Beli Sekarang"**

   * Bot membalas otomatis di group diskusi (`chat.id` = id supergroup) dengan pesan reply ke postingan auto-forward tadi.
   * Pesan berisi teks seperti:

     ```
     Klik tombol di bawah untuk membeli
     ```
   * Tambahkan **Inline Keyboard Button** `"Beli Sekarang"` dengan `callback_data` misalnya `beli_<message_id>`.

---
