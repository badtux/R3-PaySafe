  const { getPaymentCollection } = require('../models/Payment');
  const { getGatewayCredentials } = require('./refund.setting.services');
  const  refundService = require('./refund.service');


function extractTenant(hostname) {
    const tenant = hostname.split('.')[0];
    if (!tenant || tenant === 'localhost' || tenant === 'go') {
        throw new Error('Invalid tenant');
    }
    return tenant;
}

async function checkHealth(hostname) {
    const tenant = extractTenant(hostname);
    const collection = await getPaymentCollection(tenant);

    const collections = await collection.db.listCollections().toArray();
    const collectionExists = collections.some(c => c.name === 'payments');
    const documentCount = collectionExists ? await collection.countDocuments() : 0;

    return {
        status: 'ok',
        connected: true,
        database: `${tenant}_paysafe`,
        collection: 'payments',
        collectionExists,
        documentCount
    };
}

async function fetchPayments(hostname, query) {
  const tenant = extractTenant(hostname);
  const {
    from,
    to,
    status,
    search,
    showRefundOnly, // <-- frontend flag
    page = 1,
    limit = 10,
    sort = "createdAt:-1",
  } = query;

  const collection = await getPaymentCollection(tenant);
  const skip = (parseInt(page) - 1) * parseInt(limit);

  // Base filter (for both modes)
  let baseFilter = {};

  if (from || to) {
    baseFilter.createdAt = {};
    if (from) baseFilter.createdAt.$gte = new Date(from);
    if (to) {
      const toDate = new Date(to);
      toDate.setHours(23, 59, 59, 999);
      baseFilter.createdAt.$lte = toDate;
    }
  }

  if (status) baseFilter.paymentStatus = status;

  if (search) {
    baseFilter.$or = [
      { orderId: { $regex: search, $options: "i" } },
      { email: { $regex: search, $options: "i" } },
      { description: { $regex: search, $options: "i" } },
    ];
  }

  // Sort option
  let sortOption = { createdAt: -1 };
  if (sort) {
    const [field, dir] = sort.split(":");
    if (field && ["1", "-1"].includes(dir)) {
      sortOption = { [field]: parseInt(dir) };
    }
  }

  let transactions = [];
  let total = 0;

  // ✅ Case 1: Refund-only mode
  if (showRefundOnly === "true") {
    const pipeline = [
      { $match: baseFilter },
      {
        $addFields: {
          refundCount: {
            $size: {
              $filter: {
                input: { $objectToArray: "$$ROOT" },
                as: "field",
                cond: { $regexMatch: { input: "$$field.k", regex: /^refund-/ } },
              },
            },
          },
        },
      },
      { $match: { refundCount: { $gt: 0 } } },
      { $sort: sortOption },
      { $skip: skip },
      { $limit: parseInt(limit) },
    ];

    transactions = await collection.aggregate(pipeline).toArray();
    total = (
      await collection
        .aggregate([
          { $match: baseFilter },
          {
            $addFields: {
              refundCount: {
                $size: {
                  $filter: {
                    input: { $objectToArray: "$$ROOT" },
                    as: "field",
                    cond: { $regexMatch: { input: "$$field.k", regex: /^refund-/ } },
                  },
                },
              },
            },
          },
          { $match: { refundCount: { $gt: 0 } } },
          { $count: "total" },
        ])
        .toArray()
    )[0]?.total || 0;
  }

  // ✅ Case 2: Normal mode (all transactions)
  else {
    transactions = await collection
      .find(baseFilter)
      .sort(sortOption)
      .skip(skip)
      .limit(parseInt(limit))
      .toArray();

    total = await collection.countDocuments(baseFilter);
  }

  // Enrich refund details
  const enriched = transactions.map((doc) => {
    const refundKeys = Object.keys(doc).filter((k) => /^refund-\d+$/.test(k));
    let latestRefund = null;
    if (refundKeys.length) {
      const lastKey = refundKeys
        .map((k) => ({ key: k, idx: Number(k.split("-")[1]) }))
        .sort((a, b) => b.idx - a.idx)[0].key;
      latestRefund = doc[lastKey];
    }

    return {
      ...doc,
      latestTotalRefunded: latestRefund?.totalRefundedAmount ?? 0,
      latestRefundId: latestRefund?.refundTransactionId ?? null,
    };
  });

  // Currency stats
  const totalLKR = (
    await collection
      .aggregate([
        { $match: { ...baseFilter, currency: "LKR" } },
        { $group: { _id: null, total: { $sum: "$amount" } } },
      ])
      .toArray()
  )[0]?.total || 0;

  const totalUSD = (
    await collection
      .aggregate([
        { $match: { ...baseFilter, currency: "USD" } },
        { $group: { _id: null, total: { $sum: "$amount" } } },
      ])
      .toArray()
  )[0]?.total || 0;

  const successful = await collection.countDocuments({
    ...baseFilter,
    paymentStatus: "SUCCESS",
  });

  const stats = {
    totalTransactions: total,
    successfulTransactions: successful,
    totalAmountLKR: totalLKR.toFixed(2),
    totalAmountUSD: totalUSD.toFixed(2),
  };

  return { transactions: enriched, total, stats };
}


