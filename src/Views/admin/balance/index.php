<?php
// This view assumes all data variables are available in the $data array.
?>

<h1>Manajemen Saldo</h1>

<?php if (!empty($data['flash_message'])): ?>
    <div class="alert alert-<?= htmlspecialchars($data['flash_message_type']) ?>"><?= htmlspecialchars($data['flash_message']) ?></div>
<?php endif; ?>

<div class="content-box">
    <h2>Daftar Saldo Pengguna</h2>

    <div class="search-form" style="margin-bottom: 20px;">
        <form action="/admin/balance" method="get">
            <input type="text" name="search" placeholder="Cari Nama/Username..." value="<?= htmlspecialchars($data['search_term']) ?>" style="width: 300px; display: inline-block;">
            <button type="submit" class="btn">Cari</button>
             <?php if(!empty($data['search_term'])): ?>
                <a href="/admin/balance" class="btn btn-delete">Hapus Filter</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="chat-log-table">
            <thead>
                <tr>
                    <th><a href="<?= get_sort_link('id', $data['sort_by'], $data['order'], $_GET) ?>">Telegram ID</a></th>
                    <th><a href="<?= get_sort_link('first_name', $data['sort_by'], $data['order'], $_GET) ?>">Nama</a></th>
                    <th><a href="<?= get_sort_link('username', $data['sort_by'], $data['order'], $_GET) ?>">Username</a></th>
                    <th class="sortable"><a href="<?= get_sort_link('balance', $data['sort_by'], $data['order'], $_GET) ?>">Saldo Saat Ini</a></th>
                    <th class="sortable"><a href="<?= get_sort_link('total_income', $data['sort_by'], $data['order'], $_GET) ?>">Total Pemasukan</a></th>
                    <th class="sortable"><a href="<?= get_sort_link('total_spending', $data['sort_by'], $data['order'], $_GET) ?>">Total Pengeluaran</a></th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data['users_data'])): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">Tidak ada pengguna ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['users_data'] as $user_data): ?>
                        <tr>
                            <td><?= htmlspecialchars($user_data['telegram_id']) ?></td>
                            <td><?= htmlspecialchars(trim($user_data['first_name'] . ' ' . $user_data['last_name'])) ?></td>
                            <td>@<?= htmlspecialchars($user_data['username'] ?? 'N/A') ?></td>
                            <td class="clickable-log" data-log-type="balance" data-telegram-id="<?= $user_data['telegram_id'] ?>" data-user-name="<?= htmlspecialchars($user_data['first_name']) ?>"><?= format_currency($user_data['balance']) ?></td>
                            <td class="clickable-log" data-log-type="sales" data-telegram-id="<?= $user_data['telegram_id'] ?>" data-user-name="<?= htmlspecialchars($user_data['first_name']) ?>"><?= format_currency($user_data['total_income'] ?? 0) ?></td>
                            <td class="clickable-log" data-log-type="purchases" data-telegram-id="<?= $user_data['telegram_id'] ?>" data-user-name="<?= htmlspecialchars($user_data['first_name']) ?>"><?= format_currency($user_data['total_spending'] ?? 0) ?></td>
                            <td>
                                <button class="btn btn-sm btn-edit open-balance-modal"
                                        data-telegram-id="<?= $user_data['telegram_id'] ?>"
                                        data-user-name="<?= htmlspecialchars(trim($user_data['first_name'] . ' ' . $user_data['last_name'])) ?>">
                                    Ubah Saldo
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php
        $currentPage = $data['page'];
        $totalPages = $data['total_pages'];
        $query_params = $_GET;

        if ($totalPages > 1):
            if ($currentPage > 1) {
                $query_params['page'] = $currentPage - 1;
                echo '<a href="/admin/balance?' . htmlspecialchars(http_build_query($query_params)) . '">&laquo; Sebelumnya</a>';
            } else {
                echo '<span class="disabled">&laquo; Sebelumnya</span>';
            }

            echo '<span class="current-page">Halaman ' . htmlspecialchars($currentPage) . ' dari ' . htmlspecialchars($totalPages) . '</span>';

            if ($currentPage < $totalPages) {
                $query_params['page'] = $currentPage + 1;
                echo '<a href="/admin/balance?' . htmlspecialchars(http_build_query($query_params)) . '">Berikutnya &raquo;</a>';
            } else {
                echo '<span class="disabled">Berikutnya &raquo;</span>';
            }
        endif;
        ?>
    </div>
