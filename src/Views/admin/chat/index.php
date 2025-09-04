<?php
// This view assumes all data variables are available in the $data array.
?>

<div class="chat-container">
    <div class="chat-header">
        <a href="/xoradmin/dashboard?bot_id=<?= $data['bot_info']['id'] ?>" class="btn">&larr; Kembali</a>
        <h3>Riwayat Chat dengan <?= htmlspecialchars($data['user_info']['first_name'] ?? '') ?></h3>
        <p>Total Pesan: <?= $data['total_messages'] ?></p>
    </div>

    <form id="bulk-action-form" action="/xoradmin/chat/delete" method="post">
        <input type="hidden" name="user_id" value="<?= $data['user_info']['id'] ?>">
        <input type="hidden" name="bot_id" value="<?= $data['bot_info']['id'] ?>">

        <div class="bulk-actions-bar">
            <button type="submit" name="action" value="delete_db" class="btn btn-warning" disabled>Hapus dari DB</button>
            <button type="submit" name="action" value="delete_telegram" class="btn btn-danger" disabled>Hapus dari Telegram</button>
            <button type="submit" name="action" value="delete_both" class="btn btn-danger" disabled>Hapus dari Keduanya</button>
            <span id="selection-counter" style="margin-left: 10px;">0 item dipilih</span>
        </div>

        <div class="table-responsive">
            <table class="chat-log-table">
                <thead>
                    <tr>
                        <th class="col-checkbox"><input type="checkbox" id="select-all-checkbox"></th>
                        <th class="col-id">ID</th>
                        <th class="col-time">Waktu</th>
                        <th class="col-direction">Arah</th>
                        <th class="col-type">Tipe</th>
                        <th class="col-content">Konten</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['messages'])): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">Tidak ada pesan ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['messages'] as $msg): ?>
                            <tr>
                                <td class="col-checkbox"><input type="checkbox" class="message-checkbox" name="message_ids[]" value="<?= $msg['id'] ?>"></td>
                                <td class="col-id"><?= $msg['id'] ?></td>
                                <td class="col-time"><?= htmlspecialchars($msg['created_at']) ?></td>
                                <td class="col-direction">
                                    <span class="direction-<?= htmlspecialchars($msg['direction']) ?>">
                                        <?= htmlspecialchars(ucfirst($msg['direction'])) ?>
                                    </span>
                                </td>
                                <td class="col-type"><?= htmlspecialchars($msg['media_type'] ?? 'Teks') ?></td>
                                <td class="col-content">
                                    <?php if (!empty($msg['text'])): ?>
                                        <p><?= nl2br(htmlspecialchars($msg['text'])) ?></p>
                                    <?php endif; ?>
                                    <span class="json-toggle" onclick="toggleJson(this, 'msg-json-<?= $msg['id'] ?>')">
                                        Lihat Raw Data
                                    </span>
                                    <pre class="raw-json" id="msg-json-<?= $msg['id'] ?>" style="display:none;"><?= htmlspecialchars(json_encode(json_decode($msg['raw_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <div class="pagination">
        <?php
        $currentPage = $data['page'];
        $totalPages = $data['total_pages'];
        if ($totalPages > 1):
            $queryParams = ['telegram_id' => $data['user_info']['id'], 'bot_id' => $data['bot_info']['id']];
        ?>
            <?php if ($currentPage > 1): ?>
                <a href="/xoradmin/chat?<?= http_build_query(array_merge($queryParams, ['page' => $currentPage - 1])) ?>">&laquo; Sebelumnya</a>
            <?php else: ?>
                <span class="disabled">&laquo; Sebelumnya</span>
            <?php endif; ?>

            <span class="current-page">Halaman <?= $currentPage ?> dari <?= $totalPages ?></span>

            <?php if ($currentPage < $totalPages): ?>
                <a href="/xoradmin/chat?<?= http_build_query(array_merge($queryParams, ['page' => $currentPage + 1])) ?>">Berikutnya &raquo;</a>
            <?php else: ?>
                <span class="disabled">Berikutnya &raquo;</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="chat-reply-form" style="margin-top: 20px;">
        <form action="/xoradmin/chat/reply" method="post">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars($data['user_info']['id']) ?>">
            <input type="hidden" name="bot_id" value="<?= htmlspecialchars($data['bot_info']['id']) ?>">
            <textarea name="reply_text" rows="3" placeholder="Ketik balasan Anda..." required style="width: 100%; box-sizing: border-box;"></textarea>
            <button type="submit" name="reply_message" class="btn" style="margin-top: 10px;">Kirim</button>
        </form>
    </div>
</div>

<script>
function toggleJson(element, id) {
    const pre = document.getElementById(id);
    if (pre.style.display === 'none' || pre.style.display === '') {
        pre.style.display = 'block';
        element.textContent = 'Sembunyikan Raw Data';
    } else {
        pre.style.display = 'none';
        element.textContent = 'Lihat Raw Data';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const messageCheckboxes = document.querySelectorAll('.message-checkbox');
    const actionButtons = document.querySelectorAll('.bulk-actions-bar button');
    const selectionCounter = document.getElementById('selection-counter');

    function updateButtonState() {
        const checkedCount = document.querySelectorAll('.message-checkbox:checked').length;
        actionButtons.forEach(button => {
            button.disabled = (checkedCount === 0);
        });
        selectionCounter.textContent = checkedCount + ' item dipilih';
    }

    selectAllCheckbox.addEventListener('change', function() {
        messageCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        updateButtonState();
    });

    messageCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!this.checked) {
                selectAllCheckbox.checked = false;
            } else if (document.querySelectorAll('.message-checkbox:checked').length === messageCheckboxes.length) {
                selectAllCheckbox.checked = true;
            }
            updateButtonState();
        });
    });

    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (document.querySelectorAll('.message-checkbox:checked').length === 0) {
                e.preventDefault();
                alert('Silakan pilih setidaknya satu pesan.');
                return;
            }
            if (!confirm(`Anda yakin ingin menjalankan aksi ini pada item yang dipilih?`)) {
                e.preventDefault();
            }
        });
    });

    updateButtonState();
});
</script>
