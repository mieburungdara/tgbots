<?php

namespace TGBot\Tests;

use PHPUnit\Framework\TestCase;
use TGBot\App;
use TGBot\Database\SaleRepository;
use TGBot\Database\FeatureChannelRepository;
use TGBot\Logger;
use TGBot\SellerReportGenerator;

class SellerReportGeneratorTest extends TestCase
{
    private $saleRepoMock;
    private $featureChannelRepoMock;
    private $loggerMock;
    private $sellerReportGenerator;
    private $appMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->saleRepoMock = $this->createMock(SaleRepository::class);
        $this->featureChannelRepoMock = $this->createMock(FeatureChannelRepository::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->sellerReportGenerator = new SellerReportGenerator(
            $this->saleRepoMock,
            $this->featureChannelRepoMock,
            $this->loggerMock
        );

        $this->appMock = $this->createMock(App::class);
        $this->appMock->user = ['id' => 123, 'role' => 'Seller'];
    }

    public function testGenerateReport_success(): void
    {
        $package = [
            'id' => 1,
            'public_id' => 'ABCDE',
            'seller_user_id' => 123,
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'available',
            'created_at' => '2025-01-01 10:00:00'
        ];
        $description = 'Deskripsi konten';

        $this->saleRepoMock->method('getAnalyticsForPackage')
                           ->willReturn(['sales_count' => 5, 'views_count' => 100, 'offers_count' => 10]);
        $this->featureChannelRepoMock->method('findAllByOwnerAndFeature')
                                     ->willReturn([]);

        list($caption, $keyboard) = $this->sellerReportGenerator->generateReport($this->appMock, $package, $description);

        $this->assertStringContainsString('Laporan Konten', $caption);
        $this->assertStringContainsString('ID Konten: `ABCDE`', $caption);
        $this->assertStringContainsString('Jumlah Terjual: 5 kali', $caption);
        $this->assertStringContainsString('Total Pendapatan: Rp 50.000', $caption);
        $this->assertStringContainsString('Tingkat Konversi: 5%', $caption);
        $this->assertArrayHasKey('inline_keyboard', $keyboard);
        $this->assertStringContainsString('Lihat Selengkapnya', $keyboard['inline_keyboard'][0][0]['text']);
    }

    public function testGenerateReport_errorHandling(): void
    {
        // Clear cache before test
        $cache_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgbots_cache';
        $cache_file = $cache_dir . DIRECTORY_SEPARATOR . 'seller_report_1.json'; // Assuming package ID is 1
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }

        $package = [
            'id' => 1,
            'public_id' => 'ABCDE',
            'seller_user_id' => 123,
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'available',
            'created_at' => '2025-01-01 10:00:00'
        ];
        $description = 'Deskripsi konten';

        $this->saleRepoMock->method('getAnalyticsForPackage')
                           ->willThrowException(new \Exception("Database error"));

        $this->loggerMock->expects($this->once())
                         ->method('error')
                         ->with($this->stringContains("Gagal membuat laporan konten untuk seller"));

        list($caption, $keyboard) = $this->sellerReportGenerator->generateReport($this->appMock, $package, $description);

        $this->assertNull($caption);
        $this->assertNull($keyboard);
    }
}
