<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script must be run from CLI.\n";
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

function isValidEmail(string $email): bool
{
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

$orderId = null;
$email = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--order-id=')) {
        $orderId = trim((string)substr($arg, strlen('--order-id=')));
    }
    if (str_starts_with($arg, '--email=')) {
        $email = trim((string)substr($arg, strlen('--email=')));
    }
}

if ($orderId === null || $orderId === '') {
    fwrite(STDERR, "Missing --order-id=...\n");
    exit(1);
}
if ($email === null || !isValidEmail($email)) {
    fwrite(STDERR, "Missing/invalid --email=...\n");
    exit(1);
}

$client = new Client(DATABASE_URL);
$collection = $client->{DB}->{COLLECTION};

$result = $collection->updateMany(
    ['orderId' => $orderId],
    [
        '$set' => [
            'email' => $email,
            'updatedAt' => new UTCDateTime(),
        ],
    ]
);

echo "Matched: {$result->getMatchedCount()} Updated: {$result->getModifiedCount()}\n";
