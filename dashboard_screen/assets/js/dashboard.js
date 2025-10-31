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
    for (const [key, value] of Object.entries(currentFilters)) {
      if (value !== undefined && value !== "") {
        params.append(key, value);
      }
    }
    params.set("status", "SUCCESS");
    params.set("sort", "createdAt:-1"); // Ensure sort is always applied
    const url = `${BASE_URL}/payments?${params}`;
    console.log("Fetching data from:", url);
    const response = await $.ajax({
      url: url,
      method: "GET",
    });
    console.log("Fetched data:", response);
    updateStats(response.stats);
    updateTable(response.transactions, response.total);
  } catch (error) {
    console.error("Error fetching data:", error);
    $("#transactionTable").html(`
          <tr><td colspan="11" class="px-5 py-4 text-center text-red-500">Failed to load transactions. Please try again later.</td></tr>
        `);
  }
}

function applyFilters() {
  const fromDate = $("#fromDate").val();
  const toDate = $("#toDate").val();
  const searchQuery = $("#searchQuery").val().toLowerCase();
  console.log("Applying filters:", {
    fromDate,
    toDate,
    status: "SUCCESS",
    searchQuery,
    sort: "createdAt:-1",
  });
  currentFilters.from = fromDate || "";
  currentFilters.to = toDate || "";
  currentFilters.status = "SUCCESS";
  currentFilters.search = searchQuery || "";
  currentFilters.sort = "createdAt:-1";
  currentFilters.page = 1;
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
  console.log("Filters reset");
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
              const transactionId = t.transactionId || "N/A";
              const displayId = transactionId.toUpperCase();

              return `
<tr class="expandable-row cursor-pointer hover:bg-gray-400" id="row-${index}">
    <td colspan="8" class="px-2 py-4">
      <div class="main-row grid grid-cols-8 gap-4">
        <span class="flex items-center justify-start text-[clamp(6px,2vw,15px)]  break-words">
          ${new Date(t.createdAt).toISOString().split("T")[0]}
        </span>
        <span class="flex items-center justify-start text-[clamp(6px,2vw,15px)] break-words">
          ${t.orderId || "N/A"}
        </span>
        <span class="flex items-center justify-start text-[clamp(6px,2vw,15px)] break-words">
          ${parseFloat(t.amount || 0).toFixed(2)}
        </span>
        <span class="flex items-center justify-start text-[clamp(6px,2vw,15px)]  break-words">
          ${t.currency || "N/A"}
        </span>
        <span class="flex items-center justify-start break-words  text-[clamp(6px,2vw,15px)] truncate max-w-[220px]" title="${
          t.email || "N/A"
        }">
          ${t.email || "N/A"}
        </span>
        <span class="flex items-center justify-start break-words text-[clamp(6px,2vw,15px)] truncate max-w-[200px]" title="${
          t.description || "N/A"
        }">
         
              ${t.cardNumber ? "  **** " + t.cardNumber.slice(-4) : "N/A"}
        </span>
        <span class="flex items-center justify-start text-[clamp(6px,2vw,15px)] break-words">
          ${t.cardBrand || "N/A"}
        </span>
        <span class="flex items-center justify-start text-[clamp(6px,2vw,15px)]  break-words">
          ${(() => {
            const status = t.paymentStatus?.toUpperCase() || "N/A";
            let colorClass = "";

            switch (status) {
              case "SUCCESS":
                colorClass = "bg-green-500 text-[clamp(6px,2vw,15px)] ";
                break;
              case "FAILED":
              case "ERROR":
                colorClass = "bg-red-100 text-[clamp(6px,2vw,15px)]  text-red-800";
                break;
              case "PENDING":
                colorClass = "bg-yellow-100 text-[clamp(6px,2vw,15px)]  text-yellow-800";
                break;
              default:
                colorClass = "bg-gray-100 text-[clamp(6px,2vw,15px)] text-gray-800";
            }

            return `<span class="${colorClass}text-[clamp(6px,2vw,14px)]  px-2.5 py-0.5 rounded">${status}</span>`;
          })()}
        </span>
      </div>

      <div class="expand-content mt-2 text-sm text-gray-700 hidden">
        <div class="flex flex-row gap-5">
          <span class="flex items-start justify-start">
            <strong>Name on Card :</strong> ${t.nameOnCard || "N/A"}
          </span>
          <span class="flex items-start justify-start">
            <strong>Refference :</strong>  ${t.description || "N/A"}
          </span>
          <span class="flex items-start justify-start">
            <strong>Transaction id  :</strong>
            <span title="${t.transactionId || "N/A"}">
  ${
    t.transactionId
      ? t.transactionId.slice(0, 4) + "..." + t.transactionId.slice(-4)
      : "N/A"
  }
</span>

          </span>
        </div>
      </div>
    </td>
  </tr>
`;
            })
            .join("")
        : `<tr><td colspan="8"  text-[clamp(6px,2vw,15px)] class="px-5 py-4 text-center">No successful transactions found</td></tr>`
    );

    const start = (currentFilters.page - 1) * currentFilters.limit + 1;
    const end = Math.min(start + currentFilters.limit - 1, total);
    $("#tableInfo").text(`Showing ${start} to ${end} of ${total} entries`);

    const totalPages = Math.ceil(total / currentFilters.limit);
    $("#prevPage").prop("disabled", currentFilters.page === 1);
    $("#nextPage").prop("disabled", currentFilters.page === totalPages);

    const maxButtons = 5;
    const halfButtons = Math.floor(maxButtons / 2);
    let startPage = Math.max(1, currentFilters.page - halfButtons);
    let endPage = Math.min(totalPages, startPage + maxButtons - 1);

    if (endPage === totalPages) {
      startPage = Math.max(1, endPage - maxButtons + 1);
    }

    const pageButtons = [];

    if (startPage > 2) {
      pageButtons.push(`
        <span class="flex items-center justify-center px-3 py-1.5 text-sm text-gray-700">...</span>
      `);
    }

    for (let i = startPage; i <= endPage; i++) {
      pageButtons.push(`
        <button class="flex items-center justify-center px-3 py-1.5 text-sm ${
          currentFilters.page === i
            ? "bg-primary text-white"
            : "bg-gray-100 text-gray-700"
        } rounded-md border border-${
        currentFilters.page === i ? "primary" : "gray-300"
      } hover:bg-gray-200" onclick="changePage(${i})">${i}</button>
      `);
    }

    if (endPage < totalPages) {
      pageButtons.push(`
        <span class="flex items-center justify-center px-3 py-1.5 text-sm text-gray-700">...</span>
      `);
    }

    $("#pageButtons").html(pageButtons.join(""));

    $(".expandable-row")
      .off("click")
      .on("click", function () {
        const index = $(this).attr("id").split("-")[1];
        toggleRow(index);
      });
  }

  function toggleRow(index) {
    const $row = $(`#row-${index}`);
    const $expandContent = $row.find(".expand-content");

    $(".expand-content").not($expandContent).slideUp(200).addClass("hidden");

    if ($expandContent.hasClass("hidden")) {
      $expandContent.removeClass("hidden").slideDown(200);
    } else {
      $expandContent.slideUp(200, function () {
        $expandContent.addClass("hidden");
      });
    }
  }

  window.updateTable = updateTable;

  window.changePage = function (page) {
    if (page < 1) return;
    currentFilters.page = page;
    loadData();
  };
});

