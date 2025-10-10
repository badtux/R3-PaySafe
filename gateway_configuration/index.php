<?php

require 'seylan/vendor/autoload.php'; 

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/error.log'); 

error_log("Request: " . $_SERVER['REQUEST_URI'] . ", Host: " . ($_SERVER['HTTP_HOST'] ?? 'unknown'));

$hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
$tenant = explode('.', $hostname)[0] ?? '';
error_log("Tenant extracted: $tenant");

if (!$tenant || $tenant === 'localhost' || $tenant === 'go') {
    http_response_code(400);
    echo "<h1>Invalid tenant!</h1>";
    error_log("Invalid tenant: $tenant");
    exit;
}

$queryString = $_SERVER['QUERY_STRING'] ?? '';
error_log("Query string: $queryString");

$isLocal = true;
$uri = $isLocal
    ? "mongodb+srv://piumal0713:Adyp%400713@cluster0.8bv15.mongodb.net/?retryWrites=true&w=majority&authSource=admin"
    : "mongodb://127.0.0.1:27017";

function getButtonColor($tenant) {
    $tenantLower = strtolower($tenant);
    if (strpos($tenantLower, 'malkey') !== false) {
        return 'blue'; 
    } elseif (strpos($tenantLower, 'helpage') !== false) {
        return 'orange'; 
    } else {
        $colors = ['green', 'purple', 'indigo', 'pink', 'teal', 'cyan', 'lime', 'amber'];
        return $colors[array_rand($colors)];
    }
}

function getColorClasses($color) {
    $colorMap = [
        'blue' => 'bg-blue-600 hover:bg-blue-700 border-blue-600',
        'orange' => 'bg-orange-500 hover:bg-orange-600 border-orange-500',
        'green' => 'bg-green-600 hover:bg-green-700 border-green-600',
        'purple' => 'bg-purple-600 hover:bg-purple-700 border-purple-600',
        'indigo' => 'bg-indigo-600 hover:bg-indigo-700 border-indigo-600',
        'pink' => 'bg-pink-600 hover:bg-pink-700 border-pink-600',
        'teal' => 'bg-teal-600 hover:bg-teal-700 border-teal-600',
        'cyan' => 'bg-cyan-500 hover:bg-cyan-600 border-cyan-500',
        'lime' => 'bg-lime-500 hover:bg-lime-600 border-lime-500',
        'amber' => 'bg-amber-500 hover:bg-amber-600 border-amber-500'
    ];
    
    return $colorMap[$color] ?? 'bg-blue-600 hover:bg-blue-700 border-blue-600'; 
}

