let BASE_URL;
// Store tenant globally for conditional rendering
let CURRENT_TENANT = null;
const today = new Date().toISOString().split("T")[0]; // e.g., "2025-10-24"
const UNIVERSAL_PASSWORD = "admin@123";
const IS_LOCAL = window.location.hostname.includes("127.0.0.1");

$(document).ready(() => {
  const hostname = window.location.hostname;
  const tenantFromUrl = window.location.tenent;
  const tenant = localStorage.getItem("tenant") || tenantFromUrl;
  CURRENT_TENANT = tenant;
  const savedTheme = localStorage.getItem("theme") || "light";
  const savedItemsPerPage = localStorage.getItem("itemsPerPage") || "10";

  console.log("Hostname:", hostname, "IS_LOCAL:", IS_LOCAL, "Tenant:", tenant);

  applyTheme(savedTheme);
  currentFilters.limit = parseInt(savedItemsPerPage);

  $("#themeToggle i")
    .removeClass("fa-sun fa-moon")
    .addClass(savedTheme === "dark" ? "fa-moon" : "fa-sun");
  $("#itemsPerPage").val(savedItemsPerPage);

  if (checkAuth()) {
    const tenant = localStorage.getItem("tenant");
    BASE_URL = IS_LOCAL
      ? `http://${tenant}.localhost:3009/api`
      : `https://${tenant}.go.digitable.io:3009/api`;
    console.log("BASE_URL set to:", BASE_URL);
    $("#dashboard-icons").removeClass("hidden");
    $("#userName").text(tenant || "User");
    $("#transactionTable").html(`
      <tr><td colspan="11" class="px-5 py-4 text-center">Loading...</td></tr>
    `);
    checkHealth();
    loadData();
    setupEventListeners();
    fetchTenantGateways(tenant).then(displayActiveGateways);
  } else {
    window.location.href = "login.html";
  }

  $("#userProfile").on("click", () => {
    if (checkAuth()) {
      logout();
    }
  });

  $("#themeToggle").on("click", () => {
    const currentTheme = localStorage.getItem("theme") || "light";
    const newTheme = currentTheme === "light" ? "dark" : "light";
    localStorage.setItem("theme", newTheme);
    applyTheme(newTheme);
    $("#themeToggle i")
      .removeClass("fa-sun fa-moon")
      .addClass(newTheme === "dark" ? "fa-moon" : "fa-sun");
    displayActiveGateways({
      activeGateways: $("#activeGatewaysDisplay").data("activeGateways") || {},
    });
  });

  $("#activateGatewayBtn").on("click", async () => {
    const tenantData = await fetchTenantGateways(tenant);
    showGatewayModal(tenantData, tenant);
  });

  $("#toggleFilter").click(function () {
    $("#filterSection").toggleClass("show");
  });
});

function applyTheme(theme) {
  if (theme === "dark") {
    $("#body").addClass("bg-gray-800 text-white");
    
    // Main table styles
    $(".bg-white").addClass("bg-gray-900").removeClass("bg-white");
    $(".text-gray-700").addClass("text-gray-200").removeClass("text-gray-700");
    $(".text-gray-600").addClass("text-gray-300").removeClass("text-gray-600");
    $(".text-gray-500").addClass("text-gray-400").removeClass("text-gray-500");
    $(".text-gray-400").addClass("text-gray-500").removeClass("text-gray-400");
    $(".bg-gray-50").addClass("bg-gray-800").removeClass("bg-gray-50");
    $(".border-gray-100").addClass("border-gray-800").removeClass("border-gray-100");
    $(".border-gray-200").addClass("border-gray-700").removeClass("border-gray-200");
    $(".bg-gray-100").addClass("bg-gray-700").removeClass("bg-gray-100");
    $(".hover\\:bg-gray-100").addClass("hover:bg-gray-700").removeClass("hover:bg-gray-100");
    $(".hover\\:bg-gray-50").addClass("hover:bg-gray-800").removeClass("hover:bg-gray-50");
    
    // Blue colors for dark mode
    $(".bg-blue-50").addClass("bg-gray-800").removeClass("bg-blue-50");
    $(".border-blue-100").addClass("border-gray-700").removeClass("border-blue-100");
    $(".border-blue-400").addClass("border-blue-600").removeClass("border-blue-400");
    $(".text-blue-500").addClass("text-blue-400").removeClass("text-blue-500");
    
    // Status badges
    $(".status-success").addClass("bg-emerald-900 text-emerald-200").removeClass("bg-emerald-50 text-emerald-600");
    $(".status-failed").addClass("bg-red-900 text-red-200").removeClass("bg-red-50 text-red-600");
    $(".status-pending").addClass("bg-amber-900 text-amber-200").removeClass("bg-amber-50 text-amber-600");
    $(".status-default").addClass("bg-gray-800 text-gray-300").removeClass("bg-gray-50 text-gray-500");
    
    // Refund tags
    $(".refund-tag-full").addClass("bg-red-900 text-red-200").removeClass("bg-red-50 text-red-600");
    $(".refund-tag-partial").addClass("bg-orange-900 text-orange-200").removeClass("bg-orange-50 text-orange-600");
    
    // Pagination
    $(".active-page").addClass("bg-blue-600").removeClass("bg-blue-500");
    $(".page-button:not(.active-page)").addClass("bg-gray-800 text-gray-300 border-gray-700").removeClass("bg-white text-gray-600 border-gray-200");
    
    // Footer
    $("#footerText").addClass("text-gray-300").removeClass("text-gray-500");
    $("#activeGatewaysDisplay span").addClass("bg-gray-700").removeClass("bg-gray-200");
    
  } else {
    $("#body").removeClass("bg-gray-800 text-white");
    
    // Main table styles
    $(".bg-gray-900").addClass("bg-white").removeClass("bg-gray-900");
    $(".text-gray-200").addClass("text-gray-700").removeClass("text-gray-200");
    $(".text-gray-300").addClass("text-gray-600").removeClass("text-gray-300");
    $(".text-gray-400").addClass("text-gray-500").removeClass("text-gray-400");
    $(".text-gray-500").addClass("text-gray-400").removeClass("text-gray-500");
    $(".bg-gray-800").addClass("bg-gray-50").removeClass("bg-gray-800");
    $(".border-gray-800").addClass("border-gray-100").removeClass("border-gray-800");
    $(".border-gray-700").addClass("border-gray-200").removeClass("border-gray-700");
    $(".bg-gray-700").addClass("bg-gray-100").removeClass("bg-gray-700");
    $(".hover\\:bg-gray-700").addClass("hover:bg-gray-100").removeClass("hover:bg-gray-700");
    $(".hover\\:bg-gray-800").addClass("hover:bg-gray-50").removeClass("hover:bg-gray-800");
    
    // Blue colors for light mode
    $(".bg-gray-800").not(".page-button").addClass("bg-blue-50").removeClass("bg-gray-800");
    $(".border-gray-700").addClass("border-blue-100").removeClass("border-gray-700");
    $(".border-blue-600").addClass("border-blue-400").removeClass("border-blue-600");
    $(".text-blue-400").addClass("text-blue-500").removeClass("text-blue-400");
    
    // Status badges
    $(".bg-emerald-900").addClass("bg-emerald-50 text-emerald-600").removeClass("bg-emerald-900 text-emerald-200");
    $(".bg-red-900").addClass("bg-red-50 text-red-600").removeClass("bg-red-900 text-red-200");
    $(".bg-amber-900").addClass("bg-amber-50 text-amber-600").removeClass("bg-amber-900 text-amber-200");
    $(".bg-gray-800.text-gray-300").addClass("bg-gray-50 text-gray-500").removeClass("bg-gray-800 text-gray-300");
    
    // Refund tags
    $(".bg-red-900.text-red-200").addClass("bg-red-50 text-red-600").removeClass("bg-red-900 text-red-200");
    $(".bg-orange-900.text-orange-200").addClass("bg-orange-50 text-orange-600").removeClass("bg-orange-900 text-orange-200");
    
    // Pagination
    $(".active-page").addClass("bg-blue-500").removeClass("bg-blue-600");
    $(".page-button:not(.active-page)").addClass("bg-white text-gray-600 border-gray-200").removeClass("bg-gray-800 text-gray-300 border-gray-700");
    
    // Footer
    $("#footerText").addClass("text-gray-500").removeClass("text-gray-300");
    $("#activeGatewaysDisplay span").addClass("bg-gray-200").removeClass("bg-gray-700");
  }
}

