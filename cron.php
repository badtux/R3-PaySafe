<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

use MongoDB\Client;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Fetch MPGS order details for given merchant/order.
 */
function fetchGatewayOrder(string $merchantId, string $orderId, string $apiPassword): array
{
    $gatewayUrl = "https://cbcmpgs.gateway.mastercard.com/api/rest/version/57/merchant/{$merchantId}/order/{$orderId}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $gatewayUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "merchant.{$merchantId}:{$apiPassword}");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        throw new RuntimeException("Gateway request failed for orderId={$orderId}, httpCode={$httpCode}, curlErr={$curlErr}");
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data)) {
        throw new RuntimeException("Unable to decode gateway response for orderId={$orderId}");
    }

    return $data;
}

/**
 * Extract only PAYMENT attempts from MPGS response. No nulls.
 */
function buildPaymentAttempts(array $data): array
{
    $attempts = [];

    $transactions = $data['transaction'] ?? [];
    if (!is_array($transactions)) {
        return [];
    }

    foreach ($transactions as $txn) {
        $txnType = strtoupper((string)($txn['transaction']['type'] ?? ''));
        if ($txnType !== 'PAYMENT') {
            continue;
        }

        $attemptOrderStatus = strtoupper((string)($txn['order']['status'] ?? ''));
        $attemptCapturedAmount = (float)($txn['order']['totalCapturedAmount'] ?? 0);
        $attemptResultRaw = strtoupper((string)($txn['result'] ?? ''));

        // Consider SUCCESS if any capture happened, even if later refunded (PARTIALLY_REFUNDED/REFUNDED).
        $attemptHadCapture = ($attemptCapturedAmount > 0);

        // Derive final result for this PAYMENT attempt.
        // If capturedAmount is 0, treat as FAIL even if gateway says SUCCESS.
        $attemptFinalResult = $attemptHadCapture ? 'SUCCESS' : 'FAIL';
        if ($attemptResultRaw === 'FAILURE') {
            $attemptFinalResult = 'FAIL';
        }

        $attempts[] = [
            'type' => 'PAYMENT',
            'finalResult' => $attemptFinalResult,
            'resultRaw' => $attemptResultRaw !== '' ? $attemptResultRaw : 'UNKNOWN',
            'gatewayCode' => (string)($txn['response']['gatewayCode'] ?? ''),
            'acquirerCode' => (string)($txn['response']['acquirerCode'] ?? ''),
            'acquirerMessage' => (string)($txn['response']['acquirerMessage'] ?? ''),
            'amount' => (float)($txn['transaction']['amount'] ?? ($txn['order']['amount'] ?? 0)),
            'currency' => (string)($txn['transaction']['currency'] ?? ($txn['order']['currency'] ?? '')),
            'timeOfRecord' => (string)($txn['timeOfRecord'] ?? ''),
            'timeOfLastUpdate' => (string)($txn['timeOfLastUpdate'] ?? ''),
            'order' => [
                'status' => (string)($txn['order']['status'] ?? ''),
                'totalAuthorizedAmount' => (float)($txn['order']['totalAuthorizedAmount'] ?? 0),
                'totalCapturedAmount' => (float)($txn['order']['totalCapturedAmount'] ?? 0),
                'totalRefundedAmount' => (float)($txn['order']['totalRefundedAmount'] ?? 0),
            ],
            'mpgsTransactionId' => (string)($txn['transaction']['id'] ?? ''),
            'receipt' => (string)($txn['transaction']['receipt'] ?? ''),
            'acquirerTransactionId' => (string)($txn['transaction']['acquirer']['transactionId'] ?? ''),
            'acquirerId' => (string)($txn['transaction']['acquirer']['id'] ?? ''),
            'stan' => (string)($txn['transaction']['stan'] ?? ''),
            'authorizationCode' => (string)($txn['transaction']['authorizationCode'] ?? ''),
            'card' => [
                'brand' => (string)($txn['sourceOfFunds']['provided']['card']['brand'] ?? ($data['sourceOfFunds']['provided']['card']['brand'] ?? '')),
                'scheme' => (string)($txn['sourceOfFunds']['provided']['card']['scheme'] ?? ($data['sourceOfFunds']['provided']['card']['scheme'] ?? '')),
                'fundingMethod' => (string)($txn['sourceOfFunds']['provided']['card']['fundingMethod'] ?? ($data['sourceOfFunds']['provided']['card']['fundingMethod'] ?? '')),
                'number' => (string)($txn['sourceOfFunds']['provided']['card']['number'] ?? ($data['sourceOfFunds']['provided']['card']['number'] ?? '')),
                'nameOnCard' => (string)($txn['sourceOfFunds']['provided']['card']['nameOnCard'] ?? ''),
            ],
        ];
    }

    return $attempts;
}

