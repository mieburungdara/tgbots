<?php
// This view assumes the following variables are available:
// 'page_title', 'bots', 'config', 'action'
$config = $data['config'];
$bots = $data['bots'];
?>

<h1><?= htmlspecialchars($data['page_title']) ?></h1>

<form action="<?= $data['action'] ?>" method="POST">
    <div class="form-group">
        <label for="name">Nama Konfigurasi</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($config['name'] ?? '') ?>" required>
        <small>Nama internal untuk referensi Anda, misal: "Channel Jual Akun FF".</small>
    </div>

    <div class="form-group">
        <label for="feature_type">Jenis Fitur</label>
        <select id="feature_type" name="feature_type" required>
            <option value="sell" <?= (($config['feature_type'] ?? '') === 'sell') ? 'selected' : '' ?>>Jual (Sell)</option>
            <option value="rate" <?= (($config['feature_type'] ?? '') === 'rate') ? 'selected' : '' ?>>Rating (Rate)</option>
            <option value="tanya" <?= (($config['feature_type'] ?? '') === 'tanya') ? 'selected' : '' ?>>Tanya (Tanya)</option>
        </select>
    </div>

    <div class="form-group">
        <label for="managing_bot_id">Bot Pengelola</label>
        <select id="managing_bot_id" name="managing_bot_id" required>
            <?php foreach ($bots as $bot): ?>
                <option value="<?= $bot['id'] ?>" <?= (($config['managing_bot_id'] ?? 0) == $bot['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($bot['first_name']) ?> (@<?= htmlspecialchars($bot['username']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <hr>
    <h3>Channel & Grup ID</h3>
    <p>Masukkan ID numerik untuk channel/grup. Kosongkan jika tidak berlaku untuk fitur ini.</p>

    <div class="form-group">
        <label for="moderation_channel_id">ID Channel Moderasi/Backup</label>
        <input type="text" id="moderation_channel_id" name="moderation_channel_id" value="<?= htmlspecialchars($config['moderation_channel_id'] ?? '') ?>">
        <small>Channel pribadi tempat bot mem-backup semua postingan untuk moderasi.</small>
    </div>

    <div class="form-group">
        <label for="public_channel_id">ID Channel Publik</label>
        <input type="text" id="public_channel_id" name="public_channel_id" value="<?= htmlspecialchars($config['public_channel_id'] ?? '') ?>">
        <small>Channel publik tempat postingan yang disetujui akan ditampilkan.</small>
    </div>

    <div class="form-group">
        <label for="discussion_group_id">ID Grup Diskusi</label>
        <input type="text" id="discussion_group_id" name="discussion_group_id" value="<?= htmlspecialchars($config['discussion_group_id'] ?? '') ?>">
        <small>Grup yang terhubung dengan channel publik untuk komentar/diskusi.</small>
    </div>

    <hr>
    <h3>Kepemilikan (Opsional)</h3>

    <div class="form-group">
        <label for="owner_user_id">ID User Pemilik</label>
        <input type="text" id="owner_user_id" name="owner_user_id" value="<?= htmlspecialchars($config['owner_user_id'] ?? '') ?>">
        <small>Isi hanya jika channel ini milik seorang member/penjual, bukan milik admin.</small>
    </div>

    <button type="submit" class="btn btn-primary">Simpan Konfigurasi</button>
</form>
