<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Konfigurasi Database</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 40px; background-color: #f4f6f8; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background-color: #fff; padding: 20px 40px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 5px solid #dc3545; }
        h1 { color: #dc3545; }
        code { background-color: #e9ecef; padding: 2px 6px; border-radius: 4px; font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Terjadi Masalah Konfigurasi</h1>
        <p>
            <?php
            // Variabel $setup_error_message diharapkan sudah di-set sebelum file ini di-include.
            echo isset($setup_error_message) ? $setup_error_message : 'Terjadi error yang tidak diketahui terkait setup database.';
            ?>
        </p>
        <p>
            Setelah Anda menjalankan skrip SQL, silakan segarkan kembali halaman ini.
        </p>
    </div>
</body>
</html>
