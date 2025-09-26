//const BASE_URL = "http://localhost:3008/api"; // Use local URL for testing
const BASE_URL = "https://malkey.go.digitable.io:3008/api";


const today = new Date().toISOString().split('T')[0];

async function authFetch(url, options = {}) {
    console.log('Making request to:', url);
    const response = await fetch(url, { ...options, credentials: 'include' });
    console.log('Response status:', response.status);
    if (response.status === 401 || response.status === 403) {
        console.log('Unauthorized or Forbidden, redirecting to /login.html');
        if (window.location.pathname !== 'login.html') {
            window.location.href = 'login.html';
        }
        throw new Error('Not authenticated');
    }
    return response;
}

async function checkAuth() {
    try {
        console.log('Checking authentication...');
        const response = await fetch(`${BASE_URL}/auth/check`, { credentials: 'include' });
        console.log('Auth check status:', response.status);
        if (!response.ok) {
            console.log('Auth check failed, redirecting to /login.html');
            if (window.location.pathname !== 'login.html') {
                window.location.href = 'login.html';
            }
            return false;
        }
        const data = await response.json();
        console.log('Auth check response:', data);
        return data.authenticated === true;
    } catch (error) {
        console.error('Error during auth check:', error);
        if (window.location.pathname !== '/login.html') {
            window.location.href = '/login.html';
        }
        return false;
    }
}

let currentFilters = {
    from: '',
    to: '',
    status: 'SUCCESS',
    search: '',
    page: 1,
    limit: 10
};

document.addEventListener('DOMContentLoaded', async () => {
    // Check authentication before any UI rendering or API calls
    const isAuthenticated = await checkAuth();
    if (!isAuthenticated) {
        console.log('User not authenticated, redirection handled in checkAuth');
        return; // Stop further execution
    }

    // Initialize dashboard only if authenticated
    document.getElementById('transactionTable').innerHTML = `
        <tr><td colspan="11" class="px-5 py-4 text-center">Loading...</td></tr>
    `;
    checkHealth();
    loadData();
    setupEventListeners();
});

function setupEventListeners() {
    document.getElementById('applyFilters').addEventListener('click', applyFilters);
    document.getElementById('resetFilters').addEventListener('click', resetFilters);
    document.getElementById('prevPage').addEventListener('click', () => changePage(currentFilters.page - 1));
    document.getElementById('nextPage').addEventListener('click', () => changePage(currentFilters.page + 1));
    document.getElementById('itemsPerPage').addEventListener('change', (e) => {
        currentFilters.limit = parseInt(e.target.value);
        currentFilters.page = 1;
        loadData();
    });
    document.getElementById('exportData').addEventListener('click', exportToCSV);
}

