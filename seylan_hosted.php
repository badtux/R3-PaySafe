<?php
// Write some logs
$logger->info('in seylan_hosted.php file');

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use Ramsey\Uuid\Uuid;

$isURLQueryRequest = ($_SERVER['REQUEST_METHOD'] === 'GET') ? true : false;
$willAttemptInit = ($isURLQueryRequest 
    && isset($_GET['currency']) 
    && isset($_GET['amount']) 
    && isset($_GET['orderId']) 
    && isset($_GET['description']) 
    && !isset($_GET['txnId'])) ? true : false;

$hasInitiated = ($isURLQueryRequest 
    && !$willAttemptInit 
    && isset($_SESSION['txnId']) 
    && isset($_SESSION['sessionId'])) ? true : false;

function resetPaymentSession($amount, $currency, $orderId, $description, $address = null, $successUrl = null, $failedUrl = null){
    $_SESSION['payments'] = []; 
    unset($_SESSION['errorMessage']);
    $txnId = bin2hex(random_bytes(16)); 

    $uuid = Uuid::uuid4()->toString();
    $_SESSION['uuid'] = $uuid;

    $_SESSION['payments'][$txnId] = [
        'amount' => $amount,
        'currency' => $currency,
        'description' => $description,
        'orderId' => $orderId,
        'uuid' => $uuid,
        'address' => $address,
        'successUrl' => $successUrl,
        'failedUrl' => $failedUrl
    ];
            
    return $txnId;
}

function initiateCheckout($txnId, $logger) {
    $authString = 'merchant.'.MERCHANT_ID.':'.API_PASSWORD;
    $authHeader = "Authorization: Basic " . base64_encode($authString);
    $endPointUrl = IPG_API_URL.'/'.MERCHANT_ID.'/session';

    $data = [
        "apiOperation" => "INITIATE_CHECKOUT",
        "interaction" => [
            "operation" => "PURCHASE",
            "merchant" => [
                "name" => "HelpAge Sri Lanka",
                "logo" => MERCHANT_LOGO,
                "url" => "https://www.helpagesl.org/",
                "phone" => "+94 11 7418977",
                "email" => "helpage@sltnet.lk"
            ],
            "returnUrl" => REDIRECT_URL,
            "displayControl" => [
                "customerEmail" => "HIDE",
                "billingAddress" => "HIDE",
                "shipping" => "HIDE"
            ]
        ],
        "order" => [
            "currency" => $_SESSION['payments'][$txnId]['currency'],
            "amount" => $_SESSION['payments'][$txnId]['amount'],
            "id" => $_SESSION['payments'][$txnId]['orderId'],
            "description" => $_SESSION['payments'][$txnId]['description']
        ]
    ];

    $jsonData = json_encode($data);
    $logger->info("cURL JSON Data: " . $jsonData);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endPointUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",  
            "Cache-Control: no-cache",
            $authHeader
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $logger->info("cUrl Response: " . $response);

    if ($response === false) {
        $logger->error("Error while cUrl: " . curl_error($ch));
        throw new Exception("Error in checkout. Please try again or contact ".MERCHANT_NAME.".");
    }

    $result = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE 
        && isset($result['result']) 
        && $result['result'] === 'SUCCESS' 
        && isset($result['session']['id'])) {

        $sessionId = $result['session']['id'];
        $_SESSION['payments'][$txnId]['sessionId'] = $sessionId;
        $logger->info("Checkout initiated successfully. Session ID: " . $sessionId);
        return $sessionId;
    }

    $logger->error("Error initiating checkout: " . $response);
    throw new Exception("Error initiating checkout. Please try again.");
}

function saveTransactionToDatabase($txnId, $sessionId, $logger) {
    $payment = $_SESSION['payments'][$txnId] ?? null;
    if (!$payment) {
        $logger->error("Cannot save to DB: payment session missing for txnId $txnId");
        return false;
    }

    try {
        $client = new Client(DATABASE_URL);
        $collection = $client->selectDatabase(DB)->selectCollection(COLLECTION);

        $document = [
            'orderId'     => $payment['orderId'],
            'uuid'        => $payment['uuid'],
            'txnId'       => $txnId,
            'amount'      => (float)$payment['amount'],
            'currency'    => $payment['currency'],
            'description' => $payment['description'],
            'address'     => $payment['address'] ?? null,
            'merchantId'  => MERCHANT_ID,
            'sessionId'   => $sessionId,
            'bank'        => "Seylan Bank",
            'createdAt'   => new UTCDateTime()
        ];

        if (!empty($payment['successUrl']) || !empty($payment['failedUrl'])) {
            if (!empty($payment['successUrl'])) {
                $document['successUrl'] = $payment['successUrl'];
            }
            if (!empty($payment['failedUrl'])) {
                $document['failedUrl'] = $payment['failedUrl'];
            }
            $document['eCardEmail'] = false;
        }

        $result = $collection->insertOne($document);

        $insertedId = $result->getInsertedId();

        if ($result->getInsertedCount() === 1) {
            $logger->info("Transaction saved to MongoDB successfully", [
                'insertedId' => (string)$insertedId,
                'txnId'      => $txnId,
                'orderId'    => $payment['orderId']
            ]);
            return true;
        } else {
            $logger->error("Failed to insert transaction into MongoDB for txnId: $txnId");
            return false;
        }
    } catch (Exception $e) {
        $logger->error("MongoDB Error: " . $e->getMessage());
        return false;
    }
}