function checkAuth() {
  return localStorage.getItem("isAuthenticated") === "true";
}

function logout() {
  localStorage.removeItem("isAuthenticated");
  localStorage.removeItem("tenant");
  window.location.href = "login.html";
}

function copyToClipboard(value, label = "Value") {
  if (!value) return;

  navigator.clipboard.writeText(value).then(() => {
    showToast(`${label} copied!`, "success");
  }).catch(() => {
    showToast("Failed to copy", "error");
  });
}

// Add cursor pointer styling for copyable elements
if (typeof document !== 'undefined' && !document.getElementById('copyOnClickStyle')) {
  const style = document.createElement('style');
  style.id = 'copyOnClickStyle';
  style.innerHTML = `
    .copy-on-click { cursor: pointer; }
    .copy-on-click:hover { text-decoration: underline; }
    .order-id { cursor: pointer; }
  `;
  document.head.appendChild(style);
}




let currentFilters = {
  from: "",
  to: "",
  status: "ALL", // allow showing SUCCESS + FAILED + PENDING + etc
  search: "",
  page: 1,
  limit: parseInt(localStorage.getItem("itemsPerPage") || "10"),
  sort: "createdAt:-1", // Sort newest first
};

function setupEventListeners() {
  $("#applyFilters").on("click", applyFilters);
  $("#resetFilters").on("click", resetFilters);
  $("#prevPage").off('click').on('click', () => {
    if (currentFilters.page > 1) changePage(currentFilters.page - 1);
  });
  $("#nextPage").off('click').on('click', () => {
    changePage(currentFilters.page + 1);
  });
  $("#itemsPerPage").on("change", (e) => {
    const limit = parseInt($(e.target).val());
    localStorage.setItem("itemsPerPage", limit);
    currentFilters.limit = limit;
    currentFilters.page = 1;
    // Do not update top stats when user changes page size
    loadData({ updateStats: false });
  });
  $("#exportData").on("click", exportToCSV);
}

//let showRefundOnly = true;

// async function toggleRefundView() {
//   // Show loading spinner
//   $("#refundToggleBtn").prop("disabled", true);
//   $("#refundBtnSpinner").removeClass("hidden");
//   $("#refundBtnText").text("Loading...");

//   showRefundOnly = true;
//   currentFilters.showRefundOnly = "true";

//   try {
//     await loadData(); // Wait for data to load
//   } catch (error) {
//     console.error("Error loading refund data:", error);
//   } finally {
//     // Hide spinner and reset button text
//     $("#refundBtnSpinner").addClass("hidden");
//     $("#refundBtnText").text("Show Refund Only");
//     $("#refundToggleBtn").prop("disabled", false);
//   }
// }

async function checkHealth() {
  try {
    const url = `${BASE_URL}/transactions`;
    console.log("Checking health at:", url);
    const response = await $.ajax({
      url: url,
      method: "GET",
    });
    console.log("Health check:", response);
    if (!response.collectionExists || response.documentCount === 0) {
      $("#transactionTable").html(`
            <tr><td colspan="11" class="px-5 py-4 text-center text-red-500">
              ${
                response.collectionExists
                  ? "No successful transactions found in database"
                  : 'Collection "payments" does not exist'
              }
            </td></tr>
          `);
    }
  } catch (error) {
    console.error("Health check failed:", error);
    $("#transactionTable").html(`
          <tr><td colspan="11" class="px-5 py-4 text-center text-red-500">Failed to connect to server. Please check if the backend is running.</td></tr>
        `);
  }
}

async function loadData(options = {}) {
  const { updateStats: shouldUpdateStats = true } = options;
  try {
    const params = new URLSearchParams();

    // Add all filters safely — .set() prevents duplicates
    Object.entries(currentFilters).forEach(([key, value]) => {
      if (value !== undefined && value !== "" && value !== null) {
        params.set(key, value);
      }
    });

    // Force sort
    params.set("sort", "createdAt:-1");

    // Explicitly control refundOnly — only once!
    if (currentFilters.refundOnly === "true") {
      params.set("refundOnly", "true");
    } else {
      params.delete("refundOnly");
    }

    const url = `${BASE_URL}/payments?${params.toString()}`;
    console.log("Fetching →", url);

    const response = await $.ajax({
      url: url,
      method: "GET",
    });

    if (shouldUpdateStats) updateStats(response.stats);
    updateTable(response.transactions, response.total);

    // Visual feedback
    $("#transactionType").toggleClass(
      "bg-orange-100 ring-2 ring-orange-500 font-medium",
      currentFilters.refundOnly === "true"
    );
  } catch (error) {
    console.error("Load failed:", error);
    $("#transactionTable").html(`
      <tr><td colspan="8" class="text-center py-16 text-red-600">
        Failed to load data.
      </td></tr>
    `);
  }
}