</div>

<!-- Modal untuk Ubah Saldo -->
<div id="balance-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title-adjust">Ubah Saldo untuk Pengguna</h2>
            <button class="modal-close-adjust">&times;</button>
        </div>
        <form action="/admin/balance/adjust?<?= http_build_query($_GET) ?>" method="post" class="balance-adjustment-form" style="padding: 0; border: none; background: none; margin-top: 0;">
            <input type="hidden" name="user_id" id="modal-user-id">
            <div class="form-group">
                <label for="modal-amount">Jumlah:</label>
                <input type="number" name="amount" id="modal-amount" step="0.01" min="0.01" required placeholder="Contoh: 50000">
            </div>
            <div class="form-group">
                <label for="modal-description">Deskripsi (Opsional):</label>

                <!-- Dropdown pilihan cepat -->
                <select id="preset-description" onchange="updateDescription()" style="margin-bottom: 8px; width: 100%; padding: 8px;">
                    <option value="">-- 💡 Pilih deskripsi cepat --</option>
                    <optgroup label="🎁 Bonus & Hadiah">
                        <option value="Bonus Pendaftaran">🎉 Bonus Pendaftaran</option>
                        <option value="Hadiah topup">🛍️ Hadiah topup</option>
                        <option value="Bonus referral">🤝 Bonus referral</option>
                        <option value="Bonus Kinerja">🏆 Bonus Kinerja</option>
                        <option value="Bonus Loyalitas">💖 Bonus Loyalitas</option>
                        <option value="Bonus pencapaian target">🎯 Bonus pencapaian target</option>
                        <option value="Bonus hari raya">🕌 Bonus hari raya</option>
                        <option value="Bonus Ulang Tahun">🎂 Bonus Ulang Tahun</option>
                        <option value="Cashback">💸 Cashback</option>
                        <option value="Saldo event">🎟️ Saldo event</option>
                        <option value="Hadiah Giveaway">🎁 Hadiah Giveaway</option>
                        <option value="Hadiah kontes/kompetisi">🏅 Hadiah kontes/kompetisi</option>
                        <option value="Saldo promosi khusus">📢 Saldo promosi khusus</option>
                    </optgroup>
                    <optgroup label="💰 Pembayaran & Komisi">
                        <option value="Gaji">💵 Gaji</option>
                        <option value="Pencairan Komisi">📈 Pencairan Komisi</option>
                        <option value="Pencairan Afiliasi">🔗 Pencairan Afiliasi</option>
                        <option value="Pembayaran freelance">👨‍💻 Pembayaran freelance</option>
                        <option value="Pembayaran royalti">👑 Pembayaran royalti</option>
                        <option value="Insentif penjualan">🚀 Insentif penjualan</option>
                        <option value="Reimbursement Biaya">🧾 Reimbursement Biaya</option>
                    </optgroup>
                    <optgroup label="🔄 Kompensasi & Pengembalian">
                        <option value="Pengembalian Dana (Refund)">🔙 Pengembalian Dana (Refund)</option>
                        <option value="Refund Transaksi Gagal">❌ Refund Transaksi Gagal</option>
                        <option value="Refund kelebihan bayar">➕ Refund kelebihan bayar</option>
                        <option value="Kompensasi Gangguan Layanan">🛠️ Kompensasi Gangguan Layanan</option>
                        <option value="Kompensasi Kesalahan Sistem">🖥️ Kompensasi Kesalahan Sistem</option>
                        <option value="Kompensasi keterlambatan">⏳ Kompensasi keterlambatan</option>
                        <option value="Pembalikan Transaksi">↩️ Pembalikan Transaksi</option>
                        <option value="Penyelesaian Sengketa">⚖️ Penyelesaian Sengketa</option>
                        <option value="Ganti rugi barang/jasa">📦 Ganti rugi barang/jasa</option>
                    </optgroup>
                    <optgroup label="✏️ Penyesuaian & Koreksi">
                        <option value="Koreksi salah input">✍️ Koreksi salah input</option>
                        <option value="Penyesuaian manual">🔧 Penyesuaian manual</option>
                        <option value="Penyesuaian Saldo Awal">🌅 Penyesuaian Saldo Awal</option>
                        <option value="Migrasi saldo dari sistem lama">💾 Migrasi saldo dari sistem lama</option>
                        <option value="Penyesuaian audit internal">📊 Penyesuaian audit internal</option>
                        <option value="Koreksi transaksi duplikat">👯 Koreksi transaksi duplikat</option>
                        <option value="Koreksi Saldo Negatif">📉 Koreksi Saldo Negatif</option>
                    </optgroup>
                    <optgroup label="💸 Potongan & Biaya">
                        <option value="Biaya Administrasi">🏛️ Biaya Administrasi</option>
                        <option value="Biaya keanggotaan (membership)">💳 Biaya keanggotaan (membership)</option>
                        <option value="Biaya Layanan">🛎️ Biaya Layanan</option>
                        <option value="Biaya Penarikan">🏧 Biaya Penarikan</option>
                        <option value="Biaya upgrade akun">🌟 Biaya upgrade akun</option>
                        <option value="Biaya Pembatalan">🚫 Biaya Pembatalan</option>
                        <option value="Denda Pelanggaran">📜 Denda Pelanggaran</option>
                        <option value="Denda keterlambatan pembayaran">⌛ Denda keterlambatan pembayaran</option>
                        <option value="Potongan pajak">🧾 Potongan pajak</option>
                    </optgroup>
                    <optgroup label="⚙️ Operasional & Lain-lain">
                        <option value="Deposit untuk layanan">📥 Deposit untuk layanan</option>
                        <option value="Pembelian kredit/poin internal">🪙 Pembelian kredit/poin internal</option>
                        <option value="Transfer Antar Pengguna (Admin)">↔️ Transfer Antar Pengguna (Admin)</option>
                        <option value="Penarikan dana (Withdrawal)">📤 Penarikan dana (Withdrawal)</option>
                    </optgroup>
                </select>

                <!-- Textarea untuk custom / tambahan -->
                <textarea name="description" id="modal-description" rows="2"
                    placeholder="Isi deskripsi tambahan bila perlu..."></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="action" value="add_balance" class="btn btn-edit">Tambah Saldo</button>
                <button type="submit" name="action" value="subtract_balance" class="btn btn-delete">Kurangi Saldo</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal untuk Log Transaksi -->
