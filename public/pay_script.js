//const BASE_URL = "http://localhost:3008/api";
 const BASE_URL = "https://malkey.go.digitable.io:3008/api";
const today = new Date().toISOString().split('T')[0];

const VALID_TENANTS = [
    {
        tenant: "malkey",
        password: "password123",
        displayName: "Malkey Rent A Car"
    },
    {
        tenant: "helpage",
        password: "password456",
        displayName: "Helpage "
    }
];

function login(inputTenant, password) {
    const tenant = VALID_TENANTS.find(t => t.tenant === inputTenant && t.password === password);
    if (tenant) {
        localStorage.setItem('isAuthenticated', 'true');
        localStorage.setItem('tenant', tenant.tenant);
        localStorage.setItem('displayName', tenant.displayName);
        return true;
    }
    return false;
}


function checkAuth() {
    return localStorage.getItem('isAuthenticated') === 'true';
}

function logout() {
    localStorage.removeItem('isAuthenticated');
    localStorage.removeItem('tenant');
    localStorage.removeItem('displayName');
    $('#login-section').removeClass('hidden');
    $('#dashboard-content').addClass('hidden');
    $('#userName').text('Guest');
}

let currentFilters = {
    from: '',
    to: '',
    status: 'SUCCESS',
    search: '',
    page: 1,
    limit: 10
};

$(document).ready(() => {
    if (checkAuth()) {
        $('#login-section').addClass('hidden');
        $('#dashboard-content').removeClass('hidden');
        $('#userName').text(localStorage.getItem('displayName') || 'User');
        $('#transactionTable').html(`
            <tr><td colspan="11" class="px-5 py-4 text-center">Loading...</td></tr>
        `);
        checkHealth();
        loadData();
        setupEventListeners();
    } else {
        $('#login-section').removeClass('hidden');
        $('#dashboard-content').addClass('hidden');
        setupLoginEventListener();
    }

    $('#userProfile').on('click', () => {
        if (checkAuth()) {
            logout();
        }
    });
});



function setupLoginEventListener() {
    $('#loginButton').on('click', () => {
        const tenant = $('#username').val(); 
        const password = $('#password').val();
        if (tenant && password) {
            if (login(tenant, password)) {
                $('#login-section').addClass('hidden');
                $('#dashboard-content').removeClass('hidden');
                $('#userName').text(localStorage.getItem('displayName') || 'User');
                $('#transactionTable').html(`
                    <tr><td colspan="11" class="px-5 py-4 text-center">Loading...</td></tr>
                `);
                checkHealth();
                loadData();
                setupEventListeners();
            } else {
                $('#loginError').removeClass('hidden');
            }
        } else {
            $('#loginError').removeClass('hidden');
        }
    });
}



function setupEventListeners() {
    $('#applyFilters').on('click', applyFilters);
    $('#resetFilters').on('click', resetFilters);
    $('#prevPage').on('click', () => changePage(currentFilters.page - 1));
    $('#nextPage').on('click', () => changePage(currentFilters.page + 1));
    $('#itemsPerPage').on('change', (e) => {
        currentFilters.limit = parseInt($(e.target).val());
        currentFilters.page = 1;
        loadData();
    });
    $('#exportData').on('click', exportToCSV);
}



async function checkHealth() {
    try {
        const tenant = localStorage.getItem('tenant');
        const url = `${BASE_URL}/${tenant}/transactions`;
        const response = await $.ajax({
            url: url,
            method: 'GET'
        });
        console.log('Health check:', response);
        if (!response.collectionExists || response.documentCount === 0) {
            $('#transactionTable').html(`
                <tr><td colspan="11" class="px-5 py-4 text-center text-red-500">
                    ${response.collectionExists ? 'No successful transactions found in database' : 'Collection "payments" does not exist'}
                </td></tr>
            `);
        }
    } catch (error) {
        console.error('Health check failed:', error);
        $('#transactionTable').html(`
            <tr><td colspan="11" class="px-5 py-4 text-center text-red-500">Failed to connect to server. Please check if the backend is running.</td></tr>
        `);
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
        const tenant = localStorage.getItem('tenant');
        const response = await $.ajax({
            url: `${BASE_URL}/${tenant}/payments?${params}`,
            method: 'GET'
        });
        console.log('Fetched data:', response);
        updateStats(response.stats);
        updateTable(response.transactions, response.total);
    } catch (error) {
        console.error('Error fetching data:', error);
        $('#transactionTable').html(`
            <tr><td colspan="11" class="px-5 py-4 text-center text-red-500">Failed to load transactions. Please try again later.</td></tr>
        `);
    }
}


function applyFilters() {
    const fromDate = $('#fromDate').val() || today;
    const toDate = $('#toDate').val() || today;
    const searchQuery = $('#searchQuery').val().toLowerCase();

    console.log('Applying filters:', { fromDate, toDate, status: 'SUCCESS', searchQuery });

    currentFilters.from = fromDate;
    currentFilters.to = toDate;
    currentFilters.status = 'SUCCESS';
    currentFilters.search = searchQuery || undefined;
    currentFilters.page = 1;
    loadData();
}



function resetFilters() {
    $('#fromDate').val('');
    $('#toDate').val('');
    $('#transactionType').val('SUCCESS');
    $('#searchQuery').val('');
    $('#itemsPerPage').val('10');
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
    $('#statsCards').html(`
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
    `);
}

function updateTable(transactions, total) {
    $('#transactionTable').html(transactions.length > 0 ? transactions.map(t => {
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
    `);

    const start = (currentFilters.page - 1) * currentFilters.limit + 1;
    const end = Math.min(start + currentFilters.limit - 1, total);
    $('#tableInfo').text(`Showing ${start} to ${end} of ${total} entries`);

    const totalPages = Math.ceil(total / currentFilters.limit);
    $('#prevPage').prop('disabled', currentFilters.page === 1);
    $('#nextPage').prop('disabled', currentFilters.page === totalPages);

    $('#pageButtons').html(Array.from({
        length: totalPages
    }, (_, i) => `
        <button class="flex items-center justify-center px-3 py-1.5 text-sm ${currentFilters.page === i + 1 ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700'} rounded-md border border-${currentFilters.page === i + 1 ? 'primary' : 'gray-300'} hover:bg-gray-200" onclick="changePage(${i + 1})">${i + 1}</button>
    `).join(''));
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
            if (currentFilters[key] !== undefined && value !== '') {
                params.append(key, currentFilters[key]);
            }
        });
        params.append('status', 'SUCCESS');
        const tenant = localStorage.getItem('tenant');
        const response = await $.ajax({
            url: `${BASE_URL}/${tenant}/payments/export?${params}`,
            method: 'GET'
        });

        const headers = ['Created At,Merchant ID,Order ID,Amount,Currency,Email,Description,Card Brand,Name on Card,Payment Status'];
        const rows = response.map(t =>
            `"${new Date(t.createdAt).toISOString().split('T')[0]}","${t.merchantId || 'N/A'}","${t.orderId || 'N/A'}",${parseFloat(t.amount || 0).toFixed(2)},"${t.currency || 'N/A'}","${t.email || 'N/A'}","${t.description || 'N/A'}","${t.cardBrand || 'N/A'}","${t.nameOnCard || 'N/A'}","SUCCESS"`
        );
        const csv = [...headers, ...rows].join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const $a = $('<a>', {
            href: url,
            download: 'successful_transactions.csv'
        }).appendTo('body');
        $a[0].click();
        URL.revokeObjectURL(url);
        $a.remove();
    } catch (error) {
        console.error('Error exporting data:', error);
        alert('Failed to export data. Please try again.');
    }
}