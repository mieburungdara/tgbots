<?php

namespace TGBot;

use TGBot\App;
use TGBot\Database\SaleRepository;
use TGBot\Database\FeatureChannelRepository;
use TGBot\Logger;
use Exception;

class SellerReportGenerator
{
    private SaleRepository $saleRepo;
    private FeatureChannelRepository $featureChannelRepo;
    private Logger $logger;

    public function __construct(
        SaleRepository $saleRepo,
        FeatureChannelRepository $featureChannelRepo,
        Logger $logger
    ) {
        $this->saleRepo = $saleRepo;
        $this->featureChannelRepo = $featureChannelRepo;
        $this->logger = $logger;
    }

    public function generateReport(App $app, array $package, string $description): array
    {
        $cache_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgbots_cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0777, true);
        }
        $cache_key = 'seller_report_' . $package['id'];
        $cache_file = $cache_dir . DIRECTORY_SEPARATOR . $cache_key . '.json';
        $cache_duration = 300; // 5 minutes

        // Try to load from cache
        if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_duration)) {
            $cached_data = json_decode(file_get_contents($cache_file), true);
            if ($cached_data) {
                return $cached_data;
            }
        }

        try {
            // Existing report data
            $analytics = $this->saleRepo->getAnalyticsForPackage($package['id']);
            $sales_count = $analytics['sales_count'];
            $views_count = $analytics['views_count'];
            $offers_count = $analytics['offers_count'];

            $total_earnings = $sales_count * $package['price'];
            $price_formatted = "Rp " . number_format($package['price'], 0, ',', '.');
            $total_earnings_formatted = "Rp " . number_format($total_earnings, 0, ',', '.');
            $created_at = date('d M Y H:i', strtotime($package['created_at']));

            $conversion_rate = ($views_count > 0) ? round(($sales_count / $views_count) * 100, 2) : 0;

            $report = "✨ *Laporan Konten*\n\n" .
                "ID Konten: `{$package['public_id']}`\n" .
                "Deskripsi: {$description}\n" .
                "Harga: {$price_formatted}\n" .
                "Status: {$package['status']}\n" .
                "Tanggal Dibuat: {$created_at}\n\n" .
                "📈 *Statistik Penjualan*\n" .
                "Jumlah Terjual: {$sales_count} kali\n" .
                "Total Pendapatan: {$total_earnings_formatted}\n\n" .
                "📊 *Analitik Pengguna*\n" .
                "Dilihat oleh: {$views_count} pengguna unik\n" .
                "Upaya tawar: {$offers_count} kali\n" .
                "Tingkat Konversi: {$conversion_rate}%";

            $caption = $report;

            $keyboard_buttons = [[['text' => 'Lihat Selengkapnya 📂', 'callback_data' => "view_page_{$package['public_id']}_0"]]];
            $sales_channels = $this->featureChannelRepo->findAllByOwnerAndFeature($app->user['id'], 'sell');
            if (!empty($sales_channels)) {
                $keyboard_buttons[0][] = ['text' => '📢 Post ke Channel', 'callback_data' => "post_channel_{$package['public_id']}"];
            } else {
                $caption .= "\n\n*Anda belum mendaftarkan channel pribadi, silahkan daftarkan channel pribadi anda untuk berjualan di panel member pada /login*";
            }
            // Add "Promosikan Konten" button
            $keyboard_buttons[] = [['text' => '🚀 Promosikan Konten', 'callback_data' => "promote_content_{$package['public_id']}"]];

            $keyboard = ['inline_keyboard' => $keyboard_buttons];

            $data_to_cache = [$caption, $keyboard];
            file_put_contents($cache_file, json_encode($data_to_cache));

            return $data_to_cache;
        } catch (Exception $e) {
            $this->logger->error("Gagal membuat laporan konten untuk seller: " . $e->getMessage(), ['package_id' => $package['id']]);
            // Note: We don't send message to Telegram API from here, as this class is for report generation only.
            // The calling command will handle sending messages.
            return [null, null]; // Indicate error
        }
    }
}