try {
    if (!class_exists('MongoDB\Client')) {
        throw new Exception("MongoDB PHP driver not installed or loaded");
    }
    error_log("Connecting to MongoDB: $uri");
    $client = new MongoDB\Client($uri);
    $db = $client->selectDatabase('DT-Plutos');
    $collection = $db->selectCollection('active_gateways');
    error_log("Connected to database: DT-Plutos, collection: active_gateways");

    $tenantData = $collection->findOne(['tenant' => $tenant]);
    if (!$tenantData) {
        http_response_code(404);
        echo "<h1>Tenant not found!</h1>";
        error_log("Tenant not found: $tenant");
        exit;
    }
    error_log("Tenant data found: " . json_encode($tenantData));

    $activeGateways = isset($tenantData['activeGateways']) 
        ? (array) $tenantData['activeGateways'] 
        : [];

    $activeCount = count($activeGateways);
    error_log("Active gateways count: $activeCount");
    
    // Extract contact information from tenant data
    $contactInfo = isset($tenantData['contact']) ? (array)$tenantData['contact'] : [];
    $merchantName = isset($tenantData['merchantName']) ? $tenantData['merchantName'] : $tenant;
    $phoneNumber = isset($contactInfo['Phone number']) ? $contactInfo['Phone number'] : 'Not provided';
    $email = isset($contactInfo['email']) ? $contactInfo['email'] : 'Not provided';
    
    error_log("Contact info - Phone: $phoneNumber, Email: $email");

    $buttonColor = getButtonColor($tenant);
    $colorClasses = getColorClasses($buttonColor);
    error_log("Button color for tenant '$tenant': $buttonColor");

    if ($activeCount === 0) {
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>No Active Gateways</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="flex flex-col items-center justify-center min-h-screen bg-gradient-to-br from-white to-zinc-200 font-sans">
            <div class="bg-white shadow-lg rounded-2xl p-8 max-w-md w-full text-center border border-red-200">
                <div class="mb-6">
                    <div class="text-6xl mb-4">⚠️</div>
                    <h1 class="text-2xl font-semibold text-red-900 mb-2">Gateway Unavailable</h1>
                    <p class="text-gray-600 mb-2">We're experiencing technical difficulties with our payment systems.</p>
                    <p class="text-sm text-gray-500">Merchant: <?= htmlspecialchars($merchantName) ?></p>
                </div>
                
                <div class="my-8 p-6 bg-red-50 border border-red-200 rounded-xl">
                    <h1 style='color:red; font-size: 1.5rem; font-weight: bold; margin: 0;'>❌ No active gateways available!</h1>
                </div>

                <div class="mt-8">
                    <p class="text-gray-700 mb-4">Are you a merchant needing assistance?</p>
                    <button 
                        onclick="showContactModal()"
                        class="w-full <?= $colorClasses ?> text-white font-semibold py-3 px-6 rounded-xl transition-all duration-200 transform hover:scale-105"
                    >
                        Contact Merchant Support
                    </button>
                </div>
            </div>

            <!-- Contact Modal -->
            <div id="contactModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
                <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">Contact Support</h2>
                        <button onclick="closeContactModal()" class="text-gray-500 hover:text-gray-700 text-2xl font-bold">
                            &times;
                        </button>
                    </div>
                    
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4"><?= htmlspecialchars($merchantName) ?> Support</h3>
                        
                        <div class="space-y-4">
                            <!-- Phone Number - Clickable -->
                            <div class="p-3 bg-blue-50 rounded-lg cursor-pointer hover:bg-blue-100 transition-colors duration-200" 
                                 onclick="callPhoneNumber('<?= htmlspecialchars($phoneNumber) ?>')">
                                <div class="flex items-center gap-3">
                                    <div class="bg-blue-100 p-2 rounded-full">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-600">Phone Number</p>
                                        <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($phoneNumber) ?></p>
                                    </div>
                                    <div class="text-blue-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Email - Clickable -->
                            <div class="p-3 bg-green-50 rounded-lg cursor-pointer hover:bg-green-100 transition-colors duration-200" 
                                 onclick="sendEmail('<?= htmlspecialchars($email) ?>')">
                                <div class="flex items-center gap-3">
                                    <div class="bg-green-100 p-2 rounded-full">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-600">Email Address</p>
                                        <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($email) ?></p>
                                    </div>
                                    <div class="text-green-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button 
                        onclick="closeContactModal()" 
                        class="w-full bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-3 px-6 rounded-xl transition-all duration-200"
                    >
                        Cancel
                    </button>
                </div>
            </div>

            <script>
                function showContactModal() {
                    document.getElementById('contactModal').classList.remove('hidden');
                    document.getElementById('contactModal').classList.add('flex');
                }
                
                function closeContactModal() {
                    document.getElementById('contactModal').classList.remove('flex');
                    document.getElementById('contactModal').classList.add('hidden');
                }
                
                function callPhoneNumber(phoneNumber) {
                    if (phoneNumber !== 'Not provided') {
                        const cleanPhone = phoneNumber.replace(/[^\d+]/g, '');
                        if (cleanPhone) {
                            window.location.href = 'tel:' + cleanPhone;
                            closeContactModal();
                        } else {
                            alert('Invalid phone number format');
                        }
                    } else {
                        alert('Phone number not available');
                    }
                }
                
                function sendEmail(email) {
                    if (email !== 'Not provided' && email.includes('@')) {
                        window.location.href = 'mailto:' + email;
                        closeContactModal(); 
                    } else {
                        alert('Valid email address not available');
                    }
                }
                document.addEventListener('click', function(event) {
                    const modal = document.getElementById('contactModal');
                    if (event.target === modal) {
                        closeContactModal();
                    }
                });
                
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        closeContactModal();
                    }
                });
            </script>
        </body>
        </html>
        <?php
        error_log("No active gateways for tenant: $tenant");
        exit;
    }

    if ($activeCount === 1) {
        $gateway = current($activeGateways);
        if (!isset($gateway['path']) || empty($gateway['path'])) {
            throw new Exception("Invalid gateway path for tenant: $tenant");
        }
        $redirectUrl = $gateway['path'] . '/?' . htmlspecialchars($queryString);
        error_log("Redirecting to: $redirectUrl");
        header("Location: $redirectUrl");
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    $errorMessage = "Database error: " . $e->getMessage();
    echo "<h1>$errorMessage</h1>";
    error_log("Error: $errorMessage");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Payment Gateway</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex flex-col items-center justify-center min-h-screen bg-gradient-to-br from-white to-zinc-200 font-sans">
    <div class="bg-white shadow-lg rounded-2xl p-8 max-w-md w-full text-center border border-blue-200">
        <h1 class="text-2xl font-semibold text-blue-900 mb-6">Select Your Payment Gateway</h1>

        <?php foreach ($activeGateways as $key => $gateway): ?>
            <button
                onclick="window.location.href='<?= htmlspecialchars($gateway['path'] . '/?' . $queryString) ?>'"
                class="w-full flex items-center justify-center gap-3 border-2 border-blue-800 text-blue-800 rounded-xl py-3 px-5 mb-4 hover:bg-blue-800 hover:text-white transition-all duration-200"
            >
                <span class="text-lg font-medium">Pay with <?= htmlspecialchars($gateway['name']) ?> (<?= htmlspecialchars(ucwords(str_replace('/', ' / ', $key))) ?>)</span>
            </button>
        <?php endforeach; ?>
    </div>
</body>
</html>