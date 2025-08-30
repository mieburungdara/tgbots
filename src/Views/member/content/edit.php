<?php
// This view assumes 'package' and 'error_message' are available in the $data array.
?>

<h2>Edit Konten: <?= htmlspecialchars($data['package']['public_id']) ?></h2>
<p>Gunakan form di bawah ini untuk mengubah detail konten Anda.</p>

<?php if (isset($data['error_message'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($data['error_message']) ?></div>
<?php endif; ?>

<form action="/member/content/update" method="POST">
    <input type="hidden" name="public_id" value="<?= htmlspecialchars($data['package']['public_id']) ?>">
    <div style="margin-bottom: 15px;">
        <label for="description"><strong>Deskripsi</strong></label>
        <textarea id="description" name="description" rows="4" required><?= htmlspecialchars($data['package']['description']) ?></textarea>
    </div>
    <div style="margin-bottom: 15px;">
        <label for="price"><strong>Harga (Rp)</strong></label>
        <input type="number" id="price" name="price" value="<?= htmlspecialchars($data['package']['price']) ?>" placeholder="Kosongkan jika tidak ingin dijual">
    </div>

    <div>
        <button type="submit" class="btn">Simpan Perubahan</button>
        <a href="/member/my_content" class="btn" style="background-color: #6c757d;">Batal</a>
    </div>
</form>
