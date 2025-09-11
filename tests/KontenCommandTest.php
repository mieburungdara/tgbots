<?php

namespace TGBot\Tests;


use PHPUnit\Framework\TestCase;
use TGBot\App;
use TGBot\Handlers\Commands\KontenCommand;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use TGBot\Database\SubscriptionRepository;
use TGBot\Database\FeatureChannelRepository;
use TGBot\Database\UserRepository;
use TGBot\Database\PackageViewRepository;
use TGBot\TelegramAPI; // Asumsi ada kelas TelegramAPI
use PDO;
use PDOStatement;
use TGBot\Logger;

class KontenCommandTest extends TestCase
{
    private $appMock;
    private $telegramApiMock;
    private $packageRepoMock;
    private $saleRepoMock;
    private $subscriptionRepoMock;
    private $featureChannelRepoMock;
    private $userRepoMock;
    private $viewRepoMock;
    private $loggerMock;
    private $kontenCommand;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock TelegramAPI
        $this->telegramApiMock = $this->createMock(TelegramAPI::class);
        $this->telegramApiMock->method('sendMessage')->willReturn(['ok' => true]);
        $this->telegramApiMock->method('copyMessage')->willReturn(['ok' => true]);

        // Mock Repositories
        $this->packageRepoMock = $this->createMock(MediaPackageRepository::class);
        $this->saleRepoMock = $this->createMock(SaleRepository::class);
        $this->subscriptionRepoMock = $this->createMock(SubscriptionRepository::class);
        $this->featureChannelRepoMock = $this->createMock(FeatureChannelRepository::class);
        $this->userRepoMock = $this->createMock(UserRepository::class);
        $this->viewRepoMock = $this->createMock(PackageViewRepository::class);
        $this->loggerMock = $this->createMock(Logger::class);

        // Mock App object
        $this->appMock = $this->createMock(App::class);
        $this->appMock->telegram_api = $this->telegramApiMock;
        $this->appMock->chat_id = 12345;
        $this->appMock->user = ['id' => 67890, 'role' => 'User'];
        // Mock PDO and PDOStatement
        $pdoStatementMock = $this->createMock(PDOStatement::class);
        $pdoStatementMock->method('execute')->willReturn(true);
        // Konfigurasi fetch untuk getAnalyticsForPackage
                $this->appMock->bot = ['id' => 123, 'name' => 'TestBot']; // Inisialisasi properti bot

