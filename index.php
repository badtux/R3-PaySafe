
<?php
// Simple username and password
$USERNAME = "malkey";
$PASSWORD = "mal@1234";

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] !== $USERNAME || 
    $_SERVER['PHP_AUTH_PW'] !== $PASSWORD) {
    
    // Ask browser for login

    exit;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Protected Page</title>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($USERNAME); ?>!</h1>
    <p>This page is protected by a simple login.</p>
</body>
</html>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Transactions Dashboard</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico"> <!-- Favicon added -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="pay_script.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4e73df',
                        secondary: '#f8f9fc',
                        accent: '#36b9cc',
                        text: '#5a5c69',
                        border: '#e3e6f0',
                    }
                }
            }
        }
    </script>
    <style>
        .card-number {
            font-family: monospace;
            letter-spacing: 1px;
        }

        .table-container {
            overflow-x: auto;
        }

        .table-container::-webkit-scrollbar {
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #c2c2c2;
            border-radius: 10px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Tooltip styling [title]:hover:after {
            content: attr(title);
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 10;
        
        } */
    </style>
</head>


<body class="bg-gray-50 text-gray-700 font-sans antialiased">
    <header class="bg-gradient-to-r from-primary to-blue-800 text-white shadow-md">
        <div class="container mx-auto px-4 py-5">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <div class="mb-4 md:mb-0">
                    <h1 class="text-2xl md:text-3xl font-bold"><i class="fas fa-money-bill-wave mr-2"></i>Payment Transactions</h1>
                    <p class="mt-1 text-blue-100">Manage and review your payment transactions</p>
                </div>
                <div class="flex items-center justify-between md:justify-end">
                    <div class="mr-4 hidden md:flex items-center z-106767670">
                        <img src="https://d8asu6slkrh4m.cloudfront.net/2013/04/malkey-logo.png" alt="Logo" class="w-40 h-19 mx-auto ml-5 filter brightness-0 invert">
                    </div>
                    <div class="relative">
                        <button class="flex items-center text-white bg-blue-700 hover:bg-blue-600 px-3 py-2 rounded-lg text-sm">
                            <i class="fas fa-user mr-2"></i> Mahesh Mallawaratchi
                            <i class="fas fa-chevron-down ml-2 text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6" id="statsCards">
            <!-- Stats will be dynamically updated -->
        </div>

        <div class="bg-white rounded-lg shadow p-5 mb-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-700"><i class="fas fa-filter mr-2 text-primary"></i>Filter Transactions</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" id="fromDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" id="toDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                    <select id="transactionType" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">All Statuses</option>
                        <option value="SUCCESS">Success</option>
                        <option value="PENDING">Pending</option>
                        <option value="FAILED">Failed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Items Per Page</label>
                    <select id="itemsPerPage" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="searchQuery" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="Search by order ID, email, or description...">
                </div>
                <div class="flex items-end space-x-2">
                    <button id="applyFilters" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-md flex items-center">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <button id="resetFilters" class="border border-gray-300 hover:bg-gray-100 text-gray-700 px-4 py-2 rounded-md flex items-center">
                        <i class="fas fa-redo mr-2"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="flex justify-between items-center border-b border-gray-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-700"><i class="fas fa-list mr-2 text-primary"></i>Transaction History</h2>
                <button id="exportData" class="border border-primary text-primary hover:bg-blue-50 px-3 py-1 rounded-md text-sm flex items-center">
                    <i class="fas fa-download mr-2"></i> Export
                </button>
            </div>
            <div class="table-container">
                <table class="w-full">
                    <thead class="bg-gray-50">

                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Merchant ID</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Card Brand</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name on Card</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Id</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                        </tr>
                    </thead>
                    <tbody id="transactionTable" class="divide-y divide-gray-200">
                        <tr>
                            <td colspan="11" class="px-5 py-4 text-center">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-center border-t border-gray-200 px-5 py-4">
                <div class="text-sm text-gray-700 mb-4 md:mb-0" id="tableInfo"></div>
                <div class="flex space-x-2">
                    <button id="prevPage" class="flex items-center justify-center px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-md border border-gray-300 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed" disabled>Previous</button>
                    <div id="pageButtons" class="flex space-x-2"></div>
                    <button id="nextPage" class="flex items-center justify-center px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-md border border-gray-300 hover:bg-gray-200">Next</button>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-white mt-8 py-4 border-t border-gray-200">
        <div class="container mx-auto px-4 text-center text-sm text-gray-500">
            <p>© 2025 Payment Dashboard • Mahesh Mallawaratchi</p>
        </div>
    </footer>
</body>

</html>