<?php
// This view assumes $sold_packages and $message are passed from the controller.
?>

<style>
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
    .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; }
    .card-thumbnail { width: 100%; height: 180px; background-color: #eee; text-align: center; line-height: 180px; font-size: 2rem; color: #ccc; }
    .card-body { padding: 1rem; flex-grow: 1; }
    .card-footer { padding: 0 1rem 1rem 1rem; border-top: 1px solid #eee; margin-top: auto;}
    .card-title { font-size: 1.1rem; font-weight: bold; margin: 0 0 0.5rem 0; }
    .card-text { font-size: 0.9rem; color: #606770; margin-bottom: 0.5rem; }
    .card-price { font-size: 1rem; font-weight: bold; color: #28a745; }
    .status { padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: bold; display: inline-block; }
    .status-available { background-color: #d4edda; color: #155724; }
    .status-pending { background-color: #fff3cd; color: #856404; }
    .status-sold { background-color: #e2e3e5; color: #383d41; }
    .status-deleted { background-color: #6c757d; color: white; }
    .no-content { background: white; padding: 2rem; text-align: center; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .btn-delete { width: 100%; padding: 0.5rem 1rem; background-color: #dc3545; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9rem; border: none; cursor: pointer; margin-top: 0.5rem; }
    .btn-delete:hover { background-color: #c82333; }
    .protection-toggle { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; }
    .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 28px; }
    .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: #28a745; }
    input:checked + .slider:before { transform: translateX(22px); }
</style>

<h1>Konten yang Anda Jual</h1>

<?php if ($message): ?>
    <div class="alert alert-success" id="status-message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<div class="alert" id="ajax-message" style="display: none;"></div>


<?php if (empty($sold_packages)): ?>
    <div class="no-content">
        <p>Anda tidak memiliki konten untuk dijual saat ini.</p>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($sold_packages as $package): ?>
            <div class="card">
                <div class="card-thumbnail">
                     <span>🖼️</span>
                </div>
                <div class="card-body">
                    <p class="card-text">ID: <?= htmlspecialchars($package['public_id']) ?></p>
                    <h2 class="card-title"><?= htmlspecialchars($package['description'] ?: 'Tanpa deskripsi') ?></h2>
                    <p class="card-price">Rp <?= number_format($package['price'], 0, ',', '.') ?></p>
                    <p class="card-text">
                        <?php
                            $status_class = 'status-pending';
                            if ($package['status'] === 'available') $status_class = 'status-available';
                            if ($package['status'] === 'sold') $status_class = 'status-sold';
                            if ($package['status'] === 'deleted') $status_class = 'status-deleted';
                        ?>
                        Status: <span class="status <?= $status_class ?>"><?= htmlspecialchars(ucfirst($package['status'])) ?></span>
                    </p>
                </div>
                <div class="card-footer">
                    <div class="protection-toggle">
                        <label for="protect-toggle-<?= $package['id'] ?>">Proteksi Konten</label>
                        <label class="switch">
                            <input type="checkbox" class="protect-toggle-checkbox" id="protect-toggle-<?= $package['id'] ?>" data-package-id="<?= $package['id'] ?>" <?= !empty($package['protect_content']) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <?php if ($package['status'] !== 'sold' && $package['status'] !== 'deleted'): ?>
                        <form action="/member/transactions/delete_package" method="post" onsubmit="return confirm('Anda yakin ingin menghapus konten ini?');">
                            <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                            <button type="submit" class="btn-delete">Hapus</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
// The AJAX logic for the protection toggle still points to the old file.
// This will be refactored in a future step.
document.addEventListener('DOMContentLoaded', function() {
    const statusMessage = document.getElementById('status-message');
    if (statusMessage) {
        setTimeout(() => {
            statusMessage.style.display = 'none';
        }, 3000);
    }
    const ajaxMessage = document.getElementById('ajax-message');
    document.querySelectorAll('.protect-toggle-checkbox').forEach(toggle => {
        toggle.addEventListener('change', async function() {
            const packageId = this.dataset.packageId;
            const formData = new FormData();
            formData.append('package_id', packageId);
            try {
                const response = await fetch('/member/package_manager.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                ajaxMessage.textContent = result.message;
                ajaxMessage.className = (result.status === 'success') ? 'alert alert-success' : 'alert alert-danger';
                ajaxMessage.style.display = 'block';
                if (result.status !== 'success') {
                    this.checked = !this.checked;
                }
                setTimeout(() => {
                    ajaxMessage.style.display = 'none';
                }, 3000);
            } catch (error) {
                ajaxMessage.textContent = 'Terjadi kesalahan jaringan.';
                ajaxMessage.className = 'alert alert-danger';
                ajaxMessage.style.display = 'block';
                this.checked = !this.checked;
            }
        });
    });
});
</script>
