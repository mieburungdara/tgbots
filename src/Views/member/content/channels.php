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
                                        $managing_bot_username = 'Tidak diketahui';
                                        foreach ($data['sell_bots'] as $bot) {
                                            if ($bot['id'] == $data['channel']['managing_bot_id']) {
                                                $managing_bot_username = '@' . $bot['username'];
                                                break;
                                            }
                                        }
                                        echo htmlspecialchars($managing_bot_username);
                                        ?>
                                        (ID: <code><?= htmlspecialchars($data['channel']['managing_bot_id']) ?></code>)
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <div class="alert alert-info">
                            <h5><i class="icon fas fa-info"></i> Belum Ada Channel Terdaftar</h5>
                            <p>Anda belum mendaftarkan channel jualan. Silakan gunakan formulir di bawah untuk mendaftar.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><?= $data['channel'] ? 'Perbarui' : 'Daftarkan' ?> Channel Jualan</h3>
                </div>
                <form action="/member/channels/register" method="POST">
                    <div class="card-body">
                        <?php if (isset($_SESSION['flash_error'])) : ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                            <?php unset($_SESSION['flash_error']); ?>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['flash_message'])) : ?>
                            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_message']) ?></div>
                            <?php unset($_SESSION['flash_message']); ?>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="channel_id">ID Channel Publik</label>
                            <input type="text" class="form-control" id="channel_id" name="channel_id" value="<?= htmlspecialchars($data['channel']['public_channel_id'] ?? '') ?>" placeholder="-1001234567890" required>
                        </div>
                        <div class="form-group">
                            <label for="group_id">ID Grup Diskusi</label>
                            <input type="text" class="form-control" id="group_id" name="group_id" value="<?= htmlspecialchars($data['channel']['discussion_group_id'] ?? '') ?>" placeholder="-1009876543210" required>
                        </div>
                        <div class="form-group">
                            <label for="managing_bot_id">Pilih Bot Pengelola</label>
                            <select class="form-control" id="managing_bot_id" name="managing_bot_id" required>
                                <option value="">-- Pilih Bot --</option>
                                <?php foreach ($data['sell_bots'] as $bot) : ?>
                                    <option value="<?= $bot['id'] ?>" <?= isset($data['channel']['managing_bot_id']) && $data['channel']['managing_bot_id'] == $bot['id'] ? 'selected' : '' ?>>
                                        @<?= htmlspecialchars($bot['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Pastikan bot yang Anda pilih telah dijadikan admin di Channel dan Grup Diskusi.</small>
                        </div>
                        <p class="text-muted mt-3">
                            <i class="fas fa-info-circle"></i> Untuk mendapatkan ID Channel/Grup, Anda dapat menggunakan bot seperti <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a>. Forward pesan dari channel/grup Anda ke bot tersebut untuk melihat ID-nya.
                        </p>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Simpan Konfigurasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
