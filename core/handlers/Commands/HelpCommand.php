<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;

class HelpCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        $feature = $app->bot['assigned_feature'] ?? 'general';

        $help_text = "*🤖 Panduan Perintah Bot 🤖*\n\n";

        $general_commands_text = "*--- PERINTAH UMUM ---*\n";
        $general_commands_text .= "➡️ `/me`\nLihat profil Anda.\n";
        $general_commands_text .= "➡️ `/login`\nMasuk ke panel member web.\n";
        $general_commands_text .= "➡️ `/about`\nTentang bot ini.\n";

        switch ($feature) {
            case 'sell':
                $help_text .= "*--- FITUR JUAL BELI ---*\n";
                $help_text .= "➡️ `/sell`\nBalas (reply) media untuk mulai menjual.\n";
                $help_text .= "➡️ `/addmedia`\nTambah media saat proses `/sell`.\n";
                $help_text .= "➡️ `/addmedia <ID_PAKET>`\nTambah media ke paket yang sudah ada.\n\n";
                $help_text .= "➡️ `/konten <ID_PAKET>`\nLihat detail atau beli konten.\n";
                $help_text .= "➡️ `/balance`\nCek saldo Anda.\n";
                break;

            case 'rate':
                $help_text .= "*--- FITUR RATING ---*\n";
                $help_text .= "➡️ `/rate`\nBalas (reply) media untuk memberi rating.\n\n";
                break;

            case 'tanya':
                $help_text .= "*--- FITUR TANYA ---*\n";
                $help_text .= "➡️ `/tanya`\nBalas (reply) pesan untuk bertanya.\n\n";
                break;

            default: // General or null feature
                $help_text .= "Berikut adalah perintah utama yang bisa Anda gunakan:\n\n";
                $help_text .= "➡️ `/balance`\nCek saldo Anda.\n";
                break;
        }

        $help_text .= $general_commands_text;
        }

        if ($app->user['role'] === 'Admin') {
            $help_text .= "\n*--- KHUSUS ADMIN ---*\n";
            $help_text .= "➡️ `/dev_addsaldo <user_id> <jumlah>`\nMenambah saldo pengguna.\n";
            $help_text .= "➡️ `/feature <package_id> <channel_id>`\nMempromosikan paket ke channel.\n";
        }

        $app->telegram_api->sendLongMessage($app->chat_id, $help_text, 'Markdown');
    }
}