function applyFilters() {
  const fromDate = $("#fromDate").val();
  const toDate = $("#toDate").val();
  const selectedStatus = ($("#transactionType").val() || "ALL").toUpperCase();
  const searchQuery = $("#searchQuery").val().trim();

  console.log("Applying filters:", {
    fromDate,
    toDate,
    selectedStatus,
    searchQuery,
  });

  currentFilters.page = 1;
  currentFilters.from = fromDate || "";
  currentFilters.to = toDate || "";
  currentFilters.search = searchQuery || "";

  if (selectedStatus === "REFUNDED") {
    // refunded is implemented as success + refundOnly
    currentFilters.status = "SUCCESS";
    currentFilters.refundOnly = "true";
  } else {
    currentFilters.status = selectedStatus || "ALL";
    delete currentFilters.refundOnly;
  }

  currentFilters.sort = "createdAt:-1";

  loadData();
}
function resetFilters() {
  $("#fromDate").val("");
  $("#toDate").val("");
  $("#transactionType").val("ALL");
  $("#searchQuery").val("");
  $("#itemsPerPage").val(localStorage.getItem("itemsPerPage") || "10");

  currentFilters = {
    from: "",
    to: "",
    status: "ALL",
    search: "",
    page: 1,
    limit: parseInt(localStorage.getItem("itemsPerPage") || "10"),
    sort: "createdAt:-1",
  };

  delete currentFilters.refundOnly;

  console.log("All filters reset");
  loadData();
}

function updateStats(stats) {
  const thisMonthCount = Number(stats?.thisMonthCount || 0);
  const thisMonthLKR = Number(stats?.thisMonthAmountLKR || 0);
  const thisMonthUSD = Number(stats?.thisMonthAmountUSD || 0);
  const failedCount = Number(stats?.failedTransactions || 0);

  // Ensure the cards container uses a responsive grid so cards can sit on one row on large screens
  $("#statsCards")
    .removeClass()
    .addClass("grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4");

  $("#statsCards").html(`
        <div class="bg-red-400 rounded-lg shadow text-white p-4 cursor-pointer hover:opacity-90 transition" data-card-filter="thisMonth">
          <div class="flex items-end justify-between">
            <div>
              <div class="text-2xl font-bold">${thisMonthCount.toLocaleString(
                "en-US"
              )}</div>
              <div class="text-xs opacity-90 mt-1">This Month Transactions</div>
            </div>
            <div class="text-right">
              <div class="text-sm font-semibold">LKR ${thisMonthLKR.toLocaleString(
                "en-US",
                { minimumFractionDigits: 2, maximumFractionDigits: 2 }
              )}</div>
              <div class="text-xs font-medium">USD ${thisMonthUSD.toLocaleString(
                "en-US",
                { minimumFractionDigits: 2, maximumFractionDigits: 2 }
              )}</div>
            </div>
          </div>
        </div>

        <div class="bg-gradient-to-r from-primary to-blue-800 rounded-lg shadow text-white p-4 cursor-pointer hover:opacity-90 transition" data-card-filter="all">
          <div class="text-2xl font-bold">${stats.totalTransactions.toLocaleString(
            "en-US"
          )}</div>
          <div class="text-xs opacity-90 mt-1">Total Transactions</div>
        </div>

        <div class="bg-gradient-to-r from-rose-600 to-red-700 rounded-lg shadow text-white p-4 cursor-pointer hover:opacity-90 transition" data-card-filter="failed">
          <div class="text-2xl font-bold">${failedCount.toLocaleString(
            "en-US"
          )}</div>
          <div class="text-xs opacity-90 mt-1">Failed Transactions</div>
        </div>

        <div class="bg-gradient-to-r from-green-500 to-green-700 rounded-lg shadow text-white p-4 cursor-pointer hover:opacity-90 transition" data-card-filter="success">
          <div class="text-2xl font-bold">${stats.successfulTransactions.toLocaleString(
            "en-US"
          )}</div>
          <div class="text-xs opacity-90 mt-1">Successful Transactions</div>
        </div>

        <div class="bg-gradient-to-r from-green-500 to-green-700 rounded-lg shadow text-white p-4 cursor-pointer hover:opacity-90 transition" data-card-filter="success">
          <div class="text-2xl font-bold">LKR ${Number(
            stats.totalAmountLKR
          ).toLocaleString("en-US")}</div>
          <div class="text-xs opacity-90 mt-1">Total Amount (LKR)</div>
        </div>

        <div class="bg-gradient-to-r from-cyan-500 to-cyan-700 rounded-lg shadow text-white p-4 cursor-pointer hover:opacity-90 transition" data-card-filter="success">
          <div class="text-2xl font-bold">USD ${Number(
            stats.totalAmountUSD
          ).toLocaleString("en-US")}</div>
          <div class="text-xs opacity-90 mt-1">Total Amount (USD)</div>
        </div>
      `);

  // Click handlers
  $('#statsCards [data-card-filter]')
    .off('click')
    .on('click', function () {
      const filter = $(this).data('card-filter');

      const now = new Date();
      const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
      const monthStartStr = monthStart.toISOString().split('T')[0];
      const todayStr = now.toISOString().split('T')[0];

      // Reset paging
      currentFilters.page = 1;

      if (filter === 'all') {
        currentFilters.status = 'ALL';
        delete currentFilters.refundOnly;
        currentFilters.from = '';
        currentFilters.to = '';
        $('#transactionType').val('ALL');
        $('#fromDate').val('');
        $('#toDate').val('');

        // Keep top stats fixed when cards are clicked
        loadData({ updateStats: false });
        return;
      }

      if (filter === 'success') {
        currentFilters.status = 'SUCCESS';
        delete currentFilters.refundOnly;
        $('#transactionType').val('SUCCESS');
      }

      if (filter === 'failed') {
        currentFilters.status = 'FAILED';
        delete currentFilters.refundOnly;
        $('#transactionType').val('FAILED');
      }

      if (filter === 'thisMonth') {
        currentFilters.from = monthStartStr;
        currentFilters.to = todayStr;
        $('#fromDate').val(monthStartStr);
        $('#toDate').val(todayStr);
      }

      currentFilters.sort = 'createdAt:-1';

      // Only refresh table; keep top stats cards unchanged
      loadData({ updateStats: false });
    });
}

