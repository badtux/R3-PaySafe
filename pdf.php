<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'vendor/autoload.php';
require 'config/config.sample.php';

use MongoDB\Client;
use TCPDF;

function generateReceipt($orderId)
{
    $database_url = DATABASE_URL;
    $collection = COLLECTION;
    $database = DB;

    try {
        $client = new Client($database_url);
        $collection = $client->selectDatabase($database)->selectCollection($collection);

        $payment = $collection->findOne(['orderId' => $orderId]);
        if (!$payment) {
            die("Payment not found for Order ID: $orderId");
        }

        // --- Custom Page Size (Receipt style: 80mm width x 150mm height) ---
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, [160, 180], true, 'UTF-8', false);

        $pdf->AddPage();

        // --- Logos ---
        $logo1 = __DIR__ . '/assets/bank_logo.png';   // Left corner
      

        if (file_exists($logo1)) {
            $pdf->Image($logo1, 5, 5, 18); 
        }


        // --- Title ---
        $pdf->Ln(15); 
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(200, 0, 0);
        $pdf->Cell(0, 8, 'Online Transfer - Payment Receipt', 0, 1, 'C');
        $pdf->Ln(5);

        // Reset font color for content
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9); 

        // --- Receipt Data ---
        $receiptData = [
            "Order ID" => isset($payment->orderId) ? $payment->orderId : "N/A",
            "Status" => isset($payment->paymentStatus) ? "<strong>" . $payment->paymentStatus . "</strong>" : "N/A",
            "Amount" => (isset($payment->currency) ? $payment->currency : 'LKR') . " " . (isset($payment->amount) ? number_format($payment->amount, 2) : 'N/A'),
            "Transaction ID" => isset($payment->transactionId) ? $payment->transactionId : "N/A",
            "Date & Time" => isset($payment->updatedAt)
                ? $payment->updatedAt->toDateTime()
                    ->setTimezone(new DateTimeZone("Asia/Colombo"))
                    ->format("d/m/Y h:i A")
                : "N/A",
            "Source Account Number " =>isset($payment->cardNumber) ? $payment->cardNumber :'N/A',
            "Destination Bank" =>"SEYLAN BANK"
        ];

        // --- Table Layout ---
        $html = '<style>
                    td {padding:2px 4px; font-size:12pt; }
                    .label { font-weight: bold; width:40%; }
                    .value { width:60%; }
                 </style>';
        $html .= '<table border="0" cellpadding="3">';
        foreach ($receiptData as $key => $value) {
            $html .= '<tr>
                        <td class="label">' . $key . ' :</td>
                        <td class="value">' . $value . '</td>
                      </tr>';
        }
        $html .= '</table>';
        
        // --- Divider Line ---
       $html .= '<div style="margin-top: 20px;"></div><hr style="border: 1px solid #000; margin-bottom: 5px;">';


        // --- Footer ---
        $html .= '<p style="text-align:center; font-size:9pt; color:#555;">
                    Thank you for using <strong>Seylan PaySafe</strong>!
                  </p>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('payment_receipt_' . $orderId . '.pdf', 'D');
    } catch (Exception $e) {
        die('Error: ' . $e->getMessage());
    }
}

if (isset($_SESSION['orderId'])) {
    $orderId = trim($_SESSION['orderId']);
    generateReceipt($orderId);
} else {
?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Download Payment Receipt</title>
    </head>
    <body>
        <h2>Download Payment Receipt</h2>
        <p>No Order ID found in session. Please set an Order ID.</p>
        <form method="post" action="set_session.php">
            <label for="order_id">Enter Order ID (for testing):</label>
            <input type="text" name="order_id" id="order_id" required>
            <button type="submit">Set Order ID and Generate Receipt</button>
        </form>
    </body>
    </html>
<?php
}
?>