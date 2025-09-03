<?php
// This view assumes the following variables are available in the $data array:
// 'page_title', 'report'
?>

<h1><?= htmlspecialchars($data['page_title']) ?></h1>

<div class="card">
    <div class="card-header">
        <h2>Hasil Pemeriksaan Skema</h2>
    </div>
    <div class="card-body">
        <?php if (empty($data['report']['missing_tables']) && empty($data['report']['missing_columns'])): ?>
            <div class="alert alert-success">
                ✅ Skema database Anda sudah sinkron dengan file `updated_schema.sql`. Tidak ada aksi yang diperlukan.
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                ⚠️ Ditemukan perbedaan antara skema database Anda dan file `updated_schema.sql`.
            </div>

            <?php if (!empty($data['report']['missing_tables'])): ?>
                <h3>Tabel yang Hilang</h3>
                <p>Tabel berikut ada di `updated_schema.sql` tapi tidak ditemukan di database Anda:</p>
                <ul>
                    <?php foreach ($data['report']['missing_tables'] as $table): ?>
                        <li><strong><?= htmlspecialchars($table['name']) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
                <h4>Query untuk Membuat Tabel yang Hilang:</h4>
                <textarea readonly style="width: 100%; height: 150px;"><?php
                    foreach ($data['report']['missing_tables'] as $table) {
                        echo $table['query'] . ";\n\n";
                    }
                ?></textarea>
            <?php endif; ?>

            <?php if (!empty($data['report']['missing_columns'])): ?>
                <h3>Kolom yang Hilang</h3>
                <p>Kolom berikut ada di `updated_schema.sql` tapi tidak ditemukan di tabel database Anda:</p>
                <?php foreach ($data['report']['missing_columns'] as $table_name => $columns): ?>
                    <h4>Tabel: <strong><?= htmlspecialchars($table_name) ?></strong></h4>
                    <ul>
                        <?php foreach ($columns as $column): ?>
                            <li><strong><?= htmlspecialchars($column['name']) ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                    <h5>Query untuk Menambah Kolom yang Hilang:</h5>
                    <textarea readonly style="width: 100%; height: 100px;"><?php
                        foreach ($columns as $column) {
                            echo $column['query'] . ";\n";
                        }
                    ?></textarea>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