function changePage(page) {
  if (page < 1) return;
  currentFilters.page = page;
  loadData();
}

async function exportToCSV() {
  try {
    const params = new URLSearchParams();
    ["from", "to", "search", "sort"].forEach((key) => {
      const value = currentFilters[key];
      if (value !== undefined && value !== "") {
        params.append(key, value);
      }
    });
    params.append("status", "SUCCESS");
    const url = `${BASE_URL}/payments/export?${params}`;
    console.log("Exporting data from:", url);
    const response = await $.ajax({
      url: url,
      method: "GET",
    });
    const headers = [
      "Created At,Merchant ID,Order ID,Amount,Currency,Email,Description,Card Brand,Name on Card,Payment Status",
    ];
    const rows = response.map(
      (t) =>
        `"${new Date(t.createdAt).toISOString().split("T")[0]}","${
          t.merchantId || "N/A"
        }","${t.orderId || "N/A"}",${parseFloat(t.amount || 0).toFixed(2)},"${
          t.currency || "N/A"
        }","${t.email || "N/A"}","${t.description || "N/A"}","${
          t.cardBrand || "N/A"
        }","${t.nameOnCard || "N/A"}","SUCCESS"`
    );
    const csv = [...headers, ...rows].join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const urlObj = URL.createObjectURL(blob);
    const $a = $("<a>", {
      href: urlObj,
      download: "successful_transactions.csv",
    }).appendTo("body");
    $a[0].click();
    URL.revokeObjectURL(urlObj);
    $a.remove();
  } catch (error) {
    console.error("Error exporting data:", error);
    alert("Failed to export data. Please try again.");
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
    <div class="fixed bottom-4 right-4 px-4 py-2 text-white rounded shadow ${bg} opacity-0 transition-opacity duration-500">
      ${message}
    </div>
  `);
  $("body").append(toast);
  setTimeout(() => toast.css("opacity", 1), 50);
  setTimeout(() => toast.fadeOut(500, () => toast.remove()), 3000);
}

let refundData = {};

function openRefundModal(
  orderId,
  nameOnCard,
  cardNumber,
  originalAmount,
  currency
) {
  refundData = { orderId, originalAmount, currency };

  $("#modalOrderId").text(orderId);
  $("#modalNameOnCard").text(nameOnCard);
  $("#modalCardNumber").text(maskCard(cardNumber));
  $("#modalOriginalAmount").text(
    `${parseFloat(originalAmount).toFixed(2)} ${currency}`
  );
  $("#refundAmountInput").val("");
  $("#amountError").addClass("hidden");

  $("#refundModal").removeClass("hidden");
}

function closeRefundModal() {
  $("#refundModal").addClass("hidden");
}

function maskCard(card) {
  if (!card || card === "N/A") return "N/A";
  const clean = card.replace(/\s/g, "");
  return clean.replace(/\d{12}(\d{4})/, "**** **** **** $1");
}

function submitRefund() {
  const amount = parseFloat($("#refundAmountInput").val());
  const errorEl = $("#amountError");

  if (isNaN(amount) || amount <= 0) {
    errorEl.text("Please enter a valid amount").removeClass("hidden");
    return;
  }

  if (amount > refundData.originalAmount) {
    errorEl
      .text(`Cannot refund more than ${refundData.originalAmount.toFixed(2)}`)
      .removeClass("hidden");
    return;
  }

  errorEl.addClass("hidden");

  $.ajax({
    url: `${BASE_URL}/refund`,
    method: "POST",
    contentType: "application/json",
    data: JSON.stringify({
      orderId: refundData.orderId,
      amount: amount,
      currency: refundData.currency,
    }),
    success: function (result) {
      if (result.success) {
        alert(`Refund successful! ID: ${result.refundId}`);
        closeRefundModal();
        location.reload(); // Refresh the table
      } else {
        alert(`Refund failed: ${result.error}`);
      }
    },
    error: function (xhr, status, error) {
      alert("Network error. Try again.");
      console.error(error);
    },
  });
}

$("#footerText").text("© 2025 Digitable.IO Plutos");

//         <span class="flex items-center justify-start break-words">
//   ${
//     t.paymentStatus === "SUCCESS"
//       ? `
//     <button
//       onclick="openRefundModal('${t.orderId}', '${t.nameOnCard || "N/A"}', '${
//           t.cardNumber || "N/A"
//         }', ${t.amount}, '${t.currency}')"
//       class="bg-red-600 hover:bg-red-700 text-white text-xs font-medium px-3 py-1 rounded"
//     >
//       Refund
//     </button>
//   `
//       : ""
//   }
// </span>
