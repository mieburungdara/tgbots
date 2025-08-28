<?php
// This view assumes all data variables ($all_bots, $current_channel, etc.) are passed from the controller.
?>

<h2>Manajemen Channel Jualan</h2>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?= nl2br(htmlspecialchars($error_message)) ?></div>
<?php endif; ?>
<?php if ($success_message): ?>
    <div class="alert alert-success"><?= nl2br(htmlspecialchars($success_message)) ?></div>
<?php endif; ?>

<?php if ($current_channel): ?>
    <!-- Management View -->
    <div class="dashboard-card" style="margin-top: 20px;">
        <h3>Konfigurasi Saat Ini</h3>
        <p>
            <strong>Bot Terhubung:</strong> @<?= htmlspecialchars($bot_details['username'] ?? 'N/A') ?><br>
            <strong>Channel Jualan:</strong> <?= htmlspecialchars($current_channel['title'] ?? 'N/A') ?> (<code><?= htmlspecialchars($current_channel['channel_id']) ?></code>)<br>
            <strong>Grup Diskusi:</strong> <?= htmlspecialchars($current_channel['group_title'] ?? 'N/A') ?> (<code><?= htmlspecialchars($current_channel['discussion_group_id']) ?></code>)<br>
        </p>
        <hr>
        <p>Untuk mengubah konfigurasi, silakan gunakan formulir di bawah ini.</p>
    </div>
<?php else: ?>
    <!-- Wizard/Setup View -->
    <div class="dashboard-card" style="margin-top: 20px;">
        <h3>Panduan Konfigurasi Channel</h3>
        <p>Hubungkan bot dengan channel jualan dan grup diskusi Anda untuk mulai mem-posting konten.</p>
        <ol>
            <li>Pilih salah satu bot yang tersedia dari daftar.</li>
            <li>Buat Channel dan Grup Diskusi di Telegram.</li>
            <li>Tautkan Grup Diskusi ke Channel Anda (melalui Pengaturan Channel &rarr; Diskusi).</li>
            <li>Jadikan bot yang Anda pilih sebagai <strong>Admin</strong> di Channel <strong>DAN</strong> di Grup Diskusi.</li>
            <li>Pastikan bot memiliki izin untuk <strong>'Post Messages'</strong> di Channel.</li>
            <li>Isi formulir di bawah ini dan simpan.</li>
        </ol>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="margin-top: 20px;">
    <h3><?= $current_channel ? 'Perbarui Konfigurasi' : 'Daftarkan Konfigurasi Baru' ?></h3>
    <form action="/member/channels" method="POST">
        <div style="margin-bottom: 15px;">
            <label for="bot_id"><strong>Pilih Bot</strong></label>
            <select id="bot_id" name="bot_id" required>
                <option value="">-- Pilih Bot --</option>
                <?php foreach ($all_bots as $bot): ?>
                    <option value="<?= $bot['id'] ?>" <?= (isset($current_channel['bot_id']) && $current_channel['bot_id'] == $bot['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($bot['name']) ?> (@<?= htmlspecialchars($bot['username']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="margin-bottom: 15px;">
            <label for="channel_identifier"><strong>ID atau Username Channel</strong></label>
            <input type="text" id="channel_identifier" name="channel_identifier" required value="<?= htmlspecialchars($current_channel['channel_id'] ?? '') ?>" placeholder="@channel_jualan_anda">
        </div>
        <div style="margin-bottom: 15px;">
            <label for="group_identifier"><strong>ID atau Username Grup Diskusi</strong></label>
            <input type="text" id="group_identifier" name="group_identifier" required value="<?= htmlspecialchars($current_channel['discussion_group_id'] ?? '') ?>" placeholder="@grup_diskusi_anda">
            <small>Grup ini harus sudah terhubung ke channel Anda di pengaturan Telegram.</small>
        </div>
        <div>
            <button type="submit" class="btn">Simpan Konfigurasi</button>
        </div>
    </form>
</div>
