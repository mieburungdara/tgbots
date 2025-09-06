<div class="container-fluid">
    <div class="row">
        <!-- Channel List -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Channel Jualan</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($data['channels'])) : ?>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Channel Publik</th>
                                    <th>Grup Diskusi</th>
                                    <th>Bot Pengelola</th>
                                    <th style="width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['channels'] as $channel) : ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($channel['name']) ?></strong><br>
                                            <code><?= htmlspecialchars($channel['public_channel_id']) ?></code>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($channel['discussion_group_name'] ?? 'N/A') ?></strong><br>
                                            <code><?= htmlspecialchars($channel['discussion_group_id']) ?></code>
                                        </td>
                                        <td>
                                            <?php
                                            $managing_bot_username = 'Tidak diketahui';
                                            foreach ($data['sell_bots'] as $bot) {
                                                if ($bot['id'] == $channel['managing_bot_id']) {
                                                    $managing_bot_username = '@' . $bot['username'];
                                                    break;
                                                }
                                            }
                                            echo htmlspecialchars($managing_bot_username);
                                            ?>
                                        </td>
                                        <td>
                                            <form action="/member/channels/delete" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus channel ini?');">
                                                <input type="hidden" name="channel_id" value="<?= $channel['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
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
        </div>

        <!-- Add New Channel Form Wizard -->
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Daftarkan Channel Jualan Baru</h3>
                </div>
                <form id="channelWizardForm" action="/member/channels/register" method="POST">
                    <div class="card-body">
                        <?php if (isset($_SESSION['flash_error'])) : ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                            <?php unset($_SESSION['flash_error']); ?>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['flash_message'])) : ?>
                            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_message']) ?></div>
                            <?php unset($_SESSION['flash_message']); ?>
                        <?php endif; ?>

                        <!-- Wizard Steps -->
                        <div class="wizard-step" id="step1">
                            <h4>Langkah 1: Masukkan ID Channel Publik</h4>
                            <p>Masukkan ID numerik dari channel publik tempat Anda akan menampilkan jualan. Pastikan channel bersifat publik.</p>
                            <div class="form-group">
                                <label for="channel_id">ID Channel Publik</label>
                                <input type="text" class="form-control" id="channel_id" name="channel_id" placeholder="-1001234567890" required>
                            </div>
                        </div>

                        <div class="wizard-step" id="step2" style="display: none;">
                            <h4>Langkah 2: Masukkan ID Grup Diskusi</h4>
                            <p>Masukkan ID numerik dari grup yang terhubung dengan channel publik Anda sebagai grup diskusi.</p>
                            <div class="form-group">
                                <label for="group_id">ID Grup Diskusi</label>
                                <input type="text" class="form-control" id="group_id" name="group_id" placeholder="-1009876543210" required>
                            </div>
                        </div>

                        <div class="wizard-step" id="step3" style="display: none;">
                            <h4>Langkah 3: Pilih Bot Pengelola</h4>
                            <p>Pilih bot yang akan Anda gunakan untuk mengelola channel ini. Pastikan bot yang Anda pilih telah dijadikan admin di Channel dan Grup Diskusi.</p>
                            <div class="form-group">
                                <label for="managing_bot_id">Pilih Bot Pengelola</label>
                                <select class="form-control" id="managing_bot_id" name="managing_bot_id" required>
                                    <option value="">-- Pilih Bot --</option>
                                    <?php foreach ($data['sell_bots'] as $bot) : ?>
                                        <option value="<?= $bot['id'] ?>">
                                            @<?= htmlspecialchars($bot['username']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                             <p class="text-muted mt-3">
                                <i class="fas fa-info-circle"></i> Untuk mendapatkan ID Channel/Grup, Anda dapat menggunakan bot seperti <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a>. Forward pesan dari channel/grup Anda ke bot tersebut untuk melihat ID-nya.
                            </p>
                        </div>

                        <div class="wizard-step" id="step4" style="display: none;">
                            <h4>Langkah 4: Konfirmasi</h4>
                            <p>Silakan periksa kembali semua informasi yang telah Anda masukkan. Jika sudah benar, klik "Simpan Konfigurasi" untuk menyelesaikan pendaftaran.</p>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item"><b>ID Channel Publik:</b> <span id="confirm_channel_id"></span></li>
                                <li class="list-group-item"><b>ID Grup Diskusi:</b> <span id="confirm_group_id"></span></li>
                                <li class="list-group-item"><b>Bot Pengelola:</b> <span id="confirm_bot"></span></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;">Sebelumnya</button>
                        <button type="button" class="btn btn-primary" id="nextBtn">Berikutnya</button>
                        <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">Simpan Konfigurasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentStep = 1;
    const steps = document.querySelectorAll('.wizard-step');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    function showStep(stepNumber) {
        steps.forEach((step, index) => {
            step.style.display = (index + 1 === stepNumber) ? 'block' : 'none';
        });

        prevBtn.style.display = (stepNumber > 1) ? 'inline-block' : 'none';
        nextBtn.style.display = (stepNumber === steps.length) ? 'none' : 'inline-block';
        submitBtn.style.display = (stepNumber === steps.length) ? 'inline-block' : 'none';
    }

    function validateStep(stepNumber) {
        const currentStepDiv = document.getElementById('step' + stepNumber);
        const inputs = currentStepDiv.querySelectorAll('input[required], select[required]');
        for (const input of inputs) {
            if (!input.value) {
                alert('Harap isi semua field yang wajib diisi.');
                input.focus();
                return false;
            }
        }
        return true;
    }

    function updateConfirmationDetails() {
        document.getElementById('confirm_channel_id').textContent = document.getElementById('channel_id').value;
        document.getElementById('confirm_group_id').textContent = document.getElementById('group_id').value;
        const botSelect = document.getElementById('managing_bot_id');
        document.getElementById('confirm_bot').textContent = botSelect.options[botSelect.selectedIndex].text;
    }

    nextBtn.addEventListener('click', function() {
        if (validateStep(currentStep)) {
            if (currentStep < steps.length) {
                currentStep++;
                if (currentStep === steps.length) {
                    updateConfirmationDetails();
                }
                showStep(currentStep);
            }
        }
    });

    prevBtn.addEventListener('click', function() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    });

    showStep(currentStep);
});
</script>
