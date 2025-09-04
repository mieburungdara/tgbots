<?php
// This view assumes 'roles' and 'message' are available in the $data array.
?>

<h1>Manajemen Daftar Peran</h1>
<p>Gunakan halaman ini untuk menambah atau menghapus peran yang tersedia secara global di sistem.</p>

<?php if (isset($data['message'])): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($data['message']) ?>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h2>Tambah Peran Baru</h2>
    </div>
    <div class="card-body">
        <form action="/xoradmin/roles/store" method="post">
            <input type="text" name="role_name" placeholder="Nama peran (e.g., Reseller)" required>
            <button type="submit" name="add_role" class="btn">Tambah</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Daftar Peran Saat Ini</h2>
    </div>
    <div class="card-body">
        <table class="chat-log-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Peran</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['roles'])): ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">Belum ada peran yang ditambahkan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['roles'] as $role): ?>
                        <tr>
                            <td><?= htmlspecialchars($role['id']) ?></td>
                            <td><?= htmlspecialchars($role['name']) ?></td>
                            <td>
                                <form action="/xoradmin/roles/delete" method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus peran ini? Ini akan menghapus peran ini dari SEMUA pengguna yang memilikinya.');">
                                    <input type="hidden" name="role_id" value="<?= htmlspecialchars($role['id']) ?>">
                                    <button type="submit" name="delete_role" class="btn btn-delete btn-sm">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
