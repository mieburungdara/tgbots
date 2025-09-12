<?php

namespace TGBot\Handlers;

use TGBot\Handlers\Commands\CommandInterface;
use TGBot\Handlers\Commands\StartCommand;
use TGBot\Handlers\Commands\SetPriceCommand;
use TGBot\Handlers\Commands\SellCommand;
use TGBot\Handlers\Commands\RateCommand;
use TGBot\Handlers\Commands\TanyaCommand;
use TGBot\Handlers\Commands\AddMediaCommand;
use TGBot\Handlers\Commands\KontenCommand;
use TGBot\Handlers\Commands\BalanceCommand;
use TGBot\Handlers\Commands\LoginCommand;
use TGBot\Handlers\Commands\MeCommand;
use TGBot\Handlers\Commands\HelpCommand;
use TGBot\Handlers\Commands\AboutCommand;
use TGBot\Handlers\Commands\AdminCommand;
use TGBot\Database\MediaPackageRepository;
use TGBot\Database\SaleRepository;
use TGBot\Database\SubscriptionRepository;
use TGBot\Database\FeatureChannelRepository;
use TGBot\Database\UserRepository;
use TGBot\Database\PackageViewRepository;
use TGBot\SellerReportGenerator;

class CommandRouter
{
    private $commands = [];

    public function __construct()
    {
        $pdo = \get_db_connection();
        $logger = \TGBot\App::getLogger();

        $mediaPackageRepo = new MediaPackageRepository($pdo);
        $saleRepo = new SaleRepository($pdo);
        $subscriptionRepo = new SubscriptionRepository($pdo);
        $featureChannelRepo = new FeatureChannelRepository($pdo);
        $userRepo = new UserRepository($pdo, 0); // bot_id is not available here, this might be a problem
        $packageViewRepo = new PackageViewRepository($pdo);
        $sellerReportGenerator = new SellerReportGenerator($saleRepo, $featureChannelRepo, $logger);

        $this->commands = [
            '/start' => new StartCommand(),
            '/sell' => new SellCommand(),
            '/setprice' => new SetPriceCommand(),
            '/rate' => new RateCommand(),
            '/tanya' => new TanyaCommand(),
            '/addmedia' => new AddMediaCommand(),
            '/konten' => new KontenCommand(
                $mediaPackageRepo,
                $saleRepo,
                $subscriptionRepo,
                $featureChannelRepo,
                $userRepo,
                $packageViewRepo,
                $logger,
                $sellerReportGenerator
            ),
            '/balance' => new BalanceCommand(),
            '/login' => new LoginCommand(),
            '/me' => new MeCommand(),
            '/help' => new HelpCommand(),
            '/about' => new AboutCommand(),
        ];
        $adminCommand = new AdminCommand();
        $this->commands['/dev_addsaldo'] = $adminCommand;
        $this->commands['/feature'] = $adminCommand;
    }

    public function getCommand(string $command_name): ?CommandInterface
    {
        return isset($this->commands[$command_name]) ? $this->commands[$command_name] : null;
    }
}
