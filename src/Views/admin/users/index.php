<?php
// This view assumes all data variables are available in the $data array.
?>

<h1><?= htmlspecialchars($data['page_title']) ?></h1>

<?php if (!empty($data['message'])): ?>
    <div class="alert alert-success" id="flash-message"><?= htmlspecialchars($data['message']) ?></div>
<?php endif; ?>

<div class="search-form" style="margin-bottom: 20px;">
    <form action="/admin/users" method="get">
        <input type="text" name="search" placeholder="Cari ID, Nama, Username..." value="<?= htmlspecialchars($data['search_term']) ?>" style="width: 300px; display: inline-block;">
        <button type="submit" class="btn">Cari</button>
        <?php if(!empty($data['search_term'])): ?>
            <a href="/admin/users" class="btn btn-delete">Hapus Filter</a>
        <?php endif; ?>
    </form>
</div>

<p>Menampilkan <?= count($data['users']) ?> dari <?= $data['total_users'] ?> total pengguna.</p>

<div class="table-responsive">
    <table class="chat-log-table">
        <thead>
            <tr>
                <th><a href="<?= get_sort_link('id', $data['sort_by'], $data['order'], ['search' => $data['search_term']]) ?>">User ID</a></th>
                <th><a href="<?= get_sort_link('first_name', $data['sort_by'], $data['order'], ['search' => $data['search_term']]) ?>">Nama</a></th>
                <th><a href="<?= get_sort_link('username', $data['sort_by'], $data['order'], ['search' => $data['search_term']]) ?>">Username</a></th>
                <th><a href="<?= get_sort_link('status', $data['sort_by'], $data['order'], ['search' => $data['search_term']]) ?>">Status</a></th>
                <th><a href="<?= get_sort_link('roles', $data['sort_by'], $data['order'], ['search' => $data['search_term']]) ?>">Peran</a></th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data['users'])): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">Tidak ada pengguna ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($data['users'] as $user): ?>
                <tr id="user-row-<?= $user['id'] ?>">
                    <td><?= htmlspecialchars($user['id']) ?></td>
                    <td><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></td>
                    <td>@<?= htmlspecialchars($user['username'] ?? 'N/A') ?></td>
                    <td><span class="status-<?= htmlspecialchars($user['status']) ?>"><?= htmlspecialchars(ucfirst($user['status'])) ?></span></td>
                    <td class="roles-cell">
                        <?php if (!empty($user['roles'])): ?>
                            <?php foreach (explode(', ', $user['roles']) as $role): ?>
                                <span class="role-badge"><?= htmlspecialchars($role) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">Tidak ada</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/admin/dashboard?search_user=<?= htmlspecialchars($user['username'] ?? $user['first_name']) ?>" class="btn btn-sm">Lihat Chat</a>
                        <button class="btn btn-sm btn-manage-roles" data-user-id="<?= $user['id'] ?>" data-user-name="<?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>">Kelola Peran</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination">
    <?php
    $query_params = $_GET;
    if ($data['page'] > 1) {
        $query_params['page'] = $data['page'] - 1;
        echo '<a href="/admin/users?' . http_build_query($query_params) . '">&laquo; Sebelumnya</a>';
    } else {
        echo '<span class="disabled">&laquo; Sebelumnya</span>';
    }

    echo '<span class="current-page">Halaman ' . $data['page'] . ' dari ' . $data['total_pages'] . '</span>';

    if ($data['page'] < $data['total_pages']) {
        $query_params['page'] = $data['page'] + 1;
        echo '<a href="/admin/users?' . http_build_query($query_params) . '">Berikutnya &raquo;</a>';
    } else {
        echo '<span class="disabled">Berikutnya &raquo;</span>';
    }
    ?>
</div>

<!-- Modal for Role Management -->
<div id="roles-modal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Kelola Peran untuk Pengguna</h2>
            <span class="close-button">&times;</span>
        </div>
        <div class="modal-body">
            <form id="roles-form">
                <input type="hidden" id="modal-user-id" name="user_id">
                <div id="roles-checkbox-container">
                    <!-- Checkboxes will be populated by JavaScript -->
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button id="save-roles-button" class="btn">Simpan</button>
        </div>
    </div>
</div>

