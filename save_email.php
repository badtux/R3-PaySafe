<?php
// Minimal endpoint: /cmb/save_email.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ensure no accidental output
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once __DIR__ . '/config/config.php';
require __DIR__ . '/vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

$debugOutput = ob_get_clean();

try {
    // prefer uuid
    $uuid = trim((string)($_POST['uuid'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    // fallback to session via txnId or session.uuid if uuid missing
    if ($uuid === '') {
        $txnId = isset($_GET['txnId']) ? (string)$_GET['txnId'] : (isset($_POST['txnId']) ? (string)$_POST['txnId'] : '');
        if ($txnId && isset($_SESSION['payments'][$txnId]['uuid'])) {
            $uuid = (string)$_SESSION['payments'][$txnId]['uuid'];
        } elseif (!empty($_SESSION['uuid'])) {
            $uuid = (string)$_SESSION['uuid'];
        }
    }

    if ($uuid === '') {
        http_response_code(400);
        error_log('save_email.php: missing uuid POST=' . json_encode($_POST));
        echo json_encode(['ok' => false, 'error' => 'Missing uuid']);
        exit;
    }

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        http_response_code(400);
        error_log('save_email.php: invalid email ' . $email . ' for uuid ' . $uuid);
        echo json_encode(['ok' => false, 'error' => 'Invalid email']);
        exit;
    }

    $client = new Client(DATABASE_URL);
    $dbName = DB;
    $collName = COLLECTION;
    $collection = $client->$dbName->$collName;

    $res = $collection->updateOne(
        ['uuid' => $uuid],
        ['$set' => ['email' => $email, 'updatedAt' => new UTCDateTime()]]
    );

    $matched = method_exists($res, 'getMatchedCount') ? $res->getMatchedCount() : null;
    $modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : null;

    error_log(sprintf('save_email.php: uuid=%s email=%s matched=%s modified=%s', $uuid, $email, $matched, $modified));

    echo json_encode(['ok' => true, 'uuid' => $uuid, 'matched' => $matched, 'modified' => $modified]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    error_log('save_email.php: exception: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
    exit;
}
