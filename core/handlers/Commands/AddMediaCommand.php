<?php

namespace TGBot\Handlers\Commands;

use TGBot\App;
use TGBot\Database\UserRepository;
use TGBot\Database\MediaPackageRepository;

class AddMediaCommand implements CommandInterface
{
    public function execute(App $app, array $message, array $parts): void
    {
        if (count($parts) > 1) {
            $this->addMediaToExistingPackage($app, $message, $parts[1]);
        } else {
            $this->addMediaToNewPackage($app, $message);
        }
    }

    private function addMediaToNewPackage(App $app, array $message): void
    {
        $user_repo = new UserRepository($app->pdo, $app->bot['id']);

        if ($app->user['state'] !== 'awaiting_price') {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Perintah ini hanya bisa digunakan saat Anda sedang dalam proses menjual item.");
            return;
        }
        if (!isset($message['reply_to_message'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Untuk menambah media, silakan reply media yang ingin Anda tambahkan.");
            return;
        }

        $replied_message = $message['reply_to_message'];
        // The original file had no further logic here.
        // This functionality appears to be incomplete in the source.
    }

    private function addMediaToExistingPackage(App $app, array $message, string $public_package_id): void
    {
        $package_repo = new MediaPackageRepository($app->pdo);

        if (!isset($message['reply_to_message'])) {
            $app->telegram_api->sendMessage($app->chat_id, "Untuk menambah media, silakan reply media yang ingin Anda tambahkan.");
            return;
        }

        $package = $package_repo->findByPublicId($public_package_id);
        if (!$package || $package['seller_user_id'] != $app->user['id']) {
            $app->telegram_api->sendMessage($app->chat_id, "⚠️ Anda tidak memiliki izin untuk mengubah paket ini.");
            return;
        }

        // The original file had no further logic here.
        // This functionality appears to be incomplete in the source.
    }
}