<script>
// Javascript for the roles modal, pointing to the old API files.
// This will be refactored later.
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('roles-modal');
    const closeButton = modal.querySelector('.close-button');
    const manageButtons = document.querySelectorAll('.btn-manage-roles');
    const saveButton = document.getElementById('save-roles-button');
    const checkboxContainer = document.getElementById('roles-checkbox-container');
    const modalUserIdInput = document.getElementById('modal-user-id');
    const modalTitle = document.getElementById('modal-title');

    const allRoles = <?= json_encode($data['all_roles']) ?>;

    function openModal(userId, userName) {
        modalUserIdInput.value = userId;
        modalTitle.textContent = 'Kelola Peran untuk ' + userName;
        checkboxContainer.innerHTML = 'Memuat peran...';
        modal.style.display = 'block';

        fetch(`/api/admin/user/roles?telegram_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                populateCheckboxes(data.role_ids);
            })
            .catch(error => {
                checkboxContainer.innerHTML = `<p style="color:red;">Gagal memuat peran: ${error.message}</p>`;
            });
    }

    function populateCheckboxes(assignedRoleIds) {
        checkboxContainer.innerHTML = '';
        allRoles.forEach(role => {
            const isChecked = assignedRoleIds.includes(parseInt(role.id, 10));
            const checkboxWrapper = document.createElement('div');
            checkboxWrapper.className = 'checkbox-wrapper';
            checkboxWrapper.innerHTML = `
                <label>
                    <input type="checkbox" name="role_ids[]" value="${role.id}" ${isChecked ? 'checked' : ''}>
                    ${role.name}
                </label>
            `;
            checkboxContainer.appendChild(checkboxWrapper);
        });
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    manageButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            openModal(userId, userName);
        });
    });

    closeButton.addEventListener('click', closeModal);
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    saveButton.addEventListener('click', function() {
        const userId = modalUserIdInput.value;
        const checkedRoles = Array.from(checkboxContainer.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);

        saveButton.textContent = 'Menyimpan...';
        saveButton.disabled = true;

        fetch('/api/admin/user/roles', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                telegram_id: userId,
                role_ids: checkedRoles
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal();
                updateTableRowRoles(userId, checkedRoles);
                const flashMessage = document.getElementById('flash-message') || document.createElement('div');
                flashMessage.className = 'alert alert-success';
                flashMessage.textContent = 'Peran berhasil diperbarui!';
                if (!document.getElementById('flash-message')) {
                    document.querySelector('h1').insertAdjacentElement('afterend', flashMessage);
                }
                setTimeout(() => flashMessage.style.display = 'none', 3000);
            } else {
                throw new Error(data.error || 'Terjadi kesalahan yang tidak diketahui.');
            }
        })
        .catch(error => {
            alert('Gagal menyimpan peran: ' + error.message);
        })
        .finally(() => {
            saveButton.textContent = 'Simpan';
            saveButton.disabled = false;
        });
    });

    function updateTableRowRoles(userId, newRoleIds) {
        const row = document.getElementById(`user-row-${userId}`);
        if (!row) return;
        const rolesCell = row.querySelector('.roles-cell');
        if (!rolesCell) return;
        rolesCell.innerHTML = '';
        const newRoleNames = newRoleIds.map(id => {
            const role = allRoles.find(r => r.id == id);
            return role ? role.name : '';
        }).filter(name => name);
        if (newRoleNames.length > 0) {
            newRoleNames.forEach(name => {
                const badge = document.createElement('span');
                badge.className = 'role-badge';
                badge.textContent = name;
                rolesCell.appendChild(badge);
            });
        } else {
            const noRoleSpan = document.createElement('span');
            noRoleSpan.className = 'text-muted';
            noRoleSpan.textContent = 'Tidak ada';
            rolesCell.appendChild(noRoleSpan);
        }
    }
});
</script>

<style>
.role-badge { display: inline-block; padding: 0.25em 0.6em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.375rem; color: #fff; background-color: #6c757d; margin-right: 5px; margin-bottom: 5px; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 5px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
.modal-header h2 { margin: 0; }
.close-button { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
.close-button:hover, .close-button:focus { color: black; }
.checkbox-wrapper { margin-bottom: 10px; }
</style>
