<?php

declare(strict_types=1);

namespace Tests\Unit;

use TGBot\Controllers\WebhookController;
use TGBot\Logger;
use Codeception\Stub;
use PHPUnit\Framework\TestCase; // Use PHPUnit's TestCase

// Mock classes for dependencies
class MockBotRepository extends \TGBot\Database\BotRepository {}
class MockRawUpdateRepository extends \TGBot\Database\RawUpdateRepository {}
class MockTelegramAPI extends \TGBot\TelegramAPI {}
class MockUpdateDispatcher extends \TGBot\UpdateDispatcher {}

final class WebhookControllerTest extends TestCase // Extend TestCase
{
    private ?int $lastHttpResponseCode = null; // Property to store the last HTTP response code
    // No _before method needed for simple setup, can use setUp() if needed

    private function createTestController(array $mocks = []): WebhookController
    {
        $logger = $this->createMock(Logger::class);
        $logger->method('info');
        $logger->method('error');
        $logger->method('warning');
        $logger->method('critical');

        // Create mocks for dependencies first
        $mockBotRepository = $this->createMock(MockBotRepository::class);
        $mockBotRepository->method('findBotByTelegramId')
                          ->willReturn(['id' => 1, 'token' => 'test_token', 'username' => 'test_bot']);

        $mockRawUpdateRepository = $this->createMock(MockRawUpdateRepository::class);
        $mockRawUpdateRepository->method('create')
                                ->willReturn(true); // Return true as it expects a bool

        $mockTelegramAPI = $this->createMock(MockTelegramAPI::class);
        $mockTelegramAPI->method('getMe')
                        ->willReturn(['ok' => true, 'result' => ['username' => 'test_bot_username']]);

        $mockUpdateDispatcher = $this->createMock(MockUpdateDispatcher::class);
        $mockUpdateDispatcher->method('dispatch');

        $controller = $this->getMockBuilder(WebhookController::class)
                             ->setConstructorArgs([$logger])
                             ->onlyMethods(['setHttpResponseCode', 'terminate', 'getPhpInput', 'getDbConnection', 'getBotRepository', 'getRawUpdateRepository', 'getTelegramAPI', 'getUpdateDispatcher'])
                             ->getMock();

        // Set up mocks for protected methods
        $controller->method('setHttpResponseCode')
                   ->will($this->returnCallback(function($code) { $this->lastHttpResponseCode = $code; }));
        $controller->method('terminate')
                   ->will($this->throwException(new \Exception('Controller terminated')));
        $controller->method('getPhpInput')
                   ->willReturn('{"update_id":123,"message":{"message_id":123,"from":{"id":123,"is_bot":false,"first_name":"Test"},"chat":{"id":123,"first_name":"Test","type":"private"},"date":123,"text":"/start"}}');
        $controller->method('getDbConnection')
                   ->willReturn(new \stdClass()); // Mock PDO connection

        // Pass the pre-configured mocks as return values for the protected methods
        $controller->method('getBotRepository')
                   ->willReturn($mockBotRepository);
        $controller->method('getRawUpdateRepository')
                   ->willReturn($mockRawUpdateRepository);
        $controller->method('getTelegramAPI')
                   ->willReturn($mockTelegramAPI);
        $controller->method('getUpdateDispatcher')
                   ->willReturn($mockUpdateDispatcher);

        // Apply specific mocks from $mocks array
        foreach ($mocks as $method => $mockValue) {
            if (is_callable($mockValue)) {
                $controller->method($method)->will($this->returnCallback($mockValue));
            } else {
                $controller->method($method)->willReturn($mockValue);
            }
        }

        return $controller;
    }

    public function testHandleValidWebhookCall(): void
    {
        $controller = $this->createTestController();
        try {
            $controller->handle(['id' => 123]);
            $this->fail('Expected Controller terminated exception was not thrown.');
        } catch (\Exception $e) {
            $this->assertEquals('Controller terminated', $e->getMessage());
        }
        $this->assertEquals(200, $this->lastHttpResponseCode);
    }

    public function testHandleInvalidBotId(): void
    {
        $controller = $this->createTestController();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Controller terminated');
        $controller->handle(['id' => 'invalid']);
        $this->assertEquals(400, $this->lastHttpResponseCode);
    }

    public function testHandleBotNotFound(): void
    {
        $controller = $this->createTestController([
            'getBotRepository' => Stub::make(MockBotRepository::class, [
                'findBotByTelegramId' => null
            ])
        ]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Controller terminated');
        $controller->handle(['id' => 123]);
        $this->assertEquals(404, $this->lastHttpResponseCode);
    }

    public function testHandleEmptyUpdateJson(): void
    {
        $controller = $this->createTestController([
            'getPhpInput' => function() { return ''; }
        ]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Controller terminated');
        $controller->handle(['id' => 123]);
        $this->assertEquals(200, $this->lastHttpResponseCode);
    }

    public function testHandleInvalidJson(): void
    {
        $controller = $this->createTestController([
            'getPhpInput' => function() { return 'invalid json'; }
        ]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Controller terminated');
        $controller->handle(['id' => 123]);
        $this->assertEquals(200, $this->lastHttpResponseCode);
    }

    public function testHandleDatabaseConnectionFailure(): void
    {
        $controller = $this->createTestController([
            'getDbConnection' => null
        ]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Controller terminated');
        $controller->handle(['id' => 123]);
        $this->assertEquals(500, $this->lastHttpResponseCode);
    }

    public function testHandleGetMeApiCallFailure(): void
    {
        $controller = $this->createTestController([
            'getTelegramAPI' => Stub::make(MockTelegramAPI::class, [
                'getMe' => ['ok' => false, 'description' => 'API error']
            ])
        ]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Controller terminated');
        $controller->handle(['id' => 123]);
        $this->assertEquals(200, $this->lastHttpResponseCode);
    }
}