        $this->kontenCommand = new KontenCommand(
            $this->packageRepoMock,
            $this->saleRepoMock,
            $this->subscriptionRepoMock,
            $this->featureChannelRepoMock,
            $this->userRepoMock,
            $this->viewRepoMock,
            $this->loggerMock
        );
    }

    // Test case untuk format perintah yang salah
    public function testExecute_invalidCommandFormat(): void
    {
        $message = [];
        $parts = ['/konten']; // Hanya satu bagian

        $this->telegramApiMock->expects($this->once())
                              ->method('sendMessage')
                              ->with($this->appMock->chat_id, "Format perintah salah. Gunakan: /konten <ID Konten>");

        $this->kontenCommand->execute($this->appMock, $message, $parts);
    }

    // Test case untuk konten tidak ditemukan
    public function testExecute_packageNotFound(): void
    {
        $message = [];
        $publicId = 'NONEXISTENT';
        $parts = ['/konten', $publicId];

        $this->packageRepoMock->method('findByPublicId')->willReturn(null);

        $this->telegramApiMock->expects($this->once())
                              ->method('sendMessage')
                              ->with($this->appMock->chat_id, "Konten dengan ID `{$publicId}` tidak ditemukan.", 'Markdown');

        // Pastikan getFilesByPackageId tidak dipanggil
        $this->packageRepoMock->expects($this->never())
                              ->method('getFilesByPackageId');

        $this->kontenCommand->execute($this->appMock, $message, $parts);
    }

    // Test case untuk penjual melihat kontennya sendiri
    public function testExecute_sellerViewsOwnContent(): void
    {
        $message = [];
        $publicId = 'ABCDE';
        $parts = ['/konten', $publicId];
        $package = [
            'id' => 1,
            'public_id' => $publicId,
            'seller_user_id' => $this->appMock->user['id'], // Penjual adalah pengguna saat ini
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'available',
            'created_at' => '2025-01-01 10:00:00'
        ];
        $mediaFiles = [['type' => 'photo', 'file_size' => 1024 * 1024]]; // 1MB photo
        $thumbnail = ['storage_channel_id' => 111, 'storage_message_id' => 222];

        $this->packageRepoMock->method('findByPublicId')->willReturn($package);
        $this->packageRepoMock->method('getFilesByPackageId')->willReturn($mediaFiles);
        $this->packageRepoMock->method('getThumbnailFile')->willReturn($thumbnail);
        $this->featureChannelRepoMock->method('findAllByOwnerAndFeature')->willReturn([]); // No sales channels
        $this->saleRepoMock->method('getAnalyticsForPackage')->willReturn(['sales_count' => 0, 'views_count' => 0, 'offers_count' => 0]);
        $this->viewRepoMock->method('logView');

        $this->telegramApiMock->expects($this->once())
                              ->method('copyMessage'); // Cukup pastikan dipanggil

        $this->kontenCommand->execute($this->appMock, $message, $parts);
    }

    // Test case untuk pengguna yang sudah membeli atau admin
    public function testExecute_purchasedOrAdminUser(): void
    {
        $message = [];
        $publicId = 'ABCDE';
        $parts = ['/konten', $publicId];
        $package = [
            'id' => 1,
            'public_id' => $publicId,
            'seller_user_id' => 99999, // Bukan penjual
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'available',
            'created_at' => '2025-01-01 10:00:00'
        ];
        $mediaFiles = [['type' => 'photo', 'file_size' => 1024 * 1024]];
        $thumbnail = ['storage_channel_id' => 111, 'storage_message_id' => 222];

        $this->packageRepoMock->method('findByPublicId')->willReturn($package);
        $this->packageRepoMock->method('getFilesByPackageId')->willReturn($mediaFiles);
        $this->packageRepoMock->method('getThumbnailFile')->willReturn($thumbnail);
        $this->saleRepoMock->method('hasUserPurchased')->willReturn(true); // Sudah membeli
        $this->subscriptionRepoMock->method('hasActiveSubscription')->willReturn(false);
        $this->viewRepoMock->method('logView');

        $this->telegramApiMock->expects($this->once())
                              ->method('copyMessage')
                              ->with(
                                  $this->appMock->chat_id,
                                  $thumbnail['storage_channel_id'],
                                  $thumbnail['storage_message_id'],
                                  $this->stringContains('Deskripsi konten'),
                                  'Markdown',
                                  $this->stringContains('Lihat Selengkapnya')
                              );

        $this->kontenCommand->execute($this->appMock, $message, $parts);
    }

    // Test case untuk pengunjung biasa melihat konten yang tersedia
    public function testExecute_visitorViewsAvailableContent(): void
    {
        $message = [];
        $publicId = 'ABCDE';
        $parts = ['/konten', $publicId];
        $package = [
            'id' => 1,
            'public_id' => $publicId,
            'seller_user_id' => 99999, // Bukan penjual
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'available',
            'created_at' => '2025-01-01 10:00:00'
        ];
        $mediaFiles = [['type' => 'photo', 'file_size' => 1024 * 1024]];
        $thumbnail = ['storage_channel_id' => 111, 'storage_message_id' => 222];
        $seller = ['id' => 99999, 'subscription_price' => 50000]; // Penjual menawarkan langganan

        $this->packageRepoMock->method('findByPublicId')->willReturn($package);
        $this->packageRepoMock->method('getFilesByPackageId')->willReturn($mediaFiles);
        $this->packageRepoMock->method('getThumbnailFile')->willReturn($thumbnail);
        $this->saleRepoMock->method('hasUserPurchased')->willReturn(false);
        $this->subscriptionRepoMock->method('hasActiveSubscription')->willReturn(false);
        $this->userRepoMock->method('findUserByTelegramId')->willReturn($seller);
        $this->viewRepoMock->method('logView');

        $this->telegramApiMock->expects($this->once())
                              ->method('copyMessage')
                              ->with(
                                  $this->appMock->chat_id,
                                  $thumbnail['storage_channel_id'],
                                  $thumbnail['storage_message_id'],
                                  $this->stringContains('Deskripsi konten'),
                                  'Markdown',
                                  $this->stringContains('Beli') // Memastikan tombol beli ada
                              );

        $this->kontenCommand->execute($this->appMock, $message, $parts);
    }

    // Test case untuk pengunjung biasa melihat konten yang tidak tersedia (misal: sold)
    public function testExecute_visitorViewsUnavailableContent(): void
    {
        $message = [];
        $publicId = 'ABCDE';
        $parts = ['/konten', $publicId];
        $package = [
            'id' => 1,
            'public_id' => $publicId,
            'seller_user_id' => 99999,
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'sold', // Status tidak tersedia
            'created_at' => '2025-01-01 10:00:00'
        ];

        $this->packageRepoMock->method('findByPublicId')->willReturn($package);
        $this->saleRepoMock->method('hasUserPurchased')->willReturn(false);
        $this->subscriptionRepoMock->method('hasActiveSubscription')->willReturn(false);
        $this->viewRepoMock->method('logView');

        $this->telegramApiMock->expects($this->once())
                              ->method('sendMessage')
                              ->with($this->appMock->chat_id, $this->stringContains('Konten ini sudah terjual.'));

        $this->telegramApiMock->expects($this->never())
                              ->method('copyMessage'); // copyMessage tidak boleh dipanggil

        $this->kontenCommand->execute($this->appMock, $message, $parts);
    }

    // Test case untuk thumbnail tidak ditemukan
    public function testExecute_thumbnailNotFound(): void
    {
        $message = [];
        $publicId = 'ABCDE';
        $parts = ['/konten', $publicId];
        $package = [
            'id' => 1,
            'public_id' => $publicId,
            'seller_user_id' => 99999,
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'available',
            'created_at' => '2025-01-01 10:00:00'
        ];
        $mediaFiles = [['type' => 'photo', 'file_size' => 1024 * 1024]];

        $this->packageRepoMock->method('findByPublicId')->willReturn($package);
        $this->packageRepoMock->method('getFilesByPackageId')->willReturn($mediaFiles);
        $this->packageRepoMock->method('getThumbnailFile')->willReturn(null); // Thumbnail tidak ditemukan
        $this->viewRepoMock->method('logView');

        $this->telegramApiMock->expects($this->once())
                              ->method('sendMessage')
                              ->with($this->appMock->chat_id, $this->stringContains('Konten ini tidak memiliki media yang dapat ditampilkan'));

        $this->telegramApiMock->expects($this->never())
                              ->method('copyMessage');

        $this->kontenCommand->execute($this->appMock, $message, $parts);
    }

    // Test case untuk generateMediaSummary
    public function testGenerateMediaSummary(): void
    {
        $mediaFiles = [
            ['type' => 'photo', 'file_size' => 1000000], // 1MB
            ['type' => 'video', 'file_size' => 2000000], // 2MB
            ['type' => 'photo', 'file_size' => 500000]   // 0.5MB
        ];

        // Menggunakan Reflection untuk mengakses metode privat
        $reflection = new \ReflectionClass(KontenCommand::class);
        $method = $reflection->getMethod('generateMediaSummary');
        $method->setAccessible(true);

        $summary = $method->invokeArgs($this->kontenCommand, [$mediaFiles]);

        $this->assertStringContainsString('2P1V', $summary);
        $this->assertStringContainsString('3.34MB', $summary); // (1+2+0.5)MB = 3.5MB, rounded to 3.34MB
    }

    // Test case untuk generateSellerReport (memastikan cache berfungsi dan laporan dibuat)
    public function testGenerateSellerReport(): void
    {
        $publicId = 'ABCDE';
        $package = [
            'id' => 1,
            'public_id' => $publicId,
            'seller_user_id' => $this->appMock->user['id'],
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'available',
            'created_at' => '2025-01-01 10:00:00'
        ];
        $description = 'Deskripsi konten';

        $this->saleRepoMock->method('getAnalyticsForPackage')->willReturn(['sales_count' => 5, 'views_count' => 100, 'offers_count' => 10]);
        $this->featureChannelRepoMock->method('findAllByOwnerAndFeature')->willReturn([]);

        // Menggunakan Reflection untuk mengakses metode privat
        $reflection = new \ReflectionClass(KontenCommand::class);
        $method = $reflection->getMethod('generateSellerReport');
        $method->setAccessible(true);

        list($caption, $keyboard) = $method->invokeArgs($this->kontenCommand, [$this->appMock, $package, $description]);

        $this->assertStringContainsString('Laporan Konten', $caption);
        $this->assertStringContainsString('ID Konten: `ABCDE`', $caption);
        $this->assertStringContainsString('Jumlah Terjual: 5 kali', $caption);
        $this->assertStringContainsString('Total Pendapatan: Rp 50.000', $caption);
        $this->assertStringContainsString('Tingkat Konversi: 5%', $caption);
        $this->assertArrayHasKey('inline_keyboard', $keyboard);
        $this->assertStringContainsString('Lihat Selengkapnya', $keyboard['inline_keyboard'][0][0]['text']);
    }

    // Test case untuk handleSellerView
    public function testHandleSellerView(): void
    {
        $publicId = 'ABCDE';
        $package = [
            'id' => 1,
            'public_id' => $publicId,
            'seller_user_id' => $this->appMock->user['id'],
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'available',
            'created_at' => '2025-01-01 10:00:00'
        ];
        $description = 'Deskripsi konten';

        $this->saleRepoMock->method('getAnalyticsForPackage')->willReturn(['sales_count' => 5, 'views_count' => 100, 'offers_count' => 10]);
        $this->featureChannelRepoMock->method('findAllByOwnerAndFeature')->willReturn([]);

        $reflection = new \ReflectionClass(KontenCommand::class);
        $method = $reflection->getMethod('handleSellerView');
        $method->setAccessible(true);

        list($caption, $keyboard) = $method->invokeArgs($this->kontenCommand, [$this->appMock, $package, $description]);

        $this->assertStringContainsString('Laporan Konten', $caption);
        $this->assertArrayHasKey('inline_keyboard', $keyboard);
    }

    // Test case untuk handlePurchasedOrAdminView
    public function testHandlePurchasedOrAdminView(): void
    {
        $publicId = 'ABCDE';
        $package = [
            'id' => 1,
            'public_id' => $publicId,
            'seller_user_id' => 99999,
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'available',
            'created_at' => '2025-01-01 10:00:00'
        ];
        $description = 'Deskripsi konten';

        $reflection = new \ReflectionClass(KontenCommand::class);
        $method = $reflection->getMethod('handlePurchasedOrAdminView');
        $method->setAccessible(true);

        list($caption, $keyboard) = $method->invokeArgs($this->kontenCommand, [$this->appMock, $package, $description]);

        $this->assertEquals($description, $caption);
        $this->assertArrayHasKey('inline_keyboard', $keyboard);
        $this->assertStringContainsString('Lihat Selengkapnya', $keyboard['inline_keyboard'][0][0]['text']);
    }

    // Test case untuk handleVisitorView (available)
    public function testHandleVisitorView_available(): void
    {
        $publicId = 'ABCDE';
        $package = [
            'id' => 1,
            'public_id' => $publicId,
            'seller_user_id' => 99999,
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'available',
            'created_at' => '2025-01-01 10:00:00'
        ];
        $description = 'Deskripsi konten';
        $seller = ['id' => 99999, 'subscription_price' => 50000];

        $this->userRepoMock->method('findUserByTelegramId')->willReturn($seller);
        $this->subscriptionRepoMock->method('hasActiveSubscription')->willReturn(false);

        $reflection = new \ReflectionClass(KontenCommand::class);
        $method = $reflection->getMethod('handleVisitorView');
        $method->setAccessible(true);

        list($caption, $keyboard) = $method->invokeArgs($this->kontenCommand, [$this->appMock, $package, $description]);

        $this->assertEquals($description, $caption);
        $this->assertArrayHasKey('inline_keyboard', $keyboard);
        $this->assertStringContainsString('Beli', $keyboard['inline_keyboard'][0][0]['text']);
        $this->assertStringContainsString('Hadiahkan', $keyboard['inline_keyboard'][0][1]['text']);
        $this->assertStringContainsString('Langganan', $keyboard['inline_keyboard'][0][2]['text']);
        $this->assertStringContainsString('Tanya Penjual', $keyboard['inline_keyboard'][1][0]['text']);
    }

    // Test case untuk handleVisitorView (sold)
    public function testHandleVisitorView_sold(): void
    {
        $publicId = 'ABCDE';
        $package = [
            'id' => 1,
            'public_id' => $publicId,
            'seller_user_id' => 99999,
            'description' => 'Deskripsi konten',
            'price' => 10000,
            'status' => 'sold',
            'created_at' => '2025-01-01 10:00:00'
        ];
        $description = 'Deskripsi konten';

        $reflection = new \ReflectionClass(KontenCommand::class);
        $method = $reflection->getMethod('handleVisitorView');
        $method->setAccessible(true);

        list($caption, $keyboard) = $method->invokeArgs($this->kontenCommand, [$this->appMock, $package, $description]);

        $this->assertNull($caption); // Should return null to indicate stopping execution
        $this->assertNull($keyboard); // Should return null
    }
}
