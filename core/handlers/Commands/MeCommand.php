<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;
use TGBot\Database\AnalyticsRepository;

class MeCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        $analytics_repo = new AnalyticsRepository($app->pdo);
        $user_id = $app->user['id'];
        $sales_stats = $analytics_repo->getSellerSummary($user_id);

        $user_name = $app->telegram_api->escapeMarkdown(trim($app->user['first_name'] . ' ' . ($app->user['last_name'] ?? '')));
        $balance = "Rp " . number_format($app->user['balance'], 0, ',', '.');
        $seller_id = $app->user['public_seller_id'] ? "`" . $app->user['public_seller_id'] . "`" : "Belum terdaftar";

        $total_sales = $sales_stats['total_sales'];
        $total_revenue = "Rp " . number_format($sales_stats['total_revenue'], 0, ',', '.');

        $response = "👤 *Profil Anda*\n\n";
        $response .= "Nama: *{$user_name}*\n";
        $response .= "Telegram ID: `{$app->user['id']}`\n";
        $response .= "ID Penjual: {$seller_id}\n\n";
        $response .= "💰 *Keuangan*\n";
        $response .= "Saldo Saat Ini: *{$balance}*\n\n";
        $response .= "📈 *Aktivitas Penjualan*\n";
        $response .= "Total Item Terjual: *{$total_sales}* item\n";
        $response .= "Total Pendapatan: *{$total_revenue}*";

        $app->telegram_api->sendMessage($app->chat_id, $response, 'Markdown');
    }
}
