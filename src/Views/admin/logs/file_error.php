<?php
// This view assumes all data variables are available in the $data array.
?>

<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4"><?= htmlspecialchars($data['page_title']) ?></h1>

    <?php if ($data['error_message']): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($data['error_message']) ?></div>
    <?php endif; ?>

    <?php if ($data['raw_log_content']): ?>
        <div class="bg-white shadow-md rounded-lg p-4">
            <pre class="whitespace-pre-wrap text-sm"><?= $data['raw_log_content'] ?></pre>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No log content available.</div>
    <?php endif; ?>
</div>