/**
 * Determine transactionId based on sourceOfFunds.type
 */
function chooseTransactionId(array $data, ?array $paymentTxn): string
{
    $sourceType = strtoupper((string)($data['sourceOfFunds']['type'] ?? ''));

    $top3dsTxnId = $data['authentication']['3ds']['transactionId'] ?? '';
    $acquirerTxnId = $paymentTxn['transaction']['acquirer']['transactionId'] ?? '';
    $receiptTxnId = $paymentTxn['transaction']['receipt'] ?? '';
    $mpgsTxnId = $paymentTxn['transaction']['id'] ?? '';

    if ($sourceType === 'CARD' && $top3dsTxnId !== '') {
        return (string)$top3dsTxnId;
    }
    if ($sourceType === 'UNION_PAY' && $acquirerTxnId !== '') {
        return (string)$acquirerTxnId;
    }

    return (string)($acquirerTxnId !== '' ? $acquirerTxnId : ($receiptTxnId !== '' ? $receiptTxnId : ($mpgsTxnId !== '' ? $mpgsTxnId : ($top3dsTxnId !== '' ? $top3dsTxnId : 'not-set'))));
}

/**
 * Pick first PAYMENT txn from transaction[]
 */
function findPaymentTxn(array $data): ?array
{
    $transactions = $data['transaction'] ?? [];
    if (!is_array($transactions)) {
        return null;
    }

    foreach ($transactions as $txn) {
        $type = strtoupper((string)($txn['transaction']['type'] ?? ''));
        if ($type === 'PAYMENT') {
            return $txn;
        }
    }

    return null;
}

/**
 * Compute overall status from top-level + captured + payment approval.
 */
function computeFinalStatus(array $data, ?array $paymentTxn): array
{
    $orderStatus = strtoupper((string)($data['status'] ?? ''));
    $topResult = strtoupper((string)($data['result'] ?? ''));
    $capturedAmount = (float)($data['totalCapturedAmount'] ?? 0);

    // Treat CAPTURED/REFUNDED/PARTIALLY_REFUNDED as a successful payment if any capture happened.
    $isCapturedLike = in_array($orderStatus, ['CAPTURED', 'REFUNDED', 'PARTIALLY_REFUNDED'], true) && ($capturedAmount > 0);

    $paymentGatewayCode = strtoupper((string)($paymentTxn['response']['gatewayCode'] ?? ''));
    $paymentResult = strtoupper((string)($paymentTxn['result'] ?? ''));
    $isApprovedPayment = ($paymentResult === 'SUCCESS') && ($paymentGatewayCode === 'APPROVED');

    $isPaidSuccess = ($topResult === 'SUCCESS') && ($isCapturedLike || $isApprovedPayment);

    $mailStatus = $isPaidSuccess ? 'success' : 'payment error';
    if (in_array($topResult, ['CANCELLED', 'CANCELED'], true)) {
        $mailStatus = 'payment canceled';
        $isPaidSuccess = false;
    }

    $uiStatusText = match ($mailStatus) {
        'success' => 'SUCCESS',
        'payment canceled' => 'CANCELED',
        default => 'FAIL',
    };

    return [
        'isPaidSuccess' => $isPaidSuccess,
        'mailStatus' => $mailStatus,
        'finalPaymentStatus' => $uiStatusText,
        'captured' => $isCapturedLike,
        'capturedAmount' => $capturedAmount,
    ];
}

/**
 * Derive name on card with fallbacks.
 */