async function exportPayments(hostname, query) {
    const tenant = extractTenant(hostname);
    const { from, to, status, search, sort = "createdAt:-1" } = query;

    let filter = {};

    if (from || to) {
        filter.createdAt = {};
        if (from) filter.createdAt.$gte = new Date(from);
        if (to) {
            const toDate = new Date(to);
            toDate.setHours(23, 59, 59, 999);
            filter.createdAt.$lte = toDate;
        }
    }
    if (status) filter.paymentStatus = status;
    if (search) {
        filter.$or = [
            { orderId: { $regex: search, $options: 'i' } },
            { email: { $regex: search, $options: 'i' } },
            { description: { $regex: search, $options: 'i' } }
        ];
    }

    const collection = await getPaymentCollection(tenant);
    let queryBuilder = collection.find(filter);
    if (sort) {
        const [field, direction] = sort.split(':');
        if (field && ['1', '-1'].includes(direction)) {
            queryBuilder = queryBuilder.sort({ [field]: parseInt(direction) });
        } else {
            queryBuilder = queryBuilder.sort({ createdAt: -1 });
        }
    }
    const transactions = await queryBuilder.toArray();
    return transactions;
}


async function refundPayment_services(hostname, { orderId, amount, currency }) {
  const tenant = extractTenant(hostname);
  const collection = await getPaymentCollection(tenant);

  if (!orderId || !amount || !currency) {
    throw new Error('Missing orderId, amount, or currency');
  }

  const refundAmount = parseFloat(amount);
  if (isNaN(refundAmount) || refundAmount <= 0) {
    throw new Error('Invalid refund amount');
  }

  const curr = currency.toUpperCase();
  orderId = orderId.trim();

  const originalPayment = await collection.findOne({ orderId });
  if (!originalPayment) throw new Error(`Order ${orderId} not found`);

  if (originalPayment.currency !== curr) {
    throw new Error(`Currency mismatch: ${originalPayment.currency} vs ${curr}`);
  }

  const originalAmount = parseFloat(originalPayment.amount);
  if (refundAmount > originalAmount) {
    throw new Error(`Refund (${refundAmount}) > original (${originalAmount})`);
  }

  const credentials = getGatewayCredentials(curr);
  if (!credentials) throw new Error(`No gateway for ${curr}`);

  let gatewayResult;
  try {
    gatewayResult = await refundService.processRefund(orderId, refundAmount, curr);
  } catch (err) {
    console.error("Refund gateway call failed:", err);
    throw new Error(err.message || "Refund request failed (network/gateway error)");
  }

  // 🟢 Extract refunded amount from gateway result
  const totalRefundedAmount = gatewayResult.raw?.order?.totalRefundedAmount || 0;

  if (!gatewayResult.success) {
    const gatewayError = gatewayResult.error || {};
    const errMsg = gatewayError.explanation ||
                   gatewayError.cause ||
                   gatewayError.message ||
                   'Refund failed (gateway error)';

    console.error(`Refund failed for ${orderId}: ${errMsg}`);
    const errorResponse = new Error(errMsg);
    errorResponse.gateway = gatewayError;
    throw errorResponse;
  }

  // 🟢 Record refund entry in DB
  const refundEntry = {
    refundAmount,
    refundCurrency: curr,
    totalRefundedAmount,
    refundStatus: 'REFUNDED',
    refundTransactionId: gatewayResult.transactionId || null,
    refundResponse: [
      {
        success: true,
        gatewayCode: gatewayResult.gatewayCode || null,
        result: gatewayResult.result || null,
        transactionId: gatewayResult.transactionId || null,
        httpCode: gatewayResult.httpCode || null,
        error: null,
        timestamp: new Date().toISOString(),
      },
    ],
    refundDate: new Date(),
  };

  const existingKeys = Object.keys(originalPayment).filter(k => /^refund-\d+$/.test(k));
  const nextIdx = existingKeys.length
    ? Math.max(...existingKeys.map(k => Number(k.split('-')[1]))) + 1
    : 1;
  const nextField = `refund-${nextIdx}`;

  await collection.updateOne(
    { orderId },
    {
      $set: {
        [nextField]: refundEntry,
        updatedAt: new Date(),
      },
    }
  );
  return {
    success: true,
    message: 'Refund successful',
    orderId,
    originalAmount,
    refundAmount,
    totalRefundedAmount, 
    currency: curr,
    transactionId: gatewayResult.transactionId,
    refundId: gatewayResult.transactionId,
    gatewayCode: gatewayResult.gatewayCode,
    result: gatewayResult.result,
    // gatewayResponse: gatewayResult,
  };
}
module.exports = {
    checkHealth,
    fetchPayments,
    exportPayments,
    refundPayment_services
};