async function checkHealth() {
    try {
        const response = await authFetch(`${BASE_URL}/health`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const health = await response.json();
        console.log('Health check:', health);
        if (!health.collectionExists || health.documentCount === 0) {
            document.getElementById('transactionTable').innerHTML = `
                <tr><td colspan="11" class="px-5 py-4 text-center text-red-500">
                    ${health.collectionExists ? 'No successful transactions found in database' : 'Collection "payments" does not exist'}
                </td></tr>
            `;
        }
    } catch (error) {
        console.error('Health check failed:', error);
        document.getElementById('transactionTable').innerHTML = `
            <tr><td colspan="11" class="px-5 py-4 text-center text-red-500">Failed to connect to server. Please check if the backend is running.</td></tr>
        `;
    }
}

async function loadData() {
    try {
        const params = new URLSearchParams();
        for (const [key, value] of Object.entries(currentFilters)) {
            if (value !== undefined && value !== '') {
                params.append(key, value);
            }
        }
        params.set('status', 'SUCCESS');
        const response = await authFetch(`${BASE_URL}/payments?${params}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const { transactions, total, stats } = await response.json();
        console.log('Fetched data:', { transactions, total, stats });
        updateStats(stats);
        updateTable(transactions, total);
    } catch (error) {
        console.error('Error fetching data:', error);
        document.getElementById('transactionTable').innerHTML = `
            <tr><td colspan="11" class="px-5 py-4 text-center text-red-500">Failed to load transactions. Please try again later.</td></tr>
        `;
    }
}

function applyFilters() {
    const fromDate = document.getElementById('fromDate').value || today;
    const toDate = document.getElementById('toDate').value || today;
    const searchQuery = document.getElementById('searchQuery').value.toLowerCase();

    console.log('Applying filters:', { fromDate, toDate, status: 'SUCCESS', searchQuery });

    currentFilters.from = fromDate;
    currentFilters.to = toDate;
    currentFilters.status = 'SUCCESS';
    currentFilters.search = searchQuery || undefined;
    currentFilters.page = 1;
    loadData();
}

function resetFilters() {
    document.getElementById('fromDate').value = '';
    document.getElementById('toDate').value = '';
    document.getElementById('transactionType').value = 'SUCCESS';
    document.getElementById('searchQuery').value = '';
    document.getElementById('itemsPerPage').value = '10';
    currentFilters = {
        from: '',
        to: '',
        status: 'SUCCESS',
        search: '',
        page: 1,
        limit: 10
    };
    console.log('Filters reset');
    loadData();
}

function updateStats(stats) {
    document.getElementById('statsCards').innerHTML = `
        <div class="bg-gradient-to-r from-primary to-blue-800 rounded-lg shadow text-white p-5">
            <div class="text-3xl font-bold">${stats.totalTransactions}</div>
            <div class="text-sm opacity-90 mt-1">Total Transactions</div>
        </div>
        <div class="bg-gradient-to-r from-green-500 to-green-700 rounded-lg shadow text-white p-5">
            <div class="text-3xl font-bold">${stats.successfulTransactions}</div>
            <div class="text-sm opacity-90 mt-1">Successful Transactions</div>
        </div>
        <div class="bg-gradient-to-r from-green-500 to-green-700 rounded-lg shadow text-white p-5">
            <div class="text-3xl font-bold">LKR ${stats.totalAmountLKR}</div>
            <div class="text-sm opacity-90 mt-1">Total Amount (LKR)</div>
        </div>
        <div class="bg-gradient-to-r from-cyan-500 to-cyan-700 rounded-lg shadow text-white p-5">
            <div class="text-3xl font-bold">USD ${stats.totalAmountUSD}</div>
            <div class="text-sm opacity-90 mt-1">Total Amount (USD)</div>
        </div>
    `;
}

function updateTable(transactions, total) {
    document.getElementById('transactionTable').innerHTML = transactions.length > 0 ? transactions.map(t => {
        const transactionId = t.transactionId || 'N/A';
        const displayId = transactionId === 'N/A' ? 'N/A' :
            (transactionId.length > 8 ?
                transactionId.slice(0, 4).toUpperCase() + '...' + transactionId.slice(-4).toUpperCase() :
                transactionId.toUpperCase());

        return `
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-4">${new Date(t.createdAt).toISOString().split('T')[0]}</td>
                <td class="px-5 py-4">${t.merchantId || 'N/A'}</td>
                <td class="px-5 py-4">${t.orderId || 'N/A'}</td>
                <td class="px-5 py-4">${parseFloat(t.amount || 0).toFixed(2)}</td>
                <td class="px-5 py-4">${t.currency || 'N/A'}</td>
                <td class="px-5 py-4">${t.email || 'N/A'}</td>
                <td class="px-5 py-4">${t.description || 'N/A'}</td>
                <td class="px-5 py-4">${t.cardBrand || 'N/A'}</td>
                <td class="px-5 py-4">${t.nameOnCard || 'N/A'}</td>
                <td class="px-5 py-4 relative">
                    <span class="cursor-pointer group" title="${transactionId}">${displayId}</span>
                </td>
                <td class="px-5 py-4"><span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">SUCCESS</span></td>
            </tr>
        `;
    }).join('') : `
        <tr><td colspan="11" class="px-5 py-4 text-center">No successful transactions found</td></tr>
    `;

    const start = (currentFilters.page - 1) * currentFilters.limit + 1;
    const end = Math.min(start + currentFilters.limit - 1, total);
    document.getElementById('tableInfo').textContent = `Showing ${start} to ${end} of ${total} entries`;

    const totalPages = Math.ceil(total / currentFilters.limit);
    document.getElementById('prevPage').disabled = currentFilters.page === 1;
    document.getElementById('nextPage').disabled = currentFilters.page === totalPages;

    document.getElementById('pageButtons').innerHTML = Array.from({
        length: totalPages
    }, (_, i) => `
        <button class="flex items-center justify-center px-3 py-1.5 text-sm ${currentFilters.page === i + 1 ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700'} rounded-md border border-${currentFilters.page === i + 1 ? 'primary' : 'gray-300'} hover:bg-gray-200" onclick="changePage(${i + 1})">${i + 1}</button>
    `).join('');
}

function changePage(page) {
    if (page < 1) return;
    currentFilters.page = page;
    loadData();
}

async function exportToCSV() {
    try {
        const params = new URLSearchParams();
        ['from', 'to', 'search'].forEach(key => {
            if (currentFilters[key] !== undefined && currentFilters[key] !== '') {
                params.append(key, currentFilters[key]);
            }
        });
        params.append('status', 'SUCCESS');
        const response = await authFetch(`${BASE_URL}/export?${params}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const transactions = await response.json();

        const headers = ['Created At,Merchant ID,Order ID,Amount,Currency,Email,Description,Card Brand,Name on Card,Payment Status'];
        const rows = transactions.map(t =>
            `"${new Date(t.createdAt).toISOString().split('T')[0]}","${t.merchantId || 'N/A'}","${t.orderId || 'N/A'}",${parseFloat(t.amount || 0).toFixed(2)},"${t.currency || 'N/A'}","${t.email || 'N/A'}","${t.description || 'N/A'}","${t.cardBrand || 'N/A'}","${t.nameOnCard || 'N/A'}","SUCCESS"`
        );
        const csv = [...headers, ...rows].join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'successful_transactions.csv';
        a.click();
        URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error exporting data:', error);
        alert('Failed to export data. Please try again.');
    }
}