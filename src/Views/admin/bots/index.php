<?php
// This view assumes the following variables are available in the $data array:
// 'page_title', 'bots', 'error', 'success'
?>

<h1><?= htmlspecialchars($data['page_title']) ?></h1>

<?php if ($data['error']): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($data['error']) ?></div>
<?php endif; ?>
<?php if ($data['success']): ?>
    <div class="alert alert-success"><?= htmlspecialchars($data['success']) ?></div>
<?php endif; ?>

<h2>Tambah Bot Baru</h2>
<form action="/admin/bots" method="post">
    <input type="text" name="token" placeholder="Token API dari BotFather" required style="width: 400px; display: inline-block;">
    <button type="submit" name="add_bot">Tambah Bot</button>
</form>

<h2>Daftar Bot</h2>
<table>
    <thead>
        <tr>
            <th>ID Bot</th>
            <th>Nama</th>
            <th>Username</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($data['bots'])): ?>
            <tr>
                <td colspan="4" style="text-align: center;">Belum ada bot yang ditambahkan.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($data['bots'] as $bot): ?>
                <tr>
                    <td><?= htmlspecialchars($bot['id']) ?></td>
                    <td><?= htmlspecialchars($bot['first_name']) ?></td>
                    <td>@<?= htmlspecialchars($bot['username'] ?? 'N/A') ?></td>
                    <td>
                        <a href="/admin/bots/edit?id=<?= htmlspecialchars($bot['id']) ?>" class="btn btn-edit">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
