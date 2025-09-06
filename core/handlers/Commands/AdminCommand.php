<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;

class AdminCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        if ($app->user['role'] !== 'Admin') {
            return;
        }

        $command = $parts[0];

        // The original file had no specific logic for admin commands here.
        // This can be expanded as needed.
        switch ($command) {
            case '/dev_addsaldo':
                // Logic for adding balance would go here.
                break;
            case '/feature':
                // Logic for featuring a package would go here.
                break;
        }
    }
}
