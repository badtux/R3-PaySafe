let BASE_URL;
const today = new Date().toISOString().split("T")[0]; // e.g., "2025-10-24"
const UNIVERSAL_PASSWORD = "admin@123";
const IS_LOCAL = window.location.hostname.includes("127.0.0.1");

$(document).ready(() => {
  const hostname = window.location.hostname;
  const tenantFromUrl = window.location.tenent;
  const tenant = localStorage.getItem("tenant") || tenantFromUrl;
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
      ? `http://${tenant}.localhost:3008/api`
      : `https://${tenant}.go.digitable.io:3008/api`;
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
    $(".bg-white").addClass("bg-gray-900").removeClass("bg-white");
    $(".text-gray-700").addClass("text-gray-200").removeClass("text-gray-700");
    $(".bg-gray-50").addClass("bg-gray-700").removeClass("bg-gray-50");
    $(".border-gray-200")
      .addClass("border-gray-600")
      .removeClass("border-gray-200");
    $(".text-gray-500").addClass("text-gray-300").removeClass("text-gray-500");
    $(".bg-gray-100").addClass("bg-gray-600").removeClass("bg-gray-100");
    $(".hover\\:bg-gray-100")
      .addClass("hover:bg-gray-500")
      .removeClass("hover:bg-gray-100");
    $("#footerText").addClass("text-gray-300").removeClass("text-gray-500");
    $("#activeGatewaysDisplay span")
      .addClass("bg-gray-700")
      .removeClass("bg-gray-200");
  } else {
    $("#body").removeClass("bg-gray-800 text-white");
    $(".bg-gray-900").addClass("bg-white").removeClass("bg-gray-900");
    $(".text-gray-200").addClass("text-gray-700").removeClass("text-gray-200");
    $(".bg-gray-700").addClass("bg-gray-50").removeClass("bg-gray-700");
    $(".border-gray-600")
      .addClass("border-gray-200")
      .removeClass("border-gray-600");
    $(".text-gray-300").addClass("text-gray-500").removeClass("text-gray-300");
    $(".bg-gray-600").addClass("bg-gray-100").removeClass("bg-gray-600");
    $(".hover\\:bg-gray-500")
      .addClass("hover:bg-gray-100")
      .removeClass("hover:bg-gray-500");
    $("#footerText").addClass("text-gray-500").removeClass("text-gray-300");
    $("#activeGatewaysDisplay span")
      .addClass("bg-gray-200")
      .removeClass("bg-gray-700");
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

let currentFilters = {
  from: "",
  to: "",
  status: "SUCCESS",
  search: "",
  page: 1,
  limit: parseInt(localStorage.getItem("itemsPerPage") || "10"),
  sort: "createdAt:-1", // Sort newest first
};

function setupEventListeners() {
  $("#applyFilters").on("click", applyFilters);
  $("#resetFilters").on("click", resetFilters);
  $("#prevPage").on("click", () => changePage(currentFilters.page - 1));
  $("#nextPage").on("click", () => changePage(currentFilters.page + 1));
  $("#itemsPerPage").on("change", (e) => {
    const limit = parseInt($(e.target).val());
    localStorage.setItem("itemsPerPage", limit);
    currentFilters.limit = limit;
    currentFilters.page = 1;
    loadData();
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

async function loadData() {
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

    updateStats(response.stats);
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
  const selectedStatus = $("#transactionType").val();
  const searchQuery = $("#searchQuery").val().trim();

  console.log("Applying filters:", { fromDate, toDate, selectedStatus, searchQuery });

  currentFilters.page = 1;
  currentFilters.from = fromDate || "";
  currentFilters.to = toDate || "";
  currentFilters.search = searchQuery || "";

  if (selectedStatus === "REFUNDED") {
    currentFilters.status = "SUCCESS";
    currentFilters.refundOnly = "true";
  } else {
    currentFilters.status = selectedStatus || "SUCCESS";
    delete currentFilters.refundOnly;
  }

  currentFilters.sort = "createdAt:-1";

  loadData();
}
function resetFilters() {
  $("#fromDate").val("");
  $("#toDate").val("");
  $("#transactionType").val("SUCCESS");
  $("#searchQuery").val("");
  $("#itemsPerPage").val(localStorage.getItem("itemsPerPage") || "10");

  currentFilters = {
    from: "",
    to: "",
    status: "SUCCESS",
    search: "",
    page: 1,
    limit: parseInt(localStorage.getItem("itemsPerPage") || "10"),
    sort: "createdAt:-1",
  };

  delete currentFilters.refundOnly;  // This is correct

  console.log("All filters reset");
  loadData();
}

function updateStats(stats) {
  $("#statsCards").html(`
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

$(document).ready(function () {
  console.log("jQuery initialized for transaction table");

  function updateTable(transactions, total) {
    console.log("Updating table with", transactions.length, "transactions");

    $("#transactionTable").html(
      transactions.length > 0
        ? transactions
            .map((t, index) => {
              const originalAmount = parseFloat(t.amount || 0);
              const totalRefunded = t.latestTotalRefunded != null ? parseFloat(t.latestTotalRefunded) : 0;
              const isFullyRefunded = totalRefunded >= originalAmount;

              // Refund Tag
              let refundTag = "";
              if (totalRefunded > 0) {
                if (isFullyRefunded) {
                  refundTag = `<span class="inline-flex items-center gap-1.5 bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-full shadow-sm">
                    FULL REFUND
                  </span>`;
                } else {
                  refundTag = `<span class="inline-flex items-center gap-1.5 bg-orange-500 text-white text-xs font-bold px-3 py-1.5 rounded-full shadow-sm">
                    PARTIAL – ${totalRefunded.toFixed(2)} ${t.currency}
                  </span>`;
                }
              }

              const statusBadge = (() => {
                const s = (t.paymentStatus || "UNKNOWN").toUpperCase();
                switch (s) {
                  case "SUCCESS": return `<span class="inline-flex items-center gap-1.5 bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1.5 rounded-full">SUCCESS</span>`;
                  case "FAILED": case "ERROR": return `<span class="inline-flex items-center gap-1.5 bg-red-100 text-red-800 text-xs font-bold px-3 py-1.5 rounded-full">FAILED</span>`;
                  case "PENDING": return `<span class="inline-flex items-center gap-1.5 bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1.5 rounded-full">PENDING</span>`;
                  default: return `<span class="bg-gray-100 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-full">${s}</span>`;
                }
              })();

              const showRefundBtn = t.paymentStatus === "SUCCESS" && !isFullyRefunded;

              return `
<tr class="expandable-row group bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750 transition-all duration-200 shadow-sm" id="row-${index}">
  <td colspan="8" class="px-6 py-5">
    <!-- Main Row -->
    <div class="main-row grid grid-cols-8 gap-6 items-center text-gray-800 dark:text-gray-200">

      <div class="text-sm font-medium text-gray-600 dark:text-gray-400">
        ${new Date(t.createdAt).toISOString().split("T")[0]}
      </div>

      <div class="font-bold text-lg text-blue-600 dark:text-blue-400">
        ${t.orderId || "N/A"}
      </div>

      <div class="text-lg font-semibold text-gray-900 dark:text-white">
        ${originalAmount.toFixed(2)}
      </div>

      <div class="text-sm font-medium uppercase tracking-wider text-gray-600 dark:text-gray-400">
        ${t.currency || "LKR"}
      </div>

      <div class="truncate max-w-[200px] text-sm" title="${t.email || ''}">
        <span class="text-sm font-medium">${t.email || "N/A"}</span>
      </div>

      <div class="font-mono text-sm text-gray-600 dark:text-gray-400">
        ${t.cardNumber ? "•••• " + t.cardNumber.slice(-4) : "N/A"}
      </div>

      <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
        ${t.cardBrand || "N/A"}
      </div>

      <div class="flex justify-center">
        ${statusBadge}
      </div>

    </div>

    <!-- ONE ROW: Name, Description, Transaction ID + Refund Button on Right -->
    <div class="expand-content mt-4 p-5 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 rounded-xl border border-blue-200 dark:border-gray-700 shadow-inner hidden">
      
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-sm">

        <!-- Left: Details -->
        <div class="flex flex-wrap items-center gap-x-8 gap-y-3 text-gray-700 dark:text-gray-300">

          <div>
            <span class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Name on Card</span>
            <p class="font-semibold text-gray-900 dark:text-white">${t.nameOnCard || "N/A"}</p>
          </div>

          <div>
            <span class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Reference</span>
            <p class="font-medium text-gray-900 dark:text-white">${t.description || "N/A"}</p>
          </div>

          <div>
            <span class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Transaction ID</span>
            <p class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded mt-1 break-all" title="${t.transactionId || ''}">
              ${t.transactionId ? t.transactionId.slice(0, 10) + "..." + t.transactionId.slice(-8) : "N/A"}
            </p>
          </div>

          ${refundTag ? `<div class="mt-1">${refundTag}</div>` : ""}

        </div>

        <!-- Right: Refund Button (aligned to end) -->
        ${showRefundBtn ? `
          <button
            onclick="openRefundModal(
              '${(t.orderId || '').replace(/'/g, "\\'")}',
              '${(t.nameOnCard || "N/A").replace(/'/g, "\\'")}',
              '${t.cardNumber || "N/A"}',
              '${t.amount}',
              '${t.currency}',
              '${(t.email || "").replace(/'/g, "\\'")}',
              '${t.uuid || ""}'
            )"
            class="bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-bold py-2.5 px-6 rounded-lg shadow-lg transform hover:scale-105 transition-all duration-200 text-sm flex items-center gap-2 whitespace-nowrap"
          >
            Refund
          </button>
        ` : ""}

      </div>
    </div>
  </td>
</tr>
`;
            })
            .join("")
        : `<tr><td colspan="8" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400 text-lg">
              <div class="flex flex-col items-center gap-3">
                <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-2m3 2v-2m-9 7h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p>No successful transactions found</p>
              </div>
            </td></tr>`
    );

    // Pagination (clean & modern)
    const start = (currentFilters.page - 1) * currentFilters.limit + 1;
    const end = Math.min(start + currentFilters.limit - 1, total);
    $("#tableInfo").html(`<span class="text-sm text-gray-600 dark:text-gray-400">Showing <strong>${start}</strong> to <strong>${end}</strong> of <strong>${total}</strong> entries</span>`);

    const totalPages = Math.ceil(total / currentFilters.limit);
    $("#prevPage").prop("disabled", currentFilters.page === 1);
    $("#nextPage").prop("disabled", currentFilters.page === totalPages);

    const maxButtons = 5;
    const half = Math.floor(maxButtons / 2);
    let startPage = Math.max(1, currentFilters.page - half);
    let endPage = Math.min(totalPages, startPage + maxButtons - 1);
    if (endPage - startPage + 1 < maxButtons) startPage = Math.max(1, endPage - maxButtons + 1);

    let buttons = [];
    if (startPage > 1) buttons.push(`<span class="px-4 py-2 text-gray-500">...</span>`);
    for (let i = startPage; i <= endPage; i++) {
      buttons.push(`
        <button onclick="changePage(${i})"
          class="w-10 h-10 rounded-full font-medium transition-all ${i === currentFilters.page
            ? "bg-blue-600 text-white shadow-lg scale-110"
            : "bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-blue-100 dark:hover:bg-gray-600"}">
          ${i}
        </button>
      `);
    }
    if (endPage < totalPages) buttons.push(`<span class="px-4 py-2 text-gray-500">...</span>`);
    $("#pageButtons").html(buttons.join(""));

    // Click to expand
    $(".expandable-row")
      .off("click")
      .on("click", function (e) {
        if ($(e.target).closest("button").length) return;
        const index = this.id.split("-")[1];
        toggleRow(index);
      });
  }

  function toggleRow(index) {
    const $row = $(`#row-${index}`);
    const $content = $row.find(".expand-content");
    $(".expand-content").not($content).slideUp(300).addClass("hidden");

    if ($content.hasClass("hidden")) {
      $content.removeClass("hidden").slideDown(350);
      $row.addClass("ring-2 ring-blue-400 dark:ring-blue-600");
    } else {
      $content.slideUp(300, () => $content.addClass("hidden"));
      $row.removeClass("ring-2 ring-blue-400 dark:ring-blue-600");
    }
  }

  window.updateTable = updateTable;
  window.changePage = function (page) {
    if (page < 1) return;
    currentFilters.page = page;
    loadData();
  };
});
async function exportToCSV() {
  try {
    // Use the same endpoint as your table — it has correct latestTotalRefunded!
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(currentFilters)) {
      if (value) params.append(key, value);
    }
    params.set("status", "SUCCESS");
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

    // Rows — now 100% accurate
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

      return `"${date}","${t.merchantId || "N/A"}","${
        t.orderId || "N/A"
      }","${original}","${refunded}","${t.currency || "LKR"}","${
        t.email || "N/A"
      }","${t.description || "N/A"}","${t.cardBrand || "N/A"}","${
        t.nameOnCard || "N/A"
      }","${maskedCard}","SUCCESS"`;
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

    showToast(
      "CSV exported successfully with correct refund amounts!",
      "success"
    );
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
    errorEl.textContent = `Cannot refund more than ${refundData.originalAmount.toFixed(2)} ${refundData.currency}`;
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
  $("#confirmNoBtn").off("click").on("click", () => {
    $("#confirmRefundPopup").addClass("hidden").removeClass("flex");
    $("#refundModal").removeClass("hidden").addClass("flex"); // Show again
  });

  // Yes → submit refund
  $("#confirmYesBtn").off("click").on("click", async () => {
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