function chooseNameOnCard(array $data, ?array $paymentTxn, string $email): string
{
    $nameOnCard = $data['sourceOfFunds']['provided']['card']['nameOnCard']
        ?? $paymentTxn['sourceOfFunds']['provided']['card']['nameOnCard']
        ?? trim((string)(($data['customer']['firstName'] ?? '') . ' ' . ($data['customer']['lastName'] ?? '')));

    if (!is_string($nameOnCard) || trim($nameOnCard) === '') {
        $fallbackName = '';
        if ($email !== '' && str_contains($email, '@')) {
            $local = explode('@', $email, 2)[0];
            $local = str_replace(['.', '_', '-'], ' ', $local);
            $fallbackName = trim($local);
        }
        $nameOnCard = $fallbackName !== '' ? $fallbackName : 'not-set';
    }

    return (string)$nameOnCard;
}

/**
 * Send payment status email.
 */
function sendStatusEmail(
    string $mailStatus,
    string $email,
    string $orderId,
    string $transactionId,
    string $cardNumber,
    string $nameOnCard,
    string $amount,
    string $currency,
    array $data
): string {
    $subject = "Payment Status Update for - OID:$orderId ";

    if ($mailStatus === 'payment error') {
        $body = '
        <div style="font-family: Arial, sans-serif; color: #721c24; background-color: #f8d7da; padding: 20px; border-radius: 5px; border: 1px solid #f5c6cb;">
            <h2 style="color: #721c24; margin-top: 0;">❌ Payment Unsuccessful </h2>
            <div style="background-color: white; padding: 15px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                <table>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Transaction ID:</strong></td><td>' . htmlspecialchars($transactionId) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong> Card Number:</strong></td><td>' . htmlspecialchars($cardNumber) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong> Card Holder Name:</strong></td><td>' . htmlspecialchars($nameOnCard) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($amount) . ' ' . htmlspecialchars($currency) . '</td></tr>
                </table>
                <div style="margin-top: 15px; color: #856404; background-color: #fff3cd; padding: 10px; border-radius: 4px;">
                    <h4 style="margin: 0 0 5px 0;">Error Details:</h4>
                    <pre style="margin: 0; font-family: Consolas, monospace;">' . htmlspecialchars($data['error'] ?? 'Unknown error') . '</pre>
                </div>
            </div>
        </div>';
    } elseif ($mailStatus === 'payment canceled') {
        $body = '
        <div style="font-family: Arial, sans-serif; color: #856404; background-color: #fff3cd; padding: 20px; border-radius: 5px; border: 1px solid #ffeeba;">
            <h2 style="color: #BB6E2F; margin-top: 0;">⚠️ Payment Canceled </h2>
            <div style="background-color: white; padding: 15px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                <table>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Transaction ID:</strong></td><td>' . htmlspecialchars($transactionId) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong> Card Number:</strong></td><td>' . htmlspecialchars($cardNumber) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong> Card Holder Name:</strong></td><td>' . htmlspecialchars($nameOnCard) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($amount) . ' ' . htmlspecialchars($currency) . '</td></tr>
                </table>
            </div>
        </div>';
    } else {
        $body = '
        <div style="font-family: Arial, sans-serif; color: #155724; background-color: #d4edda; padding: 20px; border-radius: 5px; border: 1px solid #c3e6cb;">
            <h2 style="color:#155724; margin-top: 0;">✅ Payment Successful</h2>
            <div style="background-color: white; padding: 15px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0;">Order Details</h3>
                <table>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Order ID:</strong></td><td>' . htmlspecialchars($orderId) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Transaction ID:</strong></td><td>' . htmlspecialchars($transactionId) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong> Card Number:</strong></td><td>' . htmlspecialchars($cardNumber) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong> Card Holder Name:</strong></td><td>' . htmlspecialchars($nameOnCard) . '</td></tr>
                    <tr><td style="padding: 5px 10px 5px 0;"><strong>Amount:</strong></td><td>' . htmlspecialchars($amount) . ' ' . htmlspecialchars($currency) . '</td></tr>
                </table>
                <p style="margin: 15px 0 0 0; color: #155724;">Thank you for your payment with Malkey Rent A Car.</p>
            </div>
        </div>';
    }

    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;
        $mail->setFrom(MAIL_ADDRESS, MAIL_NAME);
        $mail->addAddress($email);
        foreach (CC_LIST as $cc) {
            $mail->addCC($cc);
        }
        foreach (BCC_LIST as $bcc) {
            $mail->addBCC($bcc);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return 'Email sent successfully.';
    } catch (MailException $e) {
        return 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
    }
}