$(document).ready(function () {
  console.log("jQuery initialized for transaction table");

  // Side panel for attempts (created once)
  (function ensureAttemptsPanel() {
    if (document.getElementById('attemptsPanelOverlay')) return;

    const panelHtml = `
      <div id="attemptsPanelOverlay" class="fixed inset-0 bg-black/40 hidden z-50">
        <div id="attemptsPanel" class="absolute right-0 top-0 h-full w-full sm:w-[560px] bg-white shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col">
          <div class="px-5 py-4 border-b border-gray-200 flex items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="text-xs uppercase tracking-wider text-gray-400">Attempts</div>
              <div id="attemptsPanelTitle" class="text-base font-semibold text-gray-800 truncate">Order</div>
              <div id="attemptsPanelSubtitle" class="mt-1 text-xs text-gray-500 truncate"></div>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" id="closeAttemptsPanel" class="px-3 py-2 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm">Close</button>
            </div>
          </div>
          <div id="attemptsPanelBody" class="p-5 overflow-auto flex-1 bg-gray-50"></div>
        </div>
      </div>
    `;

    $('body').append(panelHtml);

    function closePanel() {
      $('#attemptsPanel').addClass('translate-x-full');
      $('#attemptsPanelOverlay').addClass('hidden');
      $('body').removeClass('overflow-hidden');
    }

    $('#closeAttemptsPanel').on('click', closePanel);
    $('#attemptsPanelOverlay').on('click', function (e) {
      if (e.target && e.target.id === 'attemptsPanelOverlay') closePanel();
    });

    window.__closeAttemptsPanel = closePanel;
  })();

  // -----------------------------
  // Attempts panel helpers
  // -----------------------------
  function escapeHtml(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function pill(label, tone) {
    const base = 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border';
    const map = {
      green: 'bg-emerald-50 text-emerald-700 border-emerald-100',
      red: 'bg-red-50 text-red-700 border-red-100',
      amber: 'bg-amber-50 text-amber-700 border-amber-100',
      blue: 'bg-blue-50 text-blue-700 border-blue-100',
      gray: 'bg-gray-100 text-gray-700 border-gray-200',
    };
    return `<span class="${base} ${map[tone] || map.gray}">${escapeHtml(label)}</span>`;
  }

  function fmtTime(v) {
    if (!v) return '';
    try {
      const d = new Date(v);
      if (isNaN(d.getTime())) return String(v);
      return d.toLocaleString();
    } catch {
      return String(v);
    }
  }

  function openPanel() {
    $('#attemptsPanelOverlay').removeClass('hidden');
    $('body').addClass('overflow-hidden');
    requestAnimationFrame(() => {
      $('#attemptsPanel').removeClass('translate-x-full');
    });
  }

  // -----------------------------
  // Attempts panel mappings
  // -----------------------------
  const ACQUIRER_CODE_MEANINGS = {
    '00': 'Approved',
    '05': 'Do not honor',
    '12': 'Invalid transaction',
    '14': 'Invalid card number',
    '39': 'No credit account',
    '51': 'Insufficient funds',
    '54': 'Expired card',
    '91': 'Issuer unavailable',
    '': 'Not proceed',
  };

  function getAcquirerMeaning(code) {
    const c = code == null ? '' : String(code).trim();
    if (!c) return ACQUIRER_CODE_MEANINGS[''];
    return ACQUIRER_CODE_MEANINGS[c] || 'Unknown';
  }

  function getProceedHint(a) {
    const rec = a?.gatewayRecommendation || a?.response?.gatewayRecommendation || '';
    const gwc = a?.gatewayCode || a?.response?.gatewayCode || '';
    const auth = a?.authenticationStatus || a?.order?.authenticationStatus || '';
    const status = a?.order?.status || '';

    const parts = [];
    if (rec) parts.push(String(rec));
    else if (gwc) parts.push(String(gwc));
    if (auth) parts.push(String(auth));
    else if (status) parts.push(String(status));

    return parts.length ? parts.join(' • ') : '';
  }

  function getTxnStatusTone(paymentStatus) {
    const s = String(paymentStatus || 'UNKNOWN').toUpperCase();
    if (s === 'SUCCESS') return 'green';
    if (s === 'PENDING') return 'amber';
    if (['FAILED', 'FAIL', 'ERROR'].includes(s)) return 'red';
    return 'gray';
  }

  // -----------------------------
  // Attempts panel rendering
  // -----------------------------
  function renderAttemptsHeader(t, attemptsLen) {
    const statusLabel = String(t?.paymentStatus || 'UNKNOWN').toUpperCase();
    const statusTone = getTxnStatusTone(statusLabel);

    return `
      <div class="bg-white border border-gray-200 rounded-xl p-4 mb-4">
        <div class="flex flex-wrap items-center gap-2">
          ${pill('Attempts: ' + attemptsLen, 'blue')}
          ${pill('Status: ' + statusLabel, statusTone)}
          ${t?.latestTotalRefunded > 0 ? pill('Refunded: ' + t.latestTotalRefunded, 'amber') : ''}
        </div>
        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div>
            <div class="text-[11px] uppercase tracking-wider text-gray-400">Customer</div>
            <div class="text-gray-800 break-words">${escapeHtml(t?.email || 'N/A')}</div>
          </div>
          <div>
            <div class="text-[11px] uppercase tracking-wider text-gray-400">Card</div>
            <div class="text-gray-800">${escapeHtml(t?.cardBrand || 'N/A')} • ${escapeHtml(t?.cardNumber ? ('•••' + String(t.cardNumber).slice(-4)) : 'N/A')}</div>
          </div>
        </div>
      </div>
    `;
  }

  function renderNoAttempts(t) {
    const parts = [];
    if (t?.authenticationStatus) parts.push(String(t.authenticationStatus));
    if (t?.order?.authenticationStatus) parts.push(String(t.order.authenticationStatus));
    if (t?.order?.status) parts.push(String(t.order.status));
    if (t?.gatewayRecommendation) parts.push(String(t.gatewayRecommendation));
    if (t?.gatewayCode) parts.push(String(t.gatewayCode));

    const hint = parts.filter(Boolean).join(' • ');

    return `
      <div class="bg-white border border-gray-200 rounded-xl p-5 text-sm text-gray-700">
        <div class="text-base font-semibold text-gray-800">No payment attempts found</div>
        <div class="mt-2 text-sm text-gray-600">${escapeHtml(hint || 'AUTHENTICATION FAILURE')}</div>
      </div>
    `;
  }

  function renderAttemptCard(a, idx) {
    const final = String(a?.finalResult || '').toUpperCase();
    const tone = final === 'SUCCESS' ? 'green' : (final === 'FAIL' || final === 'FAILED' || final === 'ERROR') ? 'red' : final === 'PENDING' ? 'amber' : 'gray';

    const headerPills = [
      (a?.type ? pill(a.type, 'blue') : ''),
      (final ? pill(final === 'FAIL' ? 'FAILED' : final, tone) : pill('UNKNOWN', 'gray')),
      (a?.gatewayCode ? pill(`GW: ${a.gatewayCode}`, 'gray') : ''),
    ].filter(Boolean).join(' ');

    const time = fmtTime(a?.timeOfRecord || a?.timeOfLastUpdate || a?.timestamp);

    const rawCode = (
      a?.acquirerCode ??
      a?.response?.acquirerCode ??
      a?.transaction?.acquirer?.code ??
      a?.transaction?.acquirerCode ??
      ''
    );
    const acqCode = String(rawCode ?? '').trim();
    const acqMeaning = getAcquirerMeaning(acqCode);
    const proceedHint = getProceedHint(a);

    const kv = (k, v, mono = false) => `
      <div>
        <div class="text-[11px] uppercase tracking-wider text-gray-400">${escapeHtml(k)}</div>
        <div class="${mono ? 'font-mono text-xs' : 'text-sm'} text-gray-800 break-words">${escapeHtml(v ?? 'N/A')}</div>
      </div>
    `;

    return `
      <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-4">
        <div class="px-4 py-3 border-b border-gray-100">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="flex items-center flex-wrap gap-2">
                <div class="text-sm font-semibold text-gray-800">Attempt #${idx + 1}</div>
                <div class="flex flex-wrap gap-2">${headerPills}</div>
              </div>
              ${time ? `<div class="mt-1 text-xs text-gray-500">${escapeHtml(time)}</div>` : ''}
            </div>
          </div>
        </div>

        <div class="p-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            ${kv('Amount', `${a?.amount ?? 'N/A'} ${a?.currency || ''}`)}
            ${kv('MPGS Txn ID', a?.mpgsTransactionId || 'N/A', true)}
            ${kv('Acquirer Code', acqCode ? (acqCode + ' • ' + acqMeaning) : 'Not proceed', true)}
            ${kv('Proceed Hint', proceedHint || 'N/A')}
            ${kv('Acquirer Message', a?.acquirerMessage || a?.response?.acquirerMessage || 'N/A')}
            ${kv('Acquirer Txn ID', a?.acquirerTransactionId || a?.transaction?.acquirer?.transactionId || 'N/A', true)}
            ${kv('Authorization Code', a?.authorizationCode || a?.transaction?.authorizationCode || 'N/A', true)}
          </div>

          <div class="mt-4">
            <div class="bg-gray-50 border border-gray-100 rounded-lg p-3">
              <div class="text-[11px] uppercase tracking-wider text-gray-400">Order snapshot</div>
              <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                ${kv('Status', a?.order?.status || 'N/A')}
                ${kv('Captured', a?.order?.totalCapturedAmount ?? 'N/A')}
                ${kv('Refunded', a?.order?.totalRefundedAmount ?? 'N/A')}
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  function openAttemptsPanel(t) {
    const attempts = Array.isArray(t?.attempts) ? t.attempts : [];

    $('#attemptsPanelTitle').text(
      `Order ${t?.orderId || 'N/A'} • ${attempts.length} attempt${attempts.length === 1 ? '' : 's'}`
    );

    const subtitleParts = [];
    if (t?.email) subtitleParts.push(t.email);
    if (t?.currency && t?.amount != null) subtitleParts.push(`${t.amount} ${t.currency}`);
    if (t?.transactionId) subtitleParts.push(`Txn: ${t.transactionId}`);
    $('#attemptsPanelSubtitle').text(subtitleParts.join(' • '));

    if (!attempts.length) {
      $('#attemptsPanelBody').html(renderNoAttempts(t));
      openPanel();
      return;
    }

    const cardsHtml = attempts.map(renderAttemptCard).join('');
    $('#attemptsPanelBody').html(renderAttemptsHeader(t, attempts.length) + cardsHtml);
    openPanel();
  }

  // ESC closes panel
  $(document)
    .off('keydown.attemptsPanel')
    .on('keydown.attemptsPanel', function (e) {
      if (e.key === 'Escape' && typeof window.__closeAttemptsPanel === 'function') {
        window.__closeAttemptsPanel();
      }
    });

  // expose for row button click handler
  window.openAttemptsPanel = openAttemptsPanel;
});

function updateTable(transactions, total) {
  console.log("Updating table with", transactions.length, "transactions");

  // store current rows transactions for attempts panel access
  window.__currentTransactions = Array.isArray(transactions) ? transactions : [];

  $("#transactionTable").html(
    transactions.length > 0
      ? transactions
          .map((t, index) => {
            const originalAmount = parseFloat(t.amount || 0);
            const totalRefunded =
              t.latestTotalRefunded != null
                ? parseFloat(t.latestTotalRefunded)
                : 0;
            const isFullyRefunded = totalRefunded >= originalAmount;

            // Refund Tag
            let refundTag = "";
            if (totalRefunded > 0) {
              if (isFullyRefunded) {
                refundTag = `<span class="refund-tag-full inline-flex items-center gap-1.5 bg-red-50 text-red-600 text-xs font-bold px-3 py-1.5 rounded-full">
                  FULL REFUND
                </span>`;
              } else {
                refundTag = `<span class="refund-tag-partial inline-flex items-center gap-1.5 bg-orange-50 text-orange-600 text-xs font-bold px-3 py-1.5 rounded-full">
                  PARTIAL – ${totalRefunded.toFixed(2)} ${t.currency}
                </span>`;
              }
            }

            const statusBadge = (() => {
              const s = (t.paymentStatus || "UNKNOWN").toUpperCase();
              switch (s) {
                case "SUCCESS":
                  return `<span class="status-success inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-600 text-xs font-bold px-3 py-1.5 rounded-full">SUCCESS</span>`;
                case "FAILED":
                case "FAIL":
                case "ERROR":
                  return `<span class="status-failed inline-flex items-center gap-1.5 bg-red-50 text-red-600 text-xs font-bold px-3 py-1.5 rounded-full">FAILED</span>`;
                case "PENDING":
                  return `<span class="status-pending inline-flex items-center gap-1.5 bg-amber-50 text-amber-600 text-xs font-bold px-3 py-1.5 rounded-full">PENDING</span>`;
                default:
                  return `<span class="status-default bg-gray-50 text-gray-500 text-xs font-medium px-3 py-1.5 rounded-full">${s}</span>`;
              }
            })();

            const showRefundBtn =
              t.paymentStatus === "SUCCESS" && !isFullyRefunded;

            const attemptsCount = Array.isArray(t.attempts) ? t.attempts.length : 0;
            const attemptsBtn = `<button type="button" class="attempts-button bg-white hover:bg-gray-100 border border-gray-200 text-gray-700 font-semibold py-2.5 px-5 rounded-lg shadow-sm transition-all duration-200 text-sm whitespace-nowrap" data-index="${index}">
                   Attempts (${attemptsCount})
                 </button>`;

            const addressBlock =
              (String(CURRENT_TENANT || '').toLowerCase() === 'helpage' && t.address)
                ? `
          <div>
            <span class="text-xs uppercase tracking-wider text-gray-400">Address</span>
            <p class="font-medium text-gray-700"><span class="copy-on-click">${t.address}</span></p>
          </div>
        `
                : '';

            return `
<tr class="table-row expandable-row group bg-white border-b border-gray-100 hover:bg-gray-50 transition-all duration-200" id="row-${index}">
  <td colspan="8" class="px-6 py-4">
    <!-- Main Row -->
    <div class="main-row grid grid-cols-8 gap-6 items-center">

      <div class="date-cell text-sm font-medium text-gray-600">
  ${new Date(t.createdAt).toISOString().split("T")[0]}
</div>

      <div class="order-id font-bold text-lg text-blue-500 truncate max-w-[220px]" title="${t.orderId || ''}">
        <span class="copy-on-click">${t.orderId || "N/A"}</span>
      </div>

     <div class="amount text-lg font-semibold text-gray-700">
  <span class="copy-on-click">${Number(originalAmount).toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
  })}</span>
</div>

     <div class="currency text-sm font-medium uppercase tracking-wider text-gray-600">
  ${t.currency || "LKR"}
</div>

     <div class="truncate max-w-[200px] text-sm" title="${t.email || ""}">
  <span class="email text-sm font-medium text-gray-600"><span class="copy-on-click">${t.email || "N/A"}</span></span>
</div>

<div class="card-number text-sm font-medium text-gray-600 font-mono"
     title="${t.cardNumber || 'N/A'}">
  ${t.cardNumber ? "•••" + t.cardNumber.slice(-4) : "N/A"}
</div>

      <div class="card-brand text-sm font-medium text-gray-600">
        ${t.cardBrand || "N/A"}
      </div>

      <div class="flex justify-center">
        ${statusBadge}
      </div>

    </div>
    <div class="expand-content mt-4 p-5 bg-blue-50 rounded-xl border border-blue-100 hidden">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-sm">
        <!-- Left: Details -->
        <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
          <div>
            <span class="text-xs uppercase tracking-wider text-gray-400">Name on Card</span>
            <p class="font-semibold text-gray-700"><span class="copy-on-click">${
              t.nameOnCard || "N/A"
            }</span></p>
          </div>
          <div>
            <span class="text-xs uppercase tracking-wider text-gray-400">Reference</span>
            <p class="font-medium text-gray-700"><span class="copy-on-click">${t.description || "N/A"}</span></p>
          </div>

          <div>
            <span class="text-xs uppercase tracking-wider text-gray-400">Transaction ID</span>
            <p class="font-mono text-xs bg-white text-gray-600 border border-gray-200 px-3 py-1.5 rounded mt-1 break-all" 
               title="${t.transactionId || ""}">
              <span class="copy-on-click">${t.transactionId || "N/A"}</span>
            </p>
          </div>

          ${addressBlock}

          ${refundTag ? `<div class="mt-1">${refundTag}</div>` : ""}

        </div>

        <!-- Right: Actions -->
        <div class="flex items-center gap-3 sm:justify-end">
          ${attemptsBtn}
          ${
            showRefundBtn
              ? `
          <button
            onclick="openRefundModal(
              '${(t.orderId || "").replace(/'/g, "\\'")}',
              '${(t.nameOnCard || "N/A").replace(/'/g, "\\'")}',
              '${t.cardNumber || "N/A"}',
              '${t.amount}',
              '${t.currency}',
              '${(t.email || "").replace(/'/g, "\\'")}',
              '${t.uuid || ""}'
            )"
            class="refund-button bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-bold py-2.5 px-6 rounded-lg shadow transform hover:scale-105 transition-all duration-200 text-sm flex items-center gap-2 whitespace-nowrap"
          >
            Refund
          </button>
        `
              : ""
          }
        </div>

      </div>
    </div>
  </td>
</tr>
`;
          })
          .join("")
      : `<tr><td colspan="8" class="px-6 py-16 text-center text-gray-400 text-lg">
              <div class="flex flex-col items-center gap-3">
                <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-2m3 2v-2m-9 7h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="text-gray-500">No successful transactions found</p>
              </div>
            </td></tr>`
  );

  // Attempts button handler (do not trigger row expand)
  $('.attempts-button')
    .off('click')
    .on('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      const idx = Number($(this).data('index'));
      const t = (window.__currentTransactions || [])[idx];
      openAttemptsPanel(t);
    });

  // Click to expand
  $(".expandable-row")
    .off("click")
    .on("click", function (e) {
      // keep existing behavior: clicking any button should not expand
      if ($(e.target).closest("button").length) return;
      // NEW: clicking on copyable values should NOT toggle/scroll the row
      if ($(e.target).closest('.copy-on-click').length) return;
      const index = this.id.split("-")[1];
      toggleRow(index);
    });

  // Delegated copy-on-click handler
  $(document).off('click', '.copy-on-click').on('click', '.copy-on-click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    const text = $(this).text().trim();
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard', 'success');
      }).catch(() => {
        showToast('Copy failed', 'error');
      });
    } else {
      // Fallback
      const $temp = $('<textarea>').val(text).appendTo('body').select();
      try {
        document.execCommand('copy');
        showToast('Copied to clipboard', 'success');
      } catch (err) {
        showToast('Copy failed', 'error');
      }
      $temp.remove();
    }
  });

  // Update pagination buttons state
  try {
    const limit = Math.max(1, parseInt(currentFilters.limit || 10));
    const totalPages = Math.max(1, Math.ceil((total || 0) / limit));
    $("#prevPage").prop('disabled', currentFilters.page <= 1);
    $("#nextPage").prop('disabled', currentFilters.page >= totalPages);
  } catch (err) {
    // ignore UI update errors
  }
}

function toggleRow(index) {
  const $row = $(`#row-${index}`);
  const $content = $row.find(".expand-content");
  $(".expand-content").not($content).slideUp(300).addClass("hidden");

  if ($content.hasClass("hidden")) {
    $content.removeClass("hidden").slideDown(350);
    $row.addClass("border-l-4 border-blue-400");
  } else {
    $content.slideUp(300, () => $content.addClass("hidden"));
    $row.removeClass("border-l-4 border-blue-400");
  }
}

window.updateTable = updateTable;
window.changePage = function (page) {
  const p = Math.max(1, parseInt(page) || 1);
  if (p === currentFilters.page) return;
  currentFilters.page = p;
  // Do not update top stats when paginating
  loadData({ updateStats: false });
};

async function exportToCSV() {
  try {
    // Use the same endpoint as your table — it has correct latestTotalRefunded!
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(currentFilters)) {
      if (value) params.append(key, value);
    }

    // Respect selected status (do not force SUCCESS)
    params.set("sort", "createdAt:-1");
    params.set("limit", "10000"); // Get all records

    const url = `${BASE_URL}/payments?${params.toString()}`;
    console.log("Exporting from correct endpoint:", url);

    const response = await $.ajax({ url, method: "GET" });
    const transactions = response.transactions || [];

    // Headers
    const headers = [
      "Date,Merchant ID,Order ID,Original Amount,Refunded Amount,Currency,Email,Description,Card Brand,Name on Card,Card Number,Status",
    ];

    // Rows
    const rows = transactions.map((t) => {
      const date = t.createdAt
        ? new Date(t.createdAt).toISOString().split("T")[0]
        : "N/A";
      const original = parseFloat(t.amount || 0).toFixed(2);
      const refunded =
        t.latestTotalRefunded != null
          ? parseFloat(t.latestTotalRefunded).toFixed(2)
          : "0.00";
      const maskedCard = t.cardNumber ? "****" + t.cardNumber.slice(-4) : "N/A";
      const status = (t.paymentStatus || "UNKNOWN").toUpperCase();

      return `"${date}","${t.merchantId || "N/A"}","${
        t.orderId || "N/A"
      }","${original}","${refunded}","${t.currency || "LKR"}","${
        t.email || "N/A"
      }","${t.description || "N/A"}","${t.cardBrand || "N/A"}","${
        t.nameOnCard || "N/A"
      }","${maskedCard}","${status}"`;
    });

    const csv = [...headers, ...rows].join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `transactions_${
      new Date().toISOString().split("T")[0]
    }.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    showToast("CSV exported successfully!", "success");
  } catch (error) {
    console.error("Export failed:", error);
    showToast("Failed to export CSV", "error");
  }
}
function displayActiveGateways(data) {
  const activeGateways = data.activeGateways || {};
  const container = $("#activeGatewaysDisplay");
  container.empty().data("activeGateways", activeGateways);
  if (!activeGateways || Object.keys(activeGateways).length === 0) return;

  const currentTheme = localStorage.getItem("theme") || "light";
  const bgClass = currentTheme === "dark" ? "bg-gray-700" : "bg-gray-200";
  const textClass = currentTheme === "dark" ? "text-gray-200" : "text-gray-700";

  for (const [card, gateway] of Object.entries(activeGateways)) {
    const icon = card === "visa/master" ? "💳" : "🟡";
    const displayName =
      card === "visa/master"
        ? `Master - ${gateway.name}`
        : `Amex - ${gateway.name}`;
    container.append(`
      <span class="px-3 py-1 sm:px-2 sm:py-0.5 ${bgClass} ${textClass} rounded flex items-center space-x-2 sm:space-x-1 text-sm sm:text-xs">
        <span>${icon}</span>
        <span class="truncate">${displayName}</span>
      </span>
    `);
  }
}

function showGatewayModal(tenantData, tenant) {
  const { allGateways = {}, activeGateways = {} } = tenantData;

  let modalContent = '<div class="flex flex-col space-y-3">';
  for (const [card, gateway] of Object.entries(allGateways)) {
    const checked = activeGateways[card] ? "checked" : "";
    const labelName =
      card === "visa/master"
        ? `Visa / Master - ${gateway.name}`
        : `Amex - ${gateway.name}`;
    modalContent += `
      <label class="flex items-center space-x-2">
        <input type="checkbox" class="gatewayChk" data-card="${card}" ${checked}/>
        <span>${labelName}</span>
      </label>
    `;
  }

  modalContent += `
    <button id="activateGateways" class="mt-2 bg-green-600 text-white py-2 rounded" disabled>
      Activate
    </button>
  </div>`;

  $("#topupButtons").html(modalContent);
  $("#topupModal").removeClass("hidden");

  $(".gatewayChk").on("change", function () {
    $("#activateGateways").prop("disabled", false);
  });

  $("#activateGateways")
    .off("click")
    .on("click", function () {
      const updatedActiveGateways = {};
      $(".gatewayChk:checked").each(function () {
        const card = $(this).data("card");
        updatedActiveGateways[card] = allGateways[card];
      });

      const activateBtn = $("#activateGateways");
      activateBtn.prop("disabled", true).text("Activating...");

      $.ajax({
        url: `${BASE_URL}/settings/${tenant}/gateways/activate`,
        method: "POST",
        contentType: "application/json",
        data: JSON.stringify({ gateways: updatedActiveGateways }),
        success: function (result) {
          if (result.success) {
            console.log(this.url);
            displayActiveGateways({
              activeGateways: result.data.activeGateways,
            });
            showToast("✅ Gateways updated successfully!", "success");
          } else {
            showToast("❌ Failed to update gateways", "error");
          }
        },
        error: function (xhr, status, error) {
          console.error("Error:", error);
          showToast("❌ Error updating gateways", "error");
        },
        complete: function () {
          const activateBtn = $("#activateGateways");
          activateBtn.prop("disabled", false).text("Activate");
          $("#topupModal").addClass("hidden");
        },
      });
    });
}

$("#closeTopup").on("click", function () {
  $("#topupModal").addClass("hidden");
});

function fetchTenantGateways(tenant) {
  return $.ajax({
    url: `${BASE_URL}/settings/${tenant}/gateways`,
    method: "GET",
  })
    .then((data) => {
      if (!data.success || !data.data)
        return { allGateways: {}, activeGateways: {} };
      return {
        allGateways: data.data.allGateways || {},
        activeGateways: data.data.activeGateways || {},
      };
    })
    .catch((err) => {
      console.error("Error fetching gateways:", err);
      return { allGateways: {}, activeGateways: {} };
    });
}

function showToast(message, type = "info") {
  const bg =
    type === "success"
      ? "bg-green-600"
      : type === "error"
      ? "bg-red-600"
      : "bg-gray-600";

  const toast = $(`
    <div class="fixed bottom-4 right-4 px-4 py-2 text-white rounded shadow ${bg} opacity-0 transition-opacity duration-500 z-50">
      ${message}
    </div>
  `);
  $("body").append(toast);
  setTimeout(() => toast.css("opacity", 1), 50);
  setTimeout(() => toast.fadeOut(500, () => toast.remove()), 3500);
}

let refundData = {};

function openRefundModal(
  orderId,
  nameOnCard,
  cardNumber,
  originalAmount,
  currency,
  email,
  uuid
) {
  refundData = {
    orderId,
    originalAmount: parseFloat(originalAmount),
    currency,
    email,
    uuid: uuid,
  };

  console.log(orderId, currency, email, uuid);

  $("#modalOrderId").text(orderId);
  $("#modalNameOnCard").text(nameOnCard || "N/A");
  $("#modalCardNumber").text(maskCard(cardNumber));
  $("#modalOriginalAmount").text(
    `${refundData.originalAmount.toFixed(2)} ${currency}`
  );

  $("#modalEmail").text(email || "N/A");
  $("#refundAmountInput").val("");
  $("#amountError").addClass("hidden");

  // Reset modal state
  $("#refundFormContent").removeClass("hidden");
  $("#refundLoading").addClass("hidden");
  $("#refundSuccess").addClass("hidden");
  $("#refundError").addClass("hidden");
  $("#refundActions").removeClass("hidden");
  $("#refundDoneActions").addClass("hidden");

  $("#refundModal").removeClass("hidden").addClass("flex");
}

function closeRefundModal() {
  $("#refundModal").addClass("hidden").removeClass("flex");
  // Optional: reload page after close (only if needed)
  setTimeout(() => location.reload(), 300);
}

function maskCard(card) {
  if (!card || card === "N/A") return "N/A";
  const clean = card.replace(/\s/g, "");
  return clean.replace(/\d{12}(\d{4})/, "**** **** **** $1");
}

async function submitRefund() {
  const amount = parseFloat(document.querySelector("#refundAmountInput").value);
  const errorEl = document.querySelector("#amountError");

  // Validation
  if (isNaN(amount) || amount <= 0) {
    errorEl.textContent = "Please enter a valid amount";
    errorEl.classList.remove("hidden");
    return;
  }
  if (amount > refundData.originalAmount) {
    errorEl.textContent = `Cannot refund more than ${refundData.originalAmount.toFixed(
      2
    )} ${refundData.currency}`;
    errorEl.classList.remove("hidden");
    return;
  }

  errorEl.classList.add("hidden");

  // Hide the refund amount modal
  $("#refundModal").addClass("hidden").removeClass("flex");

  // Show confirmation popup with amount
  $("#confirmAmountText").text(`${amount.toFixed(2)} ${refundData.currency}`);
  $("#confirmOrderIdText").text(`${refundData.orderId}`);

  $("#confirmRefundPopup")
    .removeClass("hidden")
    .addClass("flex")
    .find(".transform")
    .removeClass("scale-95")
    .addClass("scale-100");

  // No → go back to refund modal
  $("#confirmNoBtn")
    .off("click")
    .on("click", () => {
      $("#confirmRefundPopup").addClass("hidden").removeClass("flex");
      $("#refundModal").removeClass("hidden").addClass("flex"); // Show again
    });

  // Yes → submit refund
  $("#confirmYesBtn")
    .off("click")
    .on("click", async () => {
      $("#confirmRefundPopup").addClass("hidden").removeClass("flex");

      // Show loading in refund modal
      $("#refundFormContent, #refundActions").addClass("hidden");
      $("#refundLoading").removeClass("hidden");
      $("#refundModal").removeClass("hidden").addClass("flex"); // Keep modal open

      try {
        const response = await fetch(`${BASE_URL}/refund`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            uuid: refundData.uuid,
            amount,
            currency: refundData.currency,
            email: refundData.email,
          }),
        });

        const result = await response.json();

        if (response.ok && result.success) {
          $("#refundLoading").addClass("hidden");
          $("#refundSuccess").removeClass("hidden");
          $("#refundDoneActions").removeClass("hidden");
          $("#successRefundId").text(result.refundId || "N/A");
        } else {
          $("#refundLoading").addClass("hidden");
          $("#refundError").removeClass("hidden");
          $("#refundDoneActions").removeClass("hidden");
          $("#errorMessage").text(result.message || "Refund failed");
        }
      } catch (err) {
        $("#refundLoading").addClass("hidden");
        $("#refundError").removeClass("hidden");
        $("#refundDoneActions").removeClass("hidden");
        $("#errorMessage").text("Network error");
      }
    });
}

$("#footerText").text("© 2025 Digitable.IO Plutos");