<div id="log-modal" class="modal-overlay">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2 id="log-modal-title">Riwayat Transaksi</h2>
            <button class="modal-close-log">&times;</button>
        </div>
        <div id="log-modal-body" style="max-height: 60vh; overflow-y: auto;">
            <p>Memuat data...</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal untuk Ubah Saldo
    const balanceModal = document.getElementById('balance-modal');
    const balanceModalTitle = document.getElementById('modal-title-adjust');
    const balanceModalUserIdInput = document.getElementById('modal-user-id');
    document.querySelectorAll('.open-balance-modal').forEach(button => {
        button.addEventListener('click', function() {
            balanceModalTitle.textContent = 'Ubah Saldo untuk ' + this.dataset.userName;
            balanceModalUserIdInput.value = this.dataset.telegramId;
            balanceModal.style.display = 'flex';
        });
    });
    document.querySelectorAll('.modal-close-adjust').forEach(button => button.addEventListener('click', () => balanceModal.style.display = 'none'));
    balanceModal.addEventListener('click', e => { if (e.target === balanceModal) balanceModal.style.display = 'none'; });

    // Modal untuk Log Transaksi
    const logModal = document.getElementById('log-modal');
    const logModalTitle = document.getElementById('log-modal-title');
    const logModalBody = document.getElementById('log-modal-body');
    document.querySelectorAll('.clickable-log').forEach(cell => {
        cell.addEventListener('click', async function() {
            const telegramId = this.dataset.telegramId;
            const userName = this.dataset.userName;
            const logType = this.dataset.logType;

            logModalBody.innerHTML = '<p>Memuat data...</p>';
            logModal.style.display = 'flex';

            let apiUrl = '';
            let title = '';
            let headers = [];
            let dataBuilder;

            switch (logType) {
                case 'balance':
                    apiUrl = `/api/admin/balance/log?telegram_id=${telegramId}`;
                    title = `Riwayat Penyesuaian Saldo untuk ${userName}`;
                    headers = ['Waktu', 'Tipe', 'Jumlah', 'Deskripsi'];
                    dataBuilder = (item) => `<td>${item.created_at}</td><td>${item.type}</td><td>${formatCurrency(item.amount)}</td><td>${item.description || ''}</td>`;
                    break;
                case 'sales':
                    apiUrl = `/api/admin/balance/sales?telegram_id=${telegramId}`;
                    title = `Riwayat Pemasukan (Penjualan) untuk ${userName}`;
                    headers = ['Waktu', 'Konten', 'Pembeli', 'Harga'];
                    dataBuilder = (item) => `<td>${item.purchased_at}</td><td>${item.package_title || 'N/A'}</td><td>${item.buyer_name || 'N/A'}</td><td>${formatCurrency(item.price)}</td>`;
                    break;
                case 'purchases':
                    apiUrl = `/api/admin/balance/purchases?telegram_id=${telegramId}`;
                    title = `Riwayat Pengeluaran (Pembelian) untuk ${userName}`;
                    headers = ['Waktu', 'Konten', 'Harga'];
                    dataBuilder = (item) => `<td>${item.purchased_at}</td><td>${item.package_title || 'N/A'}</td><td>${formatCurrency(item.price)}</td>`;
                    break;
            }

            logModalTitle.textContent = title;

            try {
                const response = await fetch(apiUrl);
                const data = await response.json();
                if (data.error) throw new Error(data.error);
                if (data.length === 0) {
                    logModalBody.innerHTML = '<p>Tidak ada riwayat ditemukan.</p>';
                    return;
                }
                const table = document.createElement('table');
                table.className = 'chat-log-table';
                const thead = document.createElement('thead');
                const tbody = document.createElement('tbody');
                const headerRow = document.createElement('tr');
                headers.forEach(h => {
                    const th = document.createElement('th');
                    th.textContent = h;
                    headerRow.appendChild(th);
                });
                thead.appendChild(headerRow);
                data.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = dataBuilder(item);
                    tbody.appendChild(row);
                });
                table.appendChild(thead);
                table.appendChild(tbody);
                logModalBody.innerHTML = '';
                logModalBody.appendChild(table);
            } catch (error) {
                logModalBody.innerHTML = `<p class="alert alert-danger">Gagal memuat data: ${error.message}</p>`;
            }
        });
    });
    document.querySelectorAll('.modal-close-log').forEach(button => button.addEventListener('click', () => logModal.style.display = 'none'));
    logModal.addEventListener('click', e => { if (e.target === logModal) logModal.style.display = 'none'; });

    function formatCurrency(number, currency = 'Rp') {
        if (isNaN(parseFloat(number))) return number;
        return currency + ' ' + parseFloat(number).toLocaleString('id-ID');
    }
});

function updateDescription() {
    const preset = document.getElementById('preset-description').value;
    const descriptionTextarea = document.getElementById('modal-description');
    if (preset) {
        descriptionTextarea.value = preset;
    }
}
</script>
