<?php
// This view assumes the following variables are available in the $data array:
// 'page_title', 'report', 'error' (optional)

$report = $data['report'] ?? [];
$error = $data['error'] ?? null;

$has_differences = !empty($report['missing_tables']) || !empty($report['extra_tables']) || !empty($report['column_differences']);
?>

<h1><?= htmlspecialchars($data['page_title']) ?></h1>

<div class="card">
    <div class="card-header">
        <h2>Hasil Pemeriksaan Skema</h2>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <h4>Error</h4>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php elseif (!$has_differences): ?>
            <div class="alert alert-success">
                ✅ Skema database Anda sudah sinkron dengan file `updated_schema.sql`. Tidak ada aksi yang diperlukan.
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <h4><i class="fas fa-exclamation-triangle"></i> Perbedaan Ditemukan</h4>
                <p>Ditemukan perbedaan antara skema database Anda dan file `updated_schema.sql`. Harap tinjau dan jalankan query di bawah ini secara manual untuk melakukan sinkronisasi.</p>
            </div>

            <?php if (!empty($report['missing_tables'])): ?>
                <div class="my-4">
                    <h3>Tabel yang Hilang (di Database)</h3>
                    <p>Tabel berikut ada di <code>updated_schema.sql</code> tapi tidak ada di database live.</p>
                    <h4>Query untuk Membuat Tabel:</h4>
                    <textarea class="form-control" readonly rows="8"><?php
                        foreach ($report['missing_tables'] as $table) {
                            echo htmlspecialchars($table['query'] . ";\n\n");
                        }
                    ?></textarea>
                </div>
            <?php endif; ?>

            <?php if (!empty($report['extra_tables'])): ?>
                <div class="my-4">
                    <h3>Tabel Tambahan (di Database)</h3>
                    <p>Tabel berikut ada di database live tapi tidak ada di <code>updated_schema.sql</code>.</p>
                    <h4>Query untuk Menghapus Tabel:</h4>
                    <textarea class="form-control" readonly rows="5"><?php
                        foreach ($report['extra_tables'] as $table) {
                            echo htmlspecialchars($table['query'] . "\n");
                        }
                    ?></textarea>
                </div>
            <?php endif; ?>

            <?php if (!empty($report['column_differences'])): ?>
                <div class="my-4">
                    <h3>Perbedaan Kolom</h3>
                    <p>Ditemukan perbedaan kolom pada tabel-tabel berikut:</p>
                    <?php foreach ($report['column_differences'] as $table_name => $diffs): ?>
                        <div class="card my-3">
                            <div class="card-header">
                                Tabel: <strong><?= htmlspecialchars($table_name) ?></strong>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($diffs['missing'])): ?>
                                    <h5>Kolom yang Hilang (Harus Ditambahkan):</h5>
                                    <textarea class="form-control" readonly rows="4"><?php
                                        foreach ($diffs['missing'] as $col) {
                                            echo htmlspecialchars($col['query'] . "\n");
                                        }
                                    ?></textarea>
                                <?php endif; ?>

                                <?php if (!empty($diffs['extra'])): ?>
                                    <h5 class="mt-3">Kolom Tambahan (Harus Dihapus):</h5>
                                    <textarea class="form-control" readonly rows="4"><?php
                                        foreach ($diffs['extra'] as $col) {
                                            echo htmlspecialchars($col['query'] . "\n");
                                        }
                                    ?></textarea>
                                <?php endif; ?>

                                <?php if (!empty($diffs['modified'])): ?>
                                    <h5 class="mt-3">Kolom yang Berubah (Harus Diperbarui):</h5>
                                    <table class="table table-bordered table-sm">
                                        <thead>
                                            <tr>
                                                <th>Kolom</th>
                                                <th>Definisi di Skema File</th>
                                                <th>Definisi di Database Live</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($diffs['modified'] as $col): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($col['name']) ?></td>
                                                <td><code><?= htmlspecialchars($col['file_def']) ?></code></td>
                                                <td><code><?= htmlspecialchars($col['live_def']) ?></code></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <h6>Query untuk Memperbaiki:</h6>
                                    <textarea class="form-control" readonly rows="4"><?php
                                        foreach ($diffs['modified'] as $col) {
                                            echo htmlspecialchars($col['query'] . "\n");
                                        }
                                    ?></textarea>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
