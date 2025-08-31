<?php
// This view assumes that the following variables are available in the $data array:
// 'bots', 'selected_bot_id', 'search_user', 'conversations', 'channel_chats', 'bot_exists',
// and the helper function get_initials().
?>

<div class="conv-layout">
    <aside class="conv-sidebar">
        <div class="conv-sidebar-header">
            <h2>Pilih Bot</h2>
        </div>
        <div class="conv-bot-list">
            <?php if (empty($data['bots'])): ?>
                <p style="padding: 15px;">Tidak ada bot ditemukan.</p>
            <?php else: ?>
                <?php foreach ($data['bots'] as $bot): ?>
                    <?php
                        $link_params = ['bot_id' => $bot['id']];
                        if (!empty($data['search_user'])) {
                            $link_params['search_user'] = $data['search_user'];
                        }
                        // Note: A url() helper function would be ideal here.
                        $url = '/admin/dashboard?' . http_build_query($link_params);
                    ?>
                    <a href="<?= $url ?>" class="<?= ($data['selected_bot_id'] == $bot['id']) ? 'active' : '' ?>">
                        <?= htmlspecialchars($bot['first_name'] ?? 'Bot Tanpa Nama') ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <main class="conv-main">
        <div class="search-form" style="margin-bottom: 20px;">
            <form action="/admin/dashboard" method="get">
                <?php if ($data['selected_bot_id']): ?>
                    <input type="hidden" name="bot_id" value="<?= htmlspecialchars($data['selected_bot_id']) ?>">
                <?php endif; ?>
                <input type="text" name="search_user" placeholder="Cari percakapan pengguna..." value="<?= htmlspecialchars($data['search_user']) ?>" style="width: 300px; display: inline-block;">
                <button type="submit" class="btn">Cari</button>
                <?php if(!empty($data['search_user'])): ?>
                    <a href="/admin/dashboard?bot_id=<?= $data['selected_bot_id'] ?>" class="btn btn-delete">Hapus Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($data['selected_bot_id']): ?>
            <?php if (!$data['bot_exists']): ?>
                <div class="alert alert-danger">Bot dengan ID <?= htmlspecialchars($data['selected_bot_id']) ?> tidak ditemukan.</div>
            <?php else: ?>

                <h3>Percakapan Pengguna</h3>
                <ul class="conv-list">
                    <?php if (empty($data['conversations'])): ?>
                        <p>Tidak ada percakapan yang cocok dengan kriteria.</p>
                    <?php else: ?>
                        <?php foreach ($data['conversations'] as $conv): ?>
                            <li class="conv-card">
                                <a href="/admin/chat?telegram_id=<?= $conv['telegram_id'] ?>&bot_id=<?= $data['selected_bot_id'] ?>">
                                    <div class="conv-avatar"><?= get_initials($conv['first_name'] ?? '?') ?></div>
                                    <div class="conv-details">
                                        <div class="conv-header">
                                            <span class="conv-name"><?= htmlspecialchars($conv['first_name'] ?? 'Tanpa Nama') ?></span>
                                            <span class="conv-time"><?= htmlspecialchars(date('H:i', strtotime($conv['last_message_time'] ?? 'now'))) ?></span>
                                        </div>
                                        <div class="conv-message"><?= htmlspecialchars($conv['last_message'] ?? '...') ?></div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <?php if(empty($data['search_user'])): // Only show channels if not searching for a user ?>
                <h3 style="margin-top: 40px;">Percakapan Channel & Grup</h3>
                <ul class="conv-list">
                     <?php if (empty($data['channel_chats'])): ?>
                        <p>Belum ada pesan dari channel atau grup untuk bot ini.</p>
                    <?php else: ?>
                        <?php foreach ($data['channel_chats'] as $chat): ?>
                            <?php
                                $chat_title = 'Grup/Channel Tanpa Nama';
                                if (!empty($chat['last_message_raw'])) {
                                    $raw = json_decode($chat['last_message_raw'], true);
                                    $chat_title = $raw['channel_post']['chat']['title'] ?? $raw['message']['chat']['title'] ?? $chat_title;
                                }
                            ?>
                            <li class="conv-card">
                                <a href="/admin/channel_chat?chat_id=<?= htmlspecialchars($chat['chat_id']) ?>&bot_id=<?= htmlspecialchars($data['selected_bot_id']) ?>">
                                    <div class="conv-avatar" style="background-color: #6c757d;"><?= get_initials($chat_title) ?></div>
                                    <div class="conv-details">
                                        <div class="conv-header">
                                            <span class="conv-name"><?= htmlspecialchars($chat_title) ?></span>
                                            <span class="conv-time"><?= htmlspecialchars(date('H:i', strtotime($chat['last_message_time'] ?? 'now'))) ?></span>
                                        </div>
                                        <div class="conv-message"><?= htmlspecialchars($chat['last_message'] ?? '...') ?></div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>

            <?php endif; ?>
        <?php else: ?>
            <div style="text-align: center; padding-top: 50px; color: #6c757d;">
                <h2>Selamat Datang</h2>
                <p>Silakan pilih bot dari sidebar kiri untuk melihat percakapannya.</p>
            </div>
        <?php endif; ?>
    </main>
</div>
