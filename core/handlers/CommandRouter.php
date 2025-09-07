<?php

namespace TGBot\Handlers;

use TGBot\Handlers\Commands\CommandInterface;
use TGBot\Handlers\Commands\StartCommand;
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

class CommandRouter
{
    private $commands = [];

    public function __construct()
    {
        $this->commands = [
            '/start' => new StartCommand(),
            '/sell' => new SellCommand(),
            '/rate' => new RateCommand(),
            '/tanya' => new TanyaCommand(),
            '/addmedia' => new AddMediaCommand(),
            '/konten' => new KontenCommand(),
            '/balance' => new BalanceCommand(),
            '/login' => new LoginCommand(),
            '/me' => new MeCommand(),
            '/help' => new HelpCommand(),
            '/about' => new AboutCommand(),
            '/about' => new AboutCommand(),
        ];
        $adminCommand = new AdminCommand();
        $this->commands['/dev_addsaldo'] = $adminCommand;
        $this->commands['/feature'] = $adminCommand;
    }

    public function getCommand(string $command_name): ?CommandInterface
    {
        return $this->commands[$command_name] ?? null;
    }
}
