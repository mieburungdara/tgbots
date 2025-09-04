<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Channel Jualan Terdaftar</h3>
                </div>
                <div class="card-body">
                    <?php if ($data['channel']) : ?>
                        <p>Berikut adalah detail channel jualan yang terhubung dengan akun Anda.</p>
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th style="width: 200px;">Nama Channel</th>
                                    <td><?= htmlspecialchars($data['channel']['name']) ?></td>
                                </tr>
                                <tr>
                                    <th>ID Channel Publik</th>
                                    <td><code><?= htmlspecialchars($data['channel']['public_channel_id']) ?></code></td>
                                </tr>
                                <tr>
                                    <th>ID Grup Diskusi</th>
                                    <td><code><?= htmlspecialchars($data['channel']['discussion_group_id']) ?></code></td>
                                </tr>
                                <tr>
                                    <th>Bot Pengelola</th>
                                    <td>
                                        <?php
                                        // Simple logic to get bot username, assuming you might pass it in the future
                                        echo "Bot ID: " . htmlspecialchars($data['channel']['managing_bot_id']);
                                        ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-3">
                            <p class="text-muted">Untuk mengubah atau mendaftarkan channel baru, gunakan perintah di bot Telegram:</p>
                            <p><code>/register_channel &lt;ID_CHANNEL&gt; &lt;ID_GRUP&gt;</code></p>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info">
                            <h5><i class="icon fas fa-info"></i> Belum Ada Channel Terdaftar</h5>
                            Anda belum mendaftarkan channel jualan. Untuk mendaftarkan, silakan kirim perintah berikut ke bot Telegram Anda:
                            <hr>
                            <code>/register_channel &lt;ID_CHANNEL&gt; &lt;ID_GRUP&gt;</code>
                            <br><br>
                            <small><strong>Contoh:</strong> <code>/register_channel -100123456789 -100987654321</code></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
