 <?php
// create_transaction.php
session_start();

// Generate transaction details
$_SESSION['txn'] = [
    'price' => 15000.00, // Set your amount here
    'currency' => 'LKR', // Or get from user selection
    'reference' => 'TX-' . uniqid() . '-' . bin2hex(random_bytes(2))
];

header('Location: paymentpage.php');
exit;
?>