function isValidEmail(string $email): bool
{
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ---- main ----

$dryRun = in_array('--dry-run', $argv, true);
$limit = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = (int)substr($arg, strlen('--limit='));
    }
}

// NEW: skip sending emails for documents that existed before this run
$skipExistingEmails = in_array('--skip-existing-emails', $argv, true);
// Capture run start timestamp (ms) to compare with document createdAt
$runStartMs = (int)(microtime(true) * 1000);
$runStartUtc = new \MongoDB\BSON\UTCDateTime($runStartMs);

$client = new Client(DATABASE_URL);
$collection = $client->{DB}->{COLLECTION};
$logCollection = $client->{DB}->cron_logs;

$filter = [
    'cron' => false,
];
$options = [];
if ($limit > 0) {
    $options['limit'] = $limit;
}

$cursor = $collection->find($filter, $options);
$docs = iterator_to_array($cursor, false);

if (count($docs) === 0) {
    echo "No records with cron=false\n";
    exit(0);
}

echo "Found " . count($docs) . " docs with cron=false\n";

foreach ($docs as $doc) {
    $uuid = (string)($doc['uuid'] ?? '');
    $orderId = (string)($doc['orderId'] ?? '');
    $currency = (string)($doc['currency'] ?? '');
    $email = (string)($doc['email'] ?? '');
    $docCron = $doc['cron'] ?? null;

    if ($uuid === '' || $orderId === '' || $currency === '') {
        continue;
    }

    if ($currency === 'LKR') {
        $merchantId = MERCHANT_ID_LKR;
        $apiPassword = API_PASSWORD_LKR;
    } else {
        $merchantId = MERCHANT_ID_USD;
        $apiPassword = API_PASSWORD_USD;
    }

    echo "Processing uuid={$uuid}, orderId={$orderId}, currency={$currency}\n";

    try {
        $data = fetchGatewayOrder($merchantId, $orderId, $apiPassword);
        $paymentTxn = findPaymentTxn($data);

        $statusInfo = computeFinalStatus($data, $paymentTxn);
        $attempts = buildPaymentAttempts($data);

        $gatewayResult = (string)($data['result'] ?? '');
        $device = $data['device'] ?? [];
        if (!is_array($device)) {
            $device = [];
        }

        $cardNumber = (string)($data['sourceOfFunds']['provided']['card']['number'] ?? ($paymentTxn['sourceOfFunds']['provided']['card']['number'] ?? ''));
        $cardBrand = (string)($data['sourceOfFunds']['provided']['card']['brand'] ?? ($paymentTxn['sourceOfFunds']['provided']['card']['brand'] ?? ''));
        $fundingMethord = (string)($data['sourceOfFunds']['provided']['card']['fundingMethod'] ?? ($paymentTxn['sourceOfFunds']['provided']['card']['fundingMethod'] ?? ''));
        $transactionId = chooseTransactionId($data, $paymentTxn);

        $lastUpdated = !empty($data['lastUpdatedTime'])
            ? new \MongoDB\BSON\UTCDateTime(strtotime((string)$data['lastUpdatedTime']) * 1000)
            : new \MongoDB\BSON\UTCDateTime();

        $nameOnCard = chooseNameOnCard($data, $paymentTxn, $email);
        $merchant = (string)($data['merchant'] ?? $merchantId);

        $updateData = [
            'paymentStatus' => (string)$statusInfo['finalPaymentStatus'],
            'gatewayResult' => $gatewayResult,
            'transactionId' => $transactionId,
            'nameOnCard' => $nameOnCard,
            'merchantId' => $merchant,
            'device' => $device,
            'cardBrand' => $cardBrand,
            'orderId' => (string)($data['id'] ?? $orderId),
            'fundingMethord' => $fundingMethord,
            'updatedAt' => $lastUpdated,
            'cardNumber' => $cardNumber,
            'captured' => (bool)$statusInfo['captured'],
            'capturedAmount' => (float)$statusInfo['capturedAmount'],
            'cron' => true,
            'attempts' => $attempts,
        ];

        if (isValidEmail($email)) {
            $updateData['email'] = $email;
        }

        if ($dryRun) {
            echo "DRY RUN update uuid={$uuid}, orderId={$orderId}, status={$updateData['paymentStatus']} attempts=" . count($attempts) . "\n";
            continue;
        }

        // Update the document by uuid regardless of its current cron value so we can mark attempts/updatedAt
        $collection->updateOne(
            ['uuid' => $uuid],
            ['$set' => $updateData]
        );
        if (isValidEmail($email) && $orderId !== '') {
            try {
                $collection->updateMany(
                    ['orderId' => $orderId],
                    [
                        '$set' => [
                            'email' => $email,
                            'updatedAt' => $updateData['updatedAt'] ?? new \MongoDB\BSON\UTCDateTime(),
                        ],
                    ]
                );
            } catch (Throwable $e2) {
            }
        }

        // Send emails if email is valid (no limit)
        // Determine whether to send email. Optionally skip existing documents created before run start.
        $shouldSendEmail = isValidEmail($email);
        $createdAtMs = 0;
        if (isset($doc['createdAt']) && $doc['createdAt'] instanceof \MongoDB\BSON\UTCDateTime) {
            $createdAtMs = (int)$doc['createdAt']->toDateTime()->getTimestamp() * 1000 + (int)floor($doc['createdAt']->toDateTime()->format('v'));
        } elseif (isset($doc['createdAt']) && is_numeric($doc['createdAt'])) {
            // some records may store createdAt as numeric ms
            $createdAtMs = (int)$doc['createdAt'];
        }

        if ($skipExistingEmails && $createdAtMs > 0 && $createdAtMs < $runStartMs) {
            $shouldSendEmail = false;
            $skipReason = 'existing document (created before this run)';
        } else {
            $skipReason = '';
        }

        // If document explicitly marked cron === -1, do not send email for it
        if ($docCron === -1) {
            echo "Email: skipped (cron=-1 on uuid={$uuid})\n";
        } elseif (!$shouldSendEmail) {
            $reasonText = $skipReason !== '' ? " skipped ({$skipReason} on uuid={$uuid})" : " skipped (missing/invalid email on uuid={$uuid})";
            echo "Email:{$reasonText}\n";
        } else {
            $amountStr = number_format((float)($data['amount'] ?? 0), 2, '.', '');
            $currencyStr = (string)($data['currency'] ?? $currency);
            $emailMsg = sendStatusEmail(
                (string)$statusInfo['mailStatus'],
                $email,
                (string)($data['id'] ?? $orderId),
                $transactionId,
                $cardNumber,
                $nameOnCard,
                $amountStr,
                $currencyStr,
                $data
            );
            echo "Email: {$emailMsg}\n";
        }

    } catch (Throwable $e) {
        try {
            $httpCode = null;
            if (preg_match('/httpCode=(\d+)/', $e->getMessage(), $m)) {
                $httpCode = (int)$m[1];
            }

            $logCollection->insertOne([
                'uuid' => $uuid,
                'orderId' => $orderId,
                'currency' => $currency,
                'merchantId' => $merchantId ?? null,
                'httpCode' => $httpCode,
                'message' => (string)$e->getMessage(),
                'createdAt' => new \MongoDB\BSON\UTCDateTime(),
            ]);
        } catch (Throwable $logErr) {
            // ignore log failures
        }

        if (!$dryRun && $uuid !== '') {
            try {
                $collection->updateOne(
                    ['uuid' => $uuid],
                    [
                        '$set' => [
                            'cron' => true,
                            'paymentStatus' => 'FAIL',
                            'gatewayResult' => 'ERROR',
                            'updatedAt' => new \MongoDB\BSON\UTCDateTime(),
                            'lastCronError' => [
                                'message' => (string)$e->getMessage(),
                                'httpCode' => isset($httpCode) ? $httpCode : null,
                                'at' => new \MongoDB\BSON\UTCDateTime(),
                            ],
                        ],
                    ]
                );
            } catch (Throwable $updateErr) {
                // ignore update failures
            }
        }

        fwrite(STDERR, "ERROR uuid={$uuid}, orderId={$orderId}: {$e->getMessage()}\n");
        continue;
    }
}

echo "Done\n";

