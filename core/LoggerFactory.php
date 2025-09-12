<?php

namespace TGBot;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class LoggerFactory
{
    public static function create(string $name = 'app', string $logFile = 'logs/app.log'): Logger
    {
        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
        return $logger;
    }
}
