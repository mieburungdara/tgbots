```sql
-- Tabel MESSAGES (super lengkap, single table)
CREATE TABLE messages (
    -- Identitas & dasar
    bot_id BIGINT NOT NULL,
    chat_id BIGINT NOT NULL,
    message_id BIGINT NOT NULL,
    message_thread_id BIGINT NULL,          -- ID topik forum (supergroup)
    date TIMESTAMP NOT NULL,
    edit_date TIMESTAMP NULL,

    -- Pengirim: bisa user ATAU sender_chat (anon admin/channel post)
    from_user_id BIGINT NULL,
    sender_chat_id BIGINT NULL,             -- jika dikirim atas nama chat/channel
    author_signature VARCHAR(255) NULL,     -- signature pada channel post
    sender_signature VARCHAR(255) NULL,     -- signature saat forward tertentu
    via_bot_user_id BIGINT NULL,            -- bot yang digunakan via inline
    business_connection_id VARCHAR(128) NULL,

    -- Penanda
    is_topic_message BOOLEAN DEFAULT 0,
    is_automatic_forward BOOLEAN DEFAULT 0,
    has_protected_content BOOLEAN DEFAULT 0,
    has_media_spoiler BOOLEAN DEFAULT 0,
    media_group_id VARCHAR(255) NULL,       -- album
    effect_id BIGINT NULL,                   -- pesan dengan efek premium (jika ada)

    -- Forward/Reply/Quote
    forward_origin JSON NULL,               -- MessageOrigin (user/chat/channel/hidden_user)
    forward_date TIMESTAMP NULL,
    reply_to_message_id BIGINT NULL,
    external_reply JSON NULL,               -- reply ke pesan eksternal (story/chat lain)
    quote JSON NULL,                        -- kutipan sebagian teks/media

    -- Tipe & konten umum
    type ENUM(
        'text','photo','video','audio','voice','document','sticker',
        'animation','video_note','location','venue','contact','poll',
        'dice','game','invoice','successful_payment','refunded_payment',
        'story','web_app_data','service','paid_media','other'
    ) NOT NULL DEFAULT 'text',
    text LONGTEXT NULL,
    entities JSON NULL,                     -- entities pada text
    link_preview_options JSON NULL,         -- kontrol preview link
    caption LONGTEXT NULL,
    caption_entities JSON NULL,

    -- Media generik (banyak jenis berbagi atribut)
    file_id VARCHAR(255) NULL,
    file_unique_id VARCHAR(255) NULL,
    file_name VARCHAR(255) NULL,
    mime_type VARCHAR(100) NULL,
    file_size BIGINT NULL,
    duration INT NULL,
    width INT NULL,
    height INT NULL,
    thumbnail JSON NULL,                    -- PhotoSize/thumbnail

    -- Photo (punya banyak ukuran)
    photo JSON NULL,                        -- array PhotoSize

    -- Audio/Voice/Video tambahan
    performer VARCHAR(255) NULL,
    title VARCHAR(255) NULL,

    -- Sticker
    sticker_type ENUM('regular','mask','custom_emoji') NULL,
    sticker_emoji VARCHAR(50) NULL,
    sticker_set_name VARCHAR(255) NULL,
    sticker_custom_emoji_id VARCHAR(255) NULL,
    sticker_is_animated BOOLEAN NULL,
    sticker_is_video BOOLEAN NULL,

    -- Story
    story_sender_chat_id BIGINT NULL,
    story_id BIGINT NULL,

    -- Lokasi
    latitude DECIMAL(10,6) NULL,
    longitude DECIMAL(10,6) NULL,
    horizontal_accuracy FLOAT NULL,
    live_period INT NULL,
    heading INT NULL,
    proximity_alert_radius INT NULL,

    -- Venue
    venue_title VARCHAR(255) NULL,
    venue_address VARCHAR(255) NULL,
    venue_foursquare_id VARCHAR(100) NULL,
    venue_foursquare_type VARCHAR(100) NULL,
    venue_google_place_id VARCHAR(100) NULL,
    venue_google_place_type VARCHAR(100) NULL,

    -- Kontak
    contact_phone_number VARCHAR(50) NULL,
    contact_first_name VARCHAR(255) NULL,
    contact_last_name VARCHAR(255) NULL,
    contact_user_id BIGINT NULL,
    contact_vcard TEXT NULL,

    -- Poll / Game / Dice
    poll JSON NULL,                         -- simpan full Poll
    game JSON NULL,                         -- Game object
    dice_emoji VARCHAR(50) NULL,
    dice_value INT NULL,

    -- Invoice & Payment (termasuk order info)
    invoice_title VARCHAR(255) NULL,
    invoice_description TEXT NULL,
    invoice_start_parameter VARCHAR(255) NULL,
    invoice_currency VARCHAR(10) NULL,
    invoice_total_amount BIGINT NULL,
    invoice_payload TEXT NULL,
    invoice_provider_token TEXT NULL,
    invoice_provider_data TEXT NULL,
    shipping_option_id VARCHAR(100) NULL,

    successful_payment_currency VARCHAR(10) NULL,
    successful_payment_total_amount BIGINT NULL,
    successful_payment_invoice_payload TEXT NULL,
    successful_payment_telegram_charge_id VARCHAR(255) NULL,
    successful_payment_provider_charge_id VARCHAR(255) NULL,
    order_info_name VARCHAR(255) NULL,
    order_info_phone_number VARCHAR(50) NULL,
    order_info_email VARCHAR(255) NULL,
    order_info_shipping_address JSON NULL,

    refunded_payment_currency VARCHAR(10) NULL,
    refunded_payment_total_amount BIGINT NULL,
    refunded_payment_invoice_payload TEXT NULL,
    refunded_payment_telegram_charge_id VARCHAR(255) NULL,
    refunded_payment_provider_charge_id VARCHAR(255) NULL,

    -- Keyboard Request (share user/chat)
    users_shared_request_id BIGINT NULL,
    users_shared_user_ids JSON NULL,        -- array user_id
    chat_shared_request_id BIGINT NULL,
    chat_shared_chat_id BIGINT NULL,

    -- Web App / Passport / Website / Write access
    web_app_data_button_text VARCHAR(255) NULL,
    web_app_data TEXT NULL,
    passport_data JSON NULL,
    connected_website VARCHAR(255) NULL,
    write_access_allowed BOOLEAN NULL,

    -- Service messages: forum topic & umum
    forum_topic_created JSON NULL,
    forum_topic_edited JSON NULL,
    forum_topic_closed BOOLEAN NULL,
    forum_topic_reopened BOOLEAN NULL,
    general_forum_topic_hidden BOOLEAN NULL,
    general_forum_topic_unhidden BOOLEAN NULL,

    new_chat_members JSON NULL,             -- array User
    left_chat_member_id BIGINT NULL,
    new_chat_title VARCHAR(255) NULL,
    new_chat_photo JSON NULL,               -- array PhotoSize
    delete_chat_photo BOOLEAN NULL,
    message_auto_delete_timer_changed INT NULL,

    group_chat_created BOOLEAN NULL,
    supergroup_chat_created BOOLEAN NULL,
    channel_chat_created BOOLEAN NULL,
    migrate_to_chat_id BIGINT NULL,
    migrate_from_chat_id BIGINT NULL,
    pinned_message_id BIGINT NULL,

    -- Giveaway / Boost / Paid media / Reactions cache (opsional)
    giveaway JSON NULL,                     -- detail giveaway
    giveaway_winners JSON NULL,
    boost_added_count INT NULL,
    paid_media JSON NULL,                   -- daftar media berbayar
    reactions JSON NULL,                    -- cache reaksi (jika disimpan)

    -- Index & relasi
    PRIMARY KEY (bot_id, chat_id, message_id),
    KEY idx_messages_chat_date (bot_id, chat_id, date),
    KEY idx_messages_from (from_user_id),
    KEY idx_messages_via_bot (via_bot_user_id),
    KEY idx_messages_reply (reply_to_message_id),
    CONSTRAINT fk_messages_bot FOREIGN KEY (bot_id)
        REFERENCES bots(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_chat FOREIGN KEY (chat_id)
        REFERENCES chats(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_from FOREIGN KEY (from_user_id)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_messages_sender_chat FOREIGN KEY (sender_chat_id)
        REFERENCES chats(id) ON DELETE SET NULL,
    CONSTRAINT fk_messages_via_bot FOREIGN KEY (via_bot_user_id)
        REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE users (
    id BIGINT PRIMARY KEY,                  -- user_id dari Telegram
    is_bot BOOLEAN NOT NULL DEFAULT 0,      -- apakah ini bot
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NULL,
    username VARCHAR(255) NULL,
    language_code VARCHAR(10) NULL,

    -- Status Premium
    is_premium BOOLEAN NULL,
    added_to_attachment_menu BOOLEAN NULL,
    can_join_groups BOOLEAN NULL,           -- jika user adalah bot
    can_read_all_group_messages BOOLEAN NULL,
    supports_inline_queries BOOLEAN NULL,

    -- Business Account (baru di Telegram API)
    business_intro JSON NULL,
    business_location JSON NULL,
    business_opening_hours JSON NULL,

    -- Metadata lokal
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE chats (
    id BIGINT PRIMARY KEY,                      -- chat_id dari Telegram
    bot_id BIGINT NOT NULL,                     -- chat ini terkait dengan bot mana
    type ENUM('private','group','supergroup','channel') NOT NULL,

    -- Basic info
    title VARCHAR(255) NULL,
    username VARCHAR(255) NULL,
    first_name VARCHAR(255) NULL,               -- hanya untuk private chat
    last_name VARCHAR(255) NULL,                -- hanya untuk private chat
    is_forum BOOLEAN NULL,                      -- apakah supergroup dengan forum

    -- Permissions & fitur
    accent_color_id INT NULL,
    max_reaction_count INT NULL,
    photo JSON NULL,                            -- ChatPhoto
    active_usernames JSON NULL,                 -- daftar username aktif
    birthdate JSON NULL,                        -- untuk user private
    business_intro JSON NULL,
    business_location JSON NULL,
    business_opening_hours JSON NULL,
    personal_chat_id BIGINT NULL,
    available_reactions JSON NULL,
    background_custom_emoji_id VARCHAR(255) NULL,
    profile_accent_color_id INT NULL,
    profile_background_custom_emoji_id VARCHAR(255) NULL,
    emoji_status_custom_emoji_id VARCHAR(255) NULL,
    emoji_status_expiration_date TIMESTAMP NULL,
    bio TEXT NULL,
    has_private_forwards BOOLEAN NULL,
    has_restricted_voice_and_video_messages BOOLEAN NULL,
    join_to_send_messages BOOLEAN NULL,
    join_by_request BOOLEAN NULL,
    description TEXT NULL,
    invite_link TEXT NULL,
    pinned_message_id BIGINT NULL,
    permissions JSON NULL,                      -- ChatPermissions
    slow_mode_delay INT NULL,
    unrestrict_boost_count INT NULL,
    message_auto_delete_time INT NULL,
    has_aggressive_anti_spam_enabled BOOLEAN NULL,
    has_hidden_members BOOLEAN NULL,
    has_protected_content BOOLEAN NULL,
    has_visible_history BOOLEAN NULL,
    sticker_set_name VARCHAR(255) NULL,
    can_set_sticker_set BOOLEAN NULL,
    custom_emoji_sticker_set_name VARCHAR(255) NULL,
    linked_chat_id BIGINT NULL,
    location JSON NULL,                         -- ChatLocation

    -- Meta
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Relasi
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
);
```
