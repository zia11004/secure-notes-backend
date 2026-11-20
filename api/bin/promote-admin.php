<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Infrastructure\DataServiceClient;
use App\Support\Input;

require dirname(__DIR__).'/vendor/autoload.php';

if (count($argv) !== 2) {
    fwrite(STDERR, "Usage: php bin/promote-admin.php <username-or-email>\n");
    exit(2);
}

try {
    $data = new DataServiceClient(AppConfig::load(dirname(__DIR__)));
    $lookup = str_contains($argv[1], '@')
        ? ['email' => Input::email($argv[1])]
        : ['username' => Input::username($argv[1])];
    $user = $data->request('POST', '/users/lookup', $lookup);
    $data->request('PATCH', '/users/'.$user['id'], ['role' => 'admin', 'roleId' => null]);
    echo "Admin role granted to the existing account.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
