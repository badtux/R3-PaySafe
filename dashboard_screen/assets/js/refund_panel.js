$(document).ready(function () {
  // Inject Refund Panel HTML
  const refundPanelHtml = `
    <div id="refundPanelOverlay" class="fixed inset-0 bg-black/40 hidden z-[60]">
      <div id="refundSidePanel" class="absolute left-0 top-0 h-full w-full sm:w-[75vw] bg-white shadow-2xl transform -translate-x-full transition-transform duration-300 flex flex-col">
        <div id="refundPanelResizeHandle" class="absolute right-0 top-0 h-full w-3 cursor-ew-resize bg-transparent hover:bg-blue-200/40 transition-colors flex items-center justify-center" title="Drag to resize">
          <div class="h-14 w-[3px] rounded-full bg-gray-300/80"></div>
        </div>
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
          <div>
            <h2 class="text-lg font-bold text-gray-800"><i class="fas fa-undo-alt text-red-500 mr-2"></i>Refunded Transactions</h2>
            <p class="text-xs text-gray-500">List of all full and partial refunds</p>
          </div>
          <button type="button" id="closeRefundPanel" class="px-3 py-2 rounded bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm">Close</button>
        </div>
        
        <div class="p-4 border-b border-gray-200">
           <input type="text" id="refundSearchInput" placeholder="Search Order ID" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-red-400 text-sm">
        </div>

        <div id="refundPanelBody" class="p-5 overflow-auto flex-1 bg-gray-50">
           <div class="text-center text-gray-500 m-8">Loading refund data...</div>
        </div>
      </div>
    </div>
  `;

  $('body').append(refundPanelHtml);



  // --- Resizable panel (persists user width) ---
  const WIDTH_STORAGE_KEY = 'refundPanelWidthPx';
  const MIN_W = 360;
  const MAX_W_RATIO = 0.95; 

  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  function applySavedWidthIfAny() {
    const saved = Number(localStorage.getItem(WIDTH_STORAGE_KEY));
    if (!saved || Number.isNaN(saved)) return;
    const maxW = Math.floor(window.innerWidth * MAX_W_RATIO);
    const w = clamp(saved, MIN_W, maxW);
    $('#refundSidePanel').css('width', `${w}px`);
  }

  applySavedWidthIfAny();

  let isResizing = false;
  let startX = 0;
  let startWidth = 0;

  function startResize(clientX) {
    const $panel = $('#refundSidePanel');
    isResizing = true;
    startX = clientX;
    startWidth = $panel.outerWidth();
    $('body').addClass('select-none');
  }

  function doResize(clientX) {
    if (!isResizing) return;
    const maxW = Math.floor(window.innerWidth * MAX_W_RATIO);
    const delta = clientX - startX;
    const next = clamp(startWidth + delta, MIN_W, maxW);
    $('#refundSidePanel').css('width', `${next}px`);
    localStorage.setItem(WIDTH_STORAGE_KEY, String(next));
  }

  function stopResize() {
    if (!isResizing) return;
    isResizing = false;
    $('body').removeClass('select-none');
  }

  // Mouse
  $(document).on('mousedown', '#refundPanelResizeHandle', function (e) {
    e.preventDefault();
    startResize(e.clientX);
  });

  $(document).on('mousemove', function (e) {
    doResize(e.clientX);
  });

  $(document).on('mouseup', function () {
    stopResize();
  });

  // Touch
  $(document).on('touchstart', '#refundPanelResizeHandle', function (e) {
    const t = e.touches && e.touches[0];
    if (!t) return;
    startResize(t.clientX);
  });

  $(document).on('touchmove', function (e) {
    const t = e.touches && e.touches[0];
    if (!t) return;
    doResize(t.clientX);
  });

  $(document).on('touchend touchcancel', function () {
    stopResize();
  });

  let currentRefundData = [];

  function closeRefundPanel() {
    $('#refundSidePanel').addClass('-translate-x-full');
    $('#refundPanelOverlay').addClass('hidden');
    $('body').removeClass('overflow-hidden');
  }

  $('#closeRefundPanel').on('click', closeRefundPanel);
  $('#refundPanelOverlay').on('click', function (e) {
    if (e.target && e.target.id === 'refundPanelOverlay') closeRefundPanel();
  });

  $(document).on('keydown', function (e) {
    if (e.key === 'Escape' && !$('#refundPanelOverlay').hasClass('hidden')) {
      closeRefundPanel();
    }
  });

  $('#openRefundDataBtn').on('click', function() {
    $('#refundPanelOverlay').removeClass('hidden');
    $('body').addClass('overflow-hidden');
  // Reuse dashboard toast/copy helpers if they exist; otherwise provide safe fallbacks.
  function showRefundToast(message, type = 'info') {
    if (typeof window.showToast === 'function') return window.showToast(message, type);
    const bg = type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-gray-600';
    const toast = $(`
      <div class="fixed bottom-4 right-4 px-4 py-2 text-white rounded shadow ${bg} opacity-0 transition-opacity duration-500 z-50">${message}</div>
    `);
    $('body').append(toast);
    setTimeout(() => toast.css('opacity', 1), 50);
    setTimeout(() => toast.fadeOut(500, () => toast.remove()), 3500);
  }

  function copyRefundValue(value, label = 'Value') {
    if (!value) return;
    if (typeof window.copyToClipboard === 'function') return window.copyToClipboard(value, label);
    navigator.clipboard
      .writeText(String(value))
      .then(() => showRefundToast(`${label} copied!`, 'success'))
      .catch(() => showRefundToast('Failed to copy', 'error'));
  }

  // Click-to-copy for ANY element rendered with .copy-on-click (same UX as dashboard table)
  $(document).off('click.refundCopy').on('click.refundCopy', '.copy-on-click', function (e) {
    const value = $(this).attr('data-copy') ?? $(this).text();
   // const label = $(this).attr('data-copy-label') || 'Copied to clipboard';
    copyRefundValue(value, label);
    e.stopPropagation();
  });
    requestAnimationFrame(() => {
      $('#refundSidePanel').removeClass('-translate-x-full');
    });
    fetchRefunds();
  });

  $('#refundSearchInput').on('input', function() {
     renderRefunds($(this).val().trim().toLowerCase());
  });

  async function fetchRefunds() {
    try {
      const url = `${BASE_URL}/payments?status=SUCCESS&refundOnly=true&limit=10000`;
      
      const response = await $.ajax({
        url: url,
        method: "GET",
      });
      
      currentRefundData = response.transactions || [];
      renderRefunds();

    } catch (err) {
      console.error("Failed to load refunds:", err);
      $('#refundPanelBody').html('<div class="text-red-500 text-center mt-5">Failed to fetch refund data</div>');
    }
  }

  function renderRefunds(filter = '') {
     const container = $('#refundPanelBody');
     let list = currentRefundData;

     // Sort latest refunds first (newest -> oldest)
     const getLatestRefundTs = (t) => {
       // Prefer timestamp from latestRefundId's node if we can find it
       if (t && t.latestRefundId) {
         const key = Object.keys(t).find((k) => k.startsWith('refund-') && t[k] && t[k].refundTransactionId === t.latestRefundId);
         if (key && t[key] && t[key].refundDate) return new Date(t[key].refundDate).getTime();
       }

       // Fallback: max refundDate among refund-* nodes
       let max = 0;
       for (const k of Object.keys(t || {})) {
         if (!k.startsWith('refund-')) continue;
         const rd = t[k]?.refundDate;
         const ts = rd ? new Date(rd).getTime() : 0;
         if (ts > max) max = ts;
       }
       // Final fallback: updatedAt/createdAt
       if (!max && t?.updatedAt) return new Date(t.updatedAt).getTime();
       if (!max && t?.createdAt) return new Date(t.createdAt).getTime();
       return max;
     };

     list = [...list].sort((a, b) => getLatestRefundTs(b) - getLatestRefundTs(a));
     
     if(filter) {
        list = list.filter(t => 
           (t.orderId && t.orderId.toLowerCase().includes(filter)) ||
           (t.email && t.email.toLowerCase().includes(filter)) ||
           (t.latestTotalRefunded && t.latestTotalRefunded.toString().includes(filter))
        );
     }

     if (list.length === 0) {
        container.html('<div class="text-gray-500 text-center mt-8">No refunded transactions found.</div>');
        return;
     }

     const html = list.map(t => {
        const totalRefunded = parseFloat(t.latestTotalRefunded || 0);
        const originalAmount = parseFloat(t.amount || 0);
        
        let tagsHtml = '';
        if (totalRefunded >= originalAmount) {
             tagsHtml = `<span class="bg-red-100 text-red-700 text-xs px-2 py-1 rounded font-bold">FULL REFUND</span>`;
        } else {
             tagsHtml = `<span class="bg-orange-100 text-orange-700 text-xs px-2 py-1 rounded font-bold">PARTIAL</span>`;
        }

        // Loop dynamic refund nodes (refund-1, refund-2 etc)
        const refundHistoryHtml = Object.keys(t)
            .filter(key => key.startsWith('refund-'))
            .map(rKey => {
                const r = t[rKey];
                const date = r.refundDate ? new Date(r.refundDate).toLocaleString() : 'N/A';
               return `
                 <div class="border-t border-gray-100 mt-2 pt-2 text-xs">
                   <div class="flex justify-between">
                     <span class="text-gray-500 copy-on-click" data-copy="${date}" data-copy-label="Refund date" title="Click to copy">${date}</span>
                     <span class="font-semibold text-gray-700 copy-on-click" data-copy="${r.refundAmount}" data-copy-label="Refund amount" title="Click to copy">${parseFloat(r.refundAmount).toFixed(2)} ${r.refundCurrency || 'LKR'}</span>
                   </div>
                  <div class="text-gray-600 break-all">Refund ID: <span class="copy-on-click font-semibold text-gray-900 text-sm" data-copy="${r.refundTransactionId || ''}" data-copy-label="Refund ID" title="Click to copy">${r.refundTransactionId || 'N/A'}</span></div>
                 </div>
               `;
            }).join('');


        return `
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-3">
               <div class="flex justify-between items-start mb-2">
                  <div class="flex gap-2 items-center">
                        <span class="font-bold text-blue-600 copy-on-click" data-copy="${t.orderId || ''}" data-copy-label="Order ID" title="Click to copy">${t.orderId}</span>
                     ${tagsHtml}
                  </div>
              <span class="font-bold text-gray-800 copy-on-click" data-copy="${totalRefunded.toFixed(2)}" data-copy-label="Refund total" title="Click to copy">${totalRefunded.toFixed(2)} / ${originalAmount.toFixed(2)} ${t.currency}</span>                    
               </div>
               
              <div class="text-sm text-gray-600 mb-1"><i class="fas fa-envelope mr-1 text-gray-400"></i> <span class="copy-on-click" data-copy="${t.email || ''}" data-copy-label="Email" title="Click to copy">${t.email || 'N/A'}</span></div>
               <div class="text-sm text-gray-600 mb-3"><i class="fas fa-credit-card mr-1 text-gray-400"></i> ${t.cardNumber ? '****' + t.cardNumber.slice(-4) : 'N/A'} (${t.cardBrand || 'N/A'})</div>
               
              <div class="bg-gray-50 p-2 rounded border border-gray-100">
                <span class="text-xs font-semibold text-gray-500 uppercase">Refund Trace</span>
                ${refundHistoryHtml}
              </div>
            </div>
        `;
     }).join('');

     container.html(html);
  }
});