try {
    if ($willAttemptInit) {
        $amount = filter_var($_GET['amount'], FILTER_VALIDATE_FLOAT);
        if (!$amount || $amount <= 0) {
            throw new Exception("Invalid or missing amount.");
        }

        if (empty($_GET['orderId'])) {
            throw new Exception("Order ID is required.");
        }

        $txnId = resetPaymentSession(
            $amount,
            $_GET['currency'] ?? 'LKR',
            $_GET['orderId'],
            $_GET['description'] ?? 'No description',
            $_GET['address'] ?? null,
            $_GET['successUrl'] ?? null,
            $_GET['failedUrl'] ?? null
        );
        $_SESSION['orderId']  = $_GET['orderId'];
        error_log("UUID: " . $_SESSION['uuid']);

        $logger->info("Initialized payment session with txnId: $txnId");

        $sessionId = initiateCheckout($txnId, $logger);

        if (!saveTransactionToDatabase($txnId, $sessionId, $logger)) {
            throw new Exception("Failed to record transaction. Please try again.");
        }

        $_SESSION['sessionId'] = $sessionId;
        $_SESSION['txnId'] = $txnId;

        header("Location: " . BASE_PATH);
        exit;
    }

    if ($hasInitiated) {
        if (isset($_SESSION['payments'][$_SESSION['txnId']]) && 
            ($_SESSION['payments'][$_SESSION['txnId']]['sessionId'] == $_SESSION['sessionId'])) {
            $logger->info('Payment session found for txnId: ' . $_SESSION['txnId']);
    
        } else {
            throw new Exception("Invalid transaction ID. Please try again.");
        }
    }
}
catch (Exception $e) {
    $logger->error("Exception during checkout initiation: " . $e->getMessage());
    $_SESSION['errorMessage'] = "Error: " . $e->getMessage();
    unset($_SESSION['payments'], $_SESSION['sessionId'], $_SESSION['txnId']);

    header("Location: " . BASE_PATH);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($_SESSION['errorMessage']) ? 'Payment Error | Seylan Bank' : 'Secure Payment | Seylan Bank'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <link href="https://unpkg.com/boxicons@2.1.2/css/boxicons.min.css" rel="stylesheet">
    <style>
        .bg-gradient-red-orange {
            background: linear-gradient(135deg, #dc2626 0%, #ea580c 100%);
        }

        .bg-gradient-red-orange-light {
            background: linear-gradient(135deg, #fef2f2 0%, #fff7ed 100%);
        }

        .bg-gradient-red-orange-hover {
            background: linear-gradient(135deg, #b91c1c 0%, #c2410c 100%);
        }

        .text-red-orange {
            color: #ea580c;
        }

        .border-red-orange {
            border-color: #ea580c;
        }

        .shadow-red-orange {
            box-shadow: 0 10px 15px -3px rgba(220, 38, 38, 0.1), 0 4px 6px -2px rgba(220, 38, 38, 0.05);
        }

        .hover-shadow-red-orange:hover {
            box-shadow: 0 20px 25px -5px rgba(220, 38, 38, 0.1), 0 10px 10px -5px rgba(220, 38, 38, 0.04);
        }
    </style>
    <?php if (!isset($_SESSION['errorMessage'])) { ?>
        <?php
        $checkoutJsUrl = APP_LIVE
            ? 'https://seylan.gateway.mastercard.com/static/checkout/checkout.min.js'
            : 'https://test-seylan.mtf.gateway.mastercard.com/static/checkout/checkout.min.js';
        ?>
        <script src="<?php echo $checkoutJsUrl; ?>"></script>

        <script>
            const sessionId = "<?php echo htmlspecialchars($_SESSION['sessionId'] ?? ''); ?>";

            Checkout.configure({
                session: {
                    id: sessionId
                }
            });
            console.log("Loaded sessionId: " + sessionId);
        </script>

    <?php } ?>
</head>

<body class="bg-gradient-red-orange-light min-h-screen flex items-center justify-center p-4">
    <div id="main-container" class="bg-white rounded-2xl shadow-red-orange transition-all duration-300 hover:shadow-red-orange w-full max-w-lg overflow-hidden">
        <div class="bg-gradient-red-orange p-6 text-center">
            <h1 class="text-2xl font-bold text-white"><?php echo isset($_SESSION['errorMessage']) ? 'Payment Error' : 'Secure Payment'; ?></h1>
            <p class="text-white text-sm">Protected by Seylan Bank</p>
        </div>

        <?php if (isset($_SESSION['errorMessage'])): ?>
            <div class="p-6 text-center">
                <h2 class="text-xl font-bold text-red-600">Error</h2>
                <p class="text-red-500 mt-2"><?php echo htmlspecialchars($_SESSION['errorMessage']); ?></p>
            </div>
        <?php else: ?>
            <div id="main_2">
                <div class="px-6 pt-8">
                    <div class="space-y-6 mb-8">
                        <div class="flex space-x-3">
                            <div class="flex items-center space-x-3 bg-red-50 p-4 rounded-lg flex-1">
                                <i class='bx bx-receipt text-lg text-red-600'></i>
                                <div class="text-left">
                                    <p class="text-sm text-gray-500">Order Reference</p>
                                    <p class="font-bold text-red-600 text-sm pl-4"><?php 
                                        $orderId = $_SESSION['payments'][$_SESSION['txnId']]['orderId'];
                                        echo htmlspecialchars($orderId); 
                                        ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 bg-orange-50 p-4 rounded-lg flex-1">
                                <i class='bx bx-credit-card text-lg text-orange-600'></i>
                                <div class="text-left">
                                    <p class="text-sm text-gray-500">Total Amount</p>
                                    <p class="font-bold text-orange-600 text-sm pl-4">
                                        <?php
                                        $amount = $_SESSION['payments'][$_SESSION['txnId']]['amount'];
                                        $currency = $_SESSION['payments'][$_SESSION['txnId']]['currency'];

                                        $formattedAmount = (fmod($amount, 1) == 0)
                                            ? number_format($amount, 0, '.', ',')
                                            : number_format($amount, 2, '.', ',');
                                        echo htmlspecialchars($currency) . ' ' . $formattedAmount;
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4 bg-red-50 p-4 rounded-xl">
                            <i class='bx bx-detail text-2xl text-red-600'></i>
                            <div class="text-left">
                                <p class="text-sm text-gray-500">Description</p>
                                <p class="font-bold text-red-600 text-sm pl-4">
                                    <?php 
                                    $description = $_SESSION['payments'][$_SESSION['txnId']]['description'];
                                    echo htmlspecialchars($description); ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4 bg-orange-50 p-4 rounded-xl">
                            <i class='bx bx-envelope text-2xl text-orange-600'></i>
                            <div class="text-left w-full">
                                <p class="text-sm text-gray-500">Your Email</p>
                                <input type="email" id="email" class="border-2 border-gray-300 p-2 rounded-lg w-full focus:border-red-orange focus:ring-red-orange" placeholder="Enter your email" required>
                                <p id="error-message" class="text-red-500 text-sm mt-1 hidden">Please enter a valid email address.</p>
                            </div>
                        </div>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" id="termsCheckbox" class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                            <span>By proceeding, I agree to the
                                <a href="https://helpagesl.org/terms.html"
                                    target="_blank"
                                    class="underline text-red-600 hover:text-orange-500 transition duration-300">
                                    terms and conditions
                                </a>
                            </span>
                        </label>
                        <span id="terms-error-message" class="text-red-500 text-sm hidden">You must agree to the Terms and Conditions to proceed.</span>
                    </div>

                    <div class="g-recaptcha mb-5" data-sitekey="<?php echo ROBOT_SITE_KEY; ?>"></div>
                    <p id="recaptcha-error-message" class="text-red-500 text-sm hidden mb-5">Please verify you are not a robot.</p>

                    <button id="proceed-btn" onclick="validateAndProceed()"
                        class="w-full bg-gradient-red-orange hover:bg-gradient-red-orange-hover text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-red-200 flex items-center justify-center space-x-2">
                        <i class='bx bx-lock-alt text-xl'></i>
                        <span id="btn-text">Proceed to Secure Payment</span>
                    </button>
                </div>
            </div>

            <!-- Embedded Checkout Container -->
                <div id="embedded-checkout" class="hidden min-h-[600px] h-auto w-full bg-white p-2 sm:p-6 pb-12"></div>
            <div id="payment-error" class="hidden mt-6 text-center text-red-600 font-semibold p-4 bg-red-50 rounded-lg"></div>
            <div id="payment-status" class="hidden mt-6 text-center text-lg font-semibold"></div>
            <div class="flex justify-center">
                <button onclick="window.location.href='https://www.helpagesl.org/'" id="return-to-merchant-btn"
                    class="hidden bg-gradient-red-orange hover:bg-gradient-red-orange-hover text-white font-bold py-4 px-6 mt-2 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg hover:shadow-red-200 flex items-center justify-center space-x-2">
                    <span>Return to Merchant</span>
                </button>
            </div>
        <?php endif; ?>
        <div class="mt-3 mb-6 flex items-center justify-center text-sm text-gray-500">
            <div class="flex items-center mr-4">
                <i class='bx bx-shield-quarter text-green-500'></i>
                <span class="ml-2">256-bit SSL Secured Connection</span>
            </div>
            <div class="flex items-center space-x-3">
                <img src="assets/card_logo.png" alt="another logo" class="h-10">
                <img src="assets/bank_logo.png" alt="bank logo" class="h-10">
            </div>
        </div>
    </div>

    <?php if (!isset($_SESSION['errorMessage'])){ ?>
    <script>
        function validateEmail(email) {
            const re = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return re.test(email.trim());
        }

        async function validateAndProceed() {
            const email = document.getElementById("email").value.trim();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const errorMessage = document.getElementById("error-message");
            const termsCheckbox = document.getElementById("termsCheckbox");
            const termsErrorMessage = document.getElementById("terms-error-message");
            const recaptchaErrorMessage = document.getElementById("recaptcha-error-message");
            const recaptchaResponse = grecaptcha.getResponse();

            termsErrorMessage.classList.add("hidden");
            errorMessage.classList.add("hidden");
            recaptchaErrorMessage.classList.add("hidden");

            if (!termsCheckbox.checked) {
                termsErrorMessage.classList.remove("hidden");
                return;
            }

            if (recaptchaResponse.length === 0) {
                recaptchaErrorMessage.classList.remove("hidden");
                return;
            }

            if (!emailPattern.test(email)) {
                errorMessage.classList.remove("hidden");
                errorMessage.textContent = 'Please enter a valid email address.';
                return;
            }

            // Save email to a cookie so response.php can pick it up
            document.cookie = "userEmail=" + encodeURIComponent(email) + "; path=/; max-age=3600";

            try {
                // Hide form
                document.getElementById("main_2").style.display = "none";

                // Show embedded area
                const embedDiv = document.getElementById("embedded-checkout");
                embedDiv.classList.remove("hidden");

                // Safety check
                if (typeof Checkout === 'undefined') {
                    throw new Error("Checkout library not loaded. Check script URL and console errors.");
                }

                if (typeof Checkout.showEmbeddedPage === 'function') {
                    // Preferred: Embedded mode inside your page
                    Checkout.showEmbeddedPage("#embedded-checkout", {
                        onError: function(error) {
                            console.error("Gateway Error:", error);
                            
                            // Hide the embedded checkout as it might be broken/empty
                            document.getElementById("embedded-checkout").classList.add("hidden");
                            
                            // Get the error message container
                            const errorContainer = document.getElementById("payment-error");
                            
                            if (errorContainer) {
                                errorContainer.classList.remove("hidden");
                                
                                // Surface the detailed explanation nicely to the user
                                let errorMessage = "An error occurred while loading the payment form.";
                                
                                if (error && error.error && error.error.explanation) {
                                    errorMessage = error.error.explanation;
                                } else if (error && error.error && error.error.cause) {
                                    errorMessage = error.error.cause;
                                }
                                
                                errorContainer.textContent = errorMessage;
                            }
                            
                            // Also show the "Return to Merchant" button so they aren't stuck
                            const returnBtn = document.getElementById("return-to-merchant-btn");
                            if (returnBtn) {
                                returnBtn.classList.remove("hidden");
                            }
                        }
                    });
                } else if (typeof Checkout.showPaymentPage === 'function') {
                    // Fallback: Lightbox / full payment page overlay
                    console.warn("Embedded mode not supported → falling back to Payment Page");
                    Checkout.showPaymentPage();
                } else {
                    throw new Error("Neither showEmbeddedPage nor showPaymentPage available. Wrong gateway version?");
                }

            } catch (err) {
                console.error("Payment launch error:", err);
                alert("Unable to launch payment: " + err.message);
                // Optional: Show form again on error
                document.getElementById("main_2").style.display = "block";
                embedDiv.classList.add("hidden");
            }
        }
    </script>
    <?php } ?>
</body>
</html>