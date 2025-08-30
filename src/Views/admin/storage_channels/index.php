<?php
// This view assumes 'private_channels', 'all_bots', and 'message' are available in the $data array.
?>

<style>
	/* CSS untuk Modal */
	.modal {
		display: none;
		position: fixed;
		z-index: 1000;
		left: 0;
		top: 0;
		width: 100%;
		height: 100%;
		overflow: auto;
		background-color: rgba(0, 0, 0, 0.4);
	}

	.modal-content {
		background-color: #fefefe;
		margin: 10% auto;
		padding: 20px;
		border: 1px solid #888;
		width: 60%;
		max-width: 700px;
		border-radius: 5px;
		position: relative;
	}

	.close-button {
		color: #aaa;
		float: right;
		font-size: 28px;
		font-weight: bold;
		position: absolute;
		top: 10px;
		right: 20px;
	}

	.close-button:hover,
	.close-button:focus {
		color: black;
		text-decoration: none;
		cursor: pointer;
	}

	#bot-list .bot-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 8px;
		border-bottom: 1px solid #eee;
	}

	#bot-list .bot-item:last-child {
		border-bottom: none;
	}

	.bot-status {
		font-size: 0.9em;
		padding: 3px 8px;
		border-radius: 12px;
	}

	.bot-status.verified {
		background-color: #d4edda;
		color: #155724;
	}

	.bot-status.unverified {
		background-color: #f8d7da;
		color: #721c24;
	}
</style>

<h1>Kelola Channel Penyimpanan</h1>

<?php if ($data['message']): ?>
<div class="alert alert-info"><?php echo nl2br(htmlspecialchars($data['message'])); ?></div>
<?php endif; ?>

<p class="description">Tambahkan channel pribadi yang akan digunakan bot untuk menyimpan file media. Anda dapat mengelola bot mana yang memiliki akses ke setiap channel.</p>

<h2>Tambah Channel Baru</h2>
<form action="/admin/storage_channels/store" method="post" class="mb-20">
	<div class="form-group">
		<label for="name">Nama Channel (untuk referensi)</label>
		<input type="text" id="name" name="name" required>
	</div>
	<div class="form-group">
		<label for="channel_id">ID Channel Telegram</label>
		<input type="text" id="channel_id" name="channel_id" required placeholder="-100123456789">
	</div>
	<button type="submit" class="btn">Tambah Channel</button>
</form>

<h2>Daftar Channel Tersimpan</h2>
<table id="channel-list-table"
    data-update-info-url="/api/admin/storage_channels/update-info">
	<thead>
		<tr>
			<th>Nama</th>
			<th>ID Channel</th>
			<th>Jumlah Bot</th>
			<th>Bot Terhubung</th>
			<th colspan="2">Aksi</th>
		</tr>
	</thead>
	<tbody>
		<?php if (empty($data['private_channels'])): ?>
		<tr>
			<td colspan="6">Belum ada channel yang ditambahkan.</td>
		</tr>
		<?php else: ?>
		<?php foreach ($data['private_channels'] as $channel): ?>
		<tr data-row-id="<?php echo $channel['channel_id']; ?>">
			<td class="channel-name"><?php echo htmlspecialchars($channel['name']); ?></td>
			<td><?php echo htmlspecialchars($channel['channel_id']); ?></td>
			<td><?php echo htmlspecialchars($channel['bot_count']); ?></td>
			<td><?php echo htmlspecialchars($channel['bot_usernames'] ?? 'N/A'); ?></td>
			<td>
				<button class="btn btn-primary manage-bots-btn" data-channel-id="<?php echo $channel['channel_id']; ?>" data-channel-name="<?php echo htmlspecialchars($channel['name']); ?>">
					Kelola Bot
				</button>
			</td>
			<td>
				<button class="btn btn-secondary btn-sm refresh-channel-btn" data-channel-id="<?php echo $channel['channel_id']; ?>">
					Refresh
				</button>
			</td>
		</tr>
		<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

