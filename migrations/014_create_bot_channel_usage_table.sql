CREATE TABLE bot_channel_usage (
    bot_id INT NOT NULL,
    last_used_channel_id INT NOT NULL,
    PRIMARY KEY (bot_id),
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE,
    FOREIGN KEY (last_used_channel_id) REFERENCES private_channels(id) ON DELETE CASCADE
);