<!-- Modal untuk Manajemen Bot -->
<div id="manage-bots-modal" class="modal"
    data-get-bots-url="/api/admin/storage_channels/bots"
    data-add-bot-url="/api/admin/storage_channels/add-bot"
    data-remove-bot-url="/api/admin/storage_channels/remove-bot"
    data-verify-bot-url="/api/admin/storage_channels/verify-bot">
	<div class="modal-content">
		<span class="close-button">&times;</span>
		<h2 id="modal-title">Kelola Bot untuk Channel: </h2>
		<div id="modal-notifications"></div>

		<h3>Bot Terhubung</h3>
		<div id="bot-list">
			<!-- Daftar bot akan dimuat di sini oleh JavaScript -->
			<p>Memuat...</p>
		</div>

		<template id="bot-item-template">
			<div class="bot-item">
				<div>
					<strong class="bot-username"></strong>
					<span class="bot-first-name"></span>
					<span class="bot-status"></span>
				</div>
				<div class="bot-actions">
					<button class="btn btn-sm btn-verify" style="display: none;">Verifikasi</button>
					<button class="btn btn-sm btn-danger btn-remove">Hapus</button>
				</div>
			</div>
		</template>

		<hr>

		<h3>Tambah Bot ke Channel</h3>
		<form id="add-bot-to-channel-form">
			<input type="hidden" id="modal-channel-id" name="channel_id">
			<div class="form-group">
				<label for="bot-select">Pilih Bot:</label>
				<select id="bot-select" name="bot_id" required>
					<option value="">-- Pilih Bot --</option>
					<?php foreach ($data['all_bots'] as $bot): ?>
					<option value="<?php echo $bot['id']; ?>">
						<?php echo htmlspecialchars($bot['first_name'] . ' (@' . $bot['username'] . ')'); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
			<button type="submit" class="btn">Tambah Bot</button>
		</form>

		<hr>

		<form id="delete-channel-form" action="/admin/storage_channels/destroy" method="post" onsubmit="return confirm('Yakin ingin menghapus channel ini beserta semua hubungan bot-nya?');">
			<input type="hidden" id="delete-channel-id-input" name="id">
			<button type="submit" class="btn btn-danger">Hapus Channel Ini</button>
		</form>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function() {
		const modal = document.getElementById('manage-bots-modal');
        const channelTable = document.getElementById('channel-list-table');
		const closeModalBtn = modal.querySelector('.close-button');
		const manageBtns = document.querySelectorAll('.manage-bots-btn');
		const botListDiv = document.getElementById('bot-list');
		const addBotForm = document.getElementById('add-bot-to-channel-form');
		const modalChannelIdInput = document.getElementById('modal-channel-id');
		const modalNotifications = document.getElementById('modal-notifications');

        const apiUrls = {
            getBots: modal.dataset.getBotsUrl,
            addBot: modal.dataset.addBotUrl,
            removeBot: modal.dataset.removeBotUrl,
            verifyBot: modal.dataset.verifyBotUrl,
            updateInfo: channelTable.dataset.updateInfoUrl
        };

		const closeModal = () => modal.style.display = 'none';

		function showNotification(message, type = 'info') {
			modalNotifications.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
			setTimeout(() => modalNotifications.innerHTML = '', 4000);
		}

		async function apiRequest(url, body) {
			try {
				const response = await fetch(url, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded'
					},
					body: new URLSearchParams(body)
				});
				if (!response.ok) throw new Error('Network response was not ok.');
				return await response.json();
			} catch (error) {
				return {
					status: 'error',
					message: 'Request failed: ' + error.message
				};
			}
		}

		function renderBotList(bots) {
			botListDiv.innerHTML = '';
			if (bots.length === 0) {
				botListDiv.innerHTML = '<p>Belum ada bot yang terhubung ke channel ini.</p>';
				return;
			}

			const template = document.getElementById('bot-item-template');

			bots.forEach(bot => {
				const clone = template.content.cloneNode(true);
				const statusSpan = clone.querySelector('.bot-status');
				const verifyBtn = clone.querySelector('.btn-verify');
				const removeBtn = clone.querySelector('.btn-remove');

				clone.querySelector('.bot-username').textContent = `@${bot.username}`;
				clone.querySelector('.bot-first-name').textContent = `(${bot.first_name})`;

				if (bot.verified_at) {
					statusSpan.textContent = 'Terverifikasi';
					statusSpan.className = 'bot-status verified';
				} else {
					statusSpan.textContent = 'Belum Diverifikasi';
					statusSpan.className = 'bot-status unverified';
					verifyBtn.style.display = 'inline-block';
					verifyBtn.dataset.botId = bot.id;
				}

				removeBtn.dataset.botId = bot.id;
				botListDiv.appendChild(clone);
			});
		}

		async function loadConnectedBots(channelId) {
			botListDiv.innerHTML = '<p>Memuat...</p>';
			try {
				const response = await fetch(`${apiUrls.getBots}?channel_id=${channelId}`);
				const data = await response.json();
				if (data.status === 'success') {
					renderBotList(data.bots);
				} else {
					botListDiv.innerHTML = `<p class="text-danger">Error: ${data.message}</p>`;
				}
			} catch (error) {
				botListDiv.innerHTML = `<p class="text-danger">Gagal memuat bot: ${error.message}</p>`;
			}
		}

		closeModalBtn.addEventListener('click', closeModal);
		window.addEventListener('click', (event) => {
			if (event.target === modal) closeModal();
		});

		manageBtns.forEach(btn => {
			btn.addEventListener('click', () => {
				const channelId = btn.dataset.channelId;
				const channelName = btn.dataset.channelName;
				modal.querySelector('#modal-title').innerText = 'Kelola Bot untuk Channel: ' + channelName;
				modalChannelIdInput.value = channelId;
				modal.querySelector('#delete-channel-id-input').value = channelId;
				modalNotifications.innerHTML = '';
				modal.style.display = 'block';
				loadConnectedBots(channelId);
			});
		});

		addBotForm.addEventListener('submit', async function(e) {
			e.preventDefault();
			const channelId = modalChannelIdInput.value;
			const botId = this.querySelector('#bot-select').value;
			if (!botId) {
				showNotification('Silakan pilih bot terlebih dahulu.', 'danger');
				return;
			}
			const data = await apiRequest(apiUrls.addBot, {
				channel_id: channelId,
				bot_id: botId
			});
			showNotification(data.message, data.status === 'success' ? 'success' : 'danger');
			if (data.status === 'success') {
				loadConnectedBots(channelId);
			}
		});

		botListDiv.addEventListener('click', async function(e) {
			const channelId = modalChannelIdInput.value;
			const target = e.target;

			if (target.classList.contains('btn-verify')) {
				target.disabled = true;
				target.innerText = 'Memverifikasi...';
				const botId = target.dataset.botId;
				const data = await apiRequest(apiUrls.verifyBot, {
					channel_id: channelId,
					bot_id: botId
				});
				showNotification(data.message, data.status === 'success' ? 'success' : 'danger');
				loadConnectedBots(channelId);
			}

			if (target.classList.contains('btn-remove')) {
				if (!confirm('Yakin ingin menghapus bot ini dari channel?')) return;
				target.disabled = true;
				const botId = target.dataset.botId;
				const data = await apiRequest(apiUrls.removeBot, {
					channel_id: channelId,
					bot_id: botId
				});
				showNotification(data.message, data.status === 'success' ? 'success' : 'danger');
				loadConnectedBots(channelId);
			}
		});

		document.querySelector('tbody').addEventListener('click', async function(e) {
			const target = e.target;
			if (target.classList.contains('refresh-channel-btn')) {
				const channelId = target.dataset.channelId;
				target.disabled = true;
				target.innerText = '...';

				const data = await apiRequest(apiUrls.updateInfo, {
					channel_id: channelId
				});
				if (data.status === 'success') {
					const row = document.querySelector(`tr[data-row-id="${channelId}"]`);
					if (row) {
						row.querySelector('.channel-name').innerText = data.newName;
					}
					alert(data.message);
				} else {
					alert('Error: ' + data.message);
				}

				target.disabled = false;
				target.innerText = 'Refresh';
			}
		});
	});
</script>