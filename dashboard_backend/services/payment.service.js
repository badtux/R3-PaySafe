const { getPaymentCollection } = require("../models/Payment");
const { getGatewayCredentials } = require("./refund.setting.services");
const refundService = require("./refund.service");

function extractTenant(hostname) {
  const tenant = hostname.split(".")[0];
  if (!tenant || tenant === "localhost" || tenant === "go") {
    throw new Error("Invalid tenant");
  }
  return tenant;
}

async function checkHealth(hostname) {
  const tenant = extractTenant(hostname);
  const collection = await getPaymentCollection(tenant);

  const collections = await collection.db.listCollections().toArray();
  const collectionExists = collections.some((c) => c.name === "payments");
  const documentCount = collectionExists
    ? await collection.countDocuments()
    : 0;

  return {
    status: "ok",
    connected: true,
    database: `${tenant}_paysafe`,
    collection: "payments",
    collectionExists,
    documentCount,
  };
}

async function fetchPayments(hostname, query) {
  const tenant = extractTenant(hostname);
  const {
    from,
    to,
    status,
    search,
    refundOnly,     // Can be "true" or ["true", "true"]
    page = 1,
    limit = 10,
    sort = "createdAt:-1",
  } = query;

  console.log('Backend query:', query);

  const collection = await getPaymentCollection(tenant);
  const skip = (parseInt(page) - 1) * parseInt(limit);

  // FIXED: Handle refundOnly as string OR array
  const isRefundOnly = refundOnly && (
    refundOnly === "true" ||
    (Array.isArray(refundOnly) && refundOnly.includes("true"))
  );

  const normalizedStatus = typeof status === 'string' ? status.toUpperCase() : undefined;

  // Base filter
  let baseFilter = {
    // Only include docs that actually have a paymentStatus
    paymentStatus: { $exists: true, $ne: null },

    // Only show docs with a real email
    email: { $exists: true, $ne: null, $ne: "" },
  };

  // Apply cron:true restriction only for malkey tenant
  if (String(tenant).toLowerCase() === 'malkey') {
    baseFilter.cron = true;
  }

  if (from || to) {
    baseFilter.createdAt = {};
    if (from) baseFilter.createdAt.$gte = new Date(from);
    if (to) {
      const toDate = new Date(to);
      toDate.setHours(23, 59, 59, 999);
      baseFilter.createdAt.$lte = toDate;
    }
  }

  // If status=ALL (or empty), don't filter by paymentStatus value (but still require it exists)
  if (normalizedStatus && normalizedStatus !== 'ALL') {
    if (normalizedStatus === 'FAILED') {
      // Group FAILED to include gateway/processor error states (and legacy FAIL)
      baseFilter.paymentStatus = { $in: ['FAILED', 'FAIL', 'ERROR'] };
    } else if (normalizedStatus === 'REFUNDED') {
      // Safety: UI uses SUCCESS+refundOnly for refunded, but if a client sends REFUNDED,
      // treat it as SUCCESS and refundOnly.
      baseFilter.paymentStatus = 'SUCCESS';
    } else {
      baseFilter.paymentStatus = normalizedStatus;
    }
  }

  if (search) {
    baseFilter.$or = [
      { orderId: { $regex: search, $options: "i" } },
      { email: { $regex: search, $options: "i" } },
      { description: { $regex: search, $options: "i" } },
    ];
  }

  // Make a dedicated stats filter.
  const statsFilter = { ...baseFilter };

  // Amount stats should be computed only for SUCCESS payments
  const amountStatsFilter = { ...statsFilter, paymentStatus: "SUCCESS" };

  // FIXED: Correct syntax for dynamic sort
  let sortOption = { createdAt: -1 };
  if (sort && sort.includes(":")) {
    const [field, dir] = sort.split(":");
    if (field && ["1", "-1"].includes(dir)) {
      sortOption = { [field]: parseInt(dir) };
    }
  }

  // Always de-duplicate by orderId: pick latest doc per orderId.
  // We sort by createdAt desc and _id desc so "latest" is stable even if createdAt ties.
  const latestPerOrderPipelineBase = [
    { $match: baseFilter },
    { $sort: { createdAt: -1, _id: -1 } },
  ];
  const addRefundCountStage = {
    $addFields: {
      refundCount: {
        $size: {
          $filter: {
            input: { $objectToArray: "$$ROOT" },
            as: "field",
            cond: { $regexMatch: { input: "$$field.k", regex: /^refund-\d+$/ } },
          },
        },
      },
    },
  };
  const groupLatestPerOrderStage = {
    $group: {
      _id: "$orderId",
      doc: { $first: "$$ROOT" },
    },
  };

  const replaceRootStage = { $replaceRoot: { newRoot: "$doc" } };

  // Stats should be computed on the same latest-per-orderId dataset as the table.
  const countLatestPerOrder = async (extraMatch = null) => {
    const pipeline = [
      ...latestPerOrderPipelineBase,
      groupLatestPerOrderStage,
      replaceRootStage,
    ];
    if (extraMatch) pipeline.push({ $match: extraMatch });
    pipeline.push({ $count: 'total' });
    return (await collection.aggregate(pipeline).toArray())[0]?.total || 0;
  };

  let transactions = [];
  let total = 0;

  if (isRefundOnly) {
    console.log('REFUND-ONLY MODE ON');

    const pipeline = [
      ...latestPerOrderPipelineBase,
      groupLatestPerOrderStage,
      replaceRootStage,
      addRefundCountStage,
      { $match: { refundCount: { $gt: 0 } } },
      { $sort: sortOption },
      { $skip: skip },
      { $limit: parseInt(limit) },
    ];

    transactions = await collection.aggregate(pipeline).toArray();

    total = (await collection
      .aggregate([
        ...latestPerOrderPipelineBase,
        groupLatestPerOrderStage,
        replaceRootStage,
        addRefundCountStage,
        { $match: { refundCount: { $gt: 0 } } },
        { $count: "total" },
      ])
      .toArray())[0]?.total || 0;
  } else {
    const pipeline = [
      ...latestPerOrderPipelineBase,
      groupLatestPerOrderStage,
      replaceRootStage,
      { $sort: sortOption },
      { $skip: skip },
      { $limit: parseInt(limit) },
    ];

    transactions = await collection.aggregate(pipeline).toArray();

    total = (await collection
      .aggregate([
        ...latestPerOrderPipelineBase,
        groupLatestPerOrderStage,
        { $count: "total" },
      ])
      .toArray())[0]?.total || 0;
  }

  // Enrich with latest refund
  const enriched = transactions.map((doc) => {
    const refundKeys = Object.keys(doc).filter((k) => /^refund-\d+$/.test(k));
    let latestRefund = null;

    if (refundKeys.length > 0) {
      const latestKey = refundKeys
        .map((k) => ({ key: k, num: Number(k.split("-")[1]) }))
        .sort((a, b) => b.num - a.num)[0].key;
      latestRefund = doc[latestKey];
    }

    return {
      ...doc,
      // ensure attempts is always returned as an array
      attempts: Array.isArray(doc.attempts) ? doc.attempts : [],
      latestTotalRefunded: latestRefund?.totalRefundedAmount ?? 0,
      latestRefundId: latestRefund?.refundTransactionId ?? null,
    };
  });

  // Stats: sum amounts correctly even if stored as strings
  const sumAmountPipeline = (filter, currency) => ([
    { $match: { ...filter, currency } },
    {
      $group: {
        _id: null,
        total: {
          $sum: {
            $convert: {
              input: "$amount",
              to: "double",
              onError: 0,
              onNull: 0
            }
          }
        }
      }
    }
  ]);

  const totalLKR = (await collection.aggregate(sumAmountPipeline(amountStatsFilter, "LKR")).toArray())[0]?.total || 0;
  const totalUSD = (await collection.aggregate(sumAmountPipeline(amountStatsFilter, "USD")).toArray())[0]?.total || 0;

  // This-month stats (same filters + createdAt in current month)
  const monthStart = new Date();
  monthStart.setDate(1);
  monthStart.setHours(0, 0, 0, 0);
  const now = new Date();

  const thisMonthFilter = {
    ...statsFilter,
    createdAt: {
      ...(statsFilter.createdAt || {}),
      $gte: monthStart,
      $lte: now,
    },
  };

  // This-month amount stats should be computed only for SUCCESS payments
  const thisMonthAmountFilter = {
    ...thisMonthFilter,
    paymentStatus: "SUCCESS",
  };

  // Use de-duplicated counting for these stats
  const thisMonthCount = await (async () => {
    const pipeline = [
      { $match: thisMonthFilter },
      { $sort: { createdAt: -1, _id: -1 } },
      groupLatestPerOrderStage,
      { $count: 'total' },
    ];
    return (await collection.aggregate(pipeline).toArray())[0]?.total || 0;
  })();

  const thisMonthAmountLKR = (await collection.aggregate(sumAmountPipeline(thisMonthAmountFilter, "LKR")).toArray())[0]?.total || 0;
  const thisMonthAmountUSD = (await collection.aggregate(sumAmountPipeline(thisMonthAmountFilter, "USD")).toArray())[0]?.total || 0;

  const successful = await countLatestPerOrder({ paymentStatus: "SUCCESS" });

  // Failed should ONLY be hard-fail statuses; refunded-success must never be part of this.
  const failed = await countLatestPerOrder({ paymentStatus: { $in: ["FAILED", "FAIL", "ERROR"] } });

  console.log(
    `Totals for tenant ${tenant}: totalLKR=${Number(totalLKR).toFixed(2)}, totalUSD=${Number(totalUSD).toFixed(2)}, thisMonthCount=${thisMonthCount}, thisMonthLKR=${Number(thisMonthAmountLKR).toFixed(2)}, thisMonthUSD=${Number(thisMonthAmountUSD).toFixed(2)}, transactions=${total}, successful=${successful}`
  );

  const stats = {
    totalTransactions: total,
    successfulTransactions: successful,
    failedTransactions: failed,
    totalAmountLKR: Number(totalLKR).toFixed(2),
    totalAmountUSD: Number(totalUSD).toFixed(2),
    thisMonthCount,
    thisMonthAmountLKR: Number(thisMonthAmountLKR).toFixed(2),
    thisMonthAmountUSD: Number(thisMonthAmountUSD).toFixed(2),
  };

  return { transactions: enriched, total, stats };
}

async function refundPayment_services(hostname, { uuid, amount, currency }) {
  const tenant = extractTenant(hostname);
  const collection = await getPaymentCollection(tenant);

  if (!uuid || !amount || !currency) {
    throw new Error("Missing uuid, amount, or currency");
  }

  const refundAmount = parseFloat(amount);
  if (isNaN(refundAmount) || refundAmount <= 0) {
    throw new Error("Invalid refund amount");
  }

  const curr = currency.toUpperCase();
  uuid = uuid.trim();

  const originalPayment = await collection.findOne({ uuid });
  if (!originalPayment) throw new Error(`Payment with uuid ${uuid} not found`);

  if (originalPayment.currency !== curr) {
    throw new Error(
      `Currency mismatch: ${originalPayment.currency} vs ${curr}`
    );
  }

  const originalAmount = parseFloat(originalPayment.amount);
  if (refundAmount > originalAmount) {
    throw new Error(`Refund (${refundAmount}) > original (${originalAmount})`);
  }

  const credentials = getGatewayCredentials(curr);
  if (!credentials) throw new Error(`No gateway for ${curr}`);

  let gatewayResult;
  try {
    gatewayResult = await refundService.processRefund(
      originalPayment.orderId,
      refundAmount,
      curr
    );
  } catch (err) {
    console.error("Refund gateway call failed:", err);
    throw new Error(
      err.message || "Refund request failed (network/gateway error)"
    );
  }
  const totalRefundedAmount =
    gatewayResult.raw?.order?.totalRefundedAmount || 0;

  if (!gatewayResult.success) {
    const gatewayError = gatewayResult.error || {};
    const errMsg =
      gatewayError.explanation ||
      gatewayError.cause ||
      gatewayError.message ||
      "Refund failed (gateway error)";

    console.error(`Refund failed for uuid ${uuid}: ${errMsg}`);
    const errorResponse = new Error(errMsg);
    errorResponse.gateway = gatewayError;
    throw errorResponse;
  }
  const refundEntry = {
    refundAmount,
    refundCurrency: curr,
    totalRefundedAmount,
    refundStatus: "REFUNDED",
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
  const existingKeys = Object.keys(originalPayment).filter((k) =>
    /^refund-\d+$/.test(k)
  );
  const nextIdx = existingKeys.length
    ? Math.max(...existingKeys.map((k) => Number(k.split("-")[1]))) + 1
    : 1;
  const nextField = `refund-${nextIdx}`;

  await collection.updateOne(
    { uuid },
    {
      $set: {
        [nextField]: refundEntry,
        updatedAt: new Date(),
        latestTotalRefunded: totalRefundedAmount,
        latestRefundId: gatewayResult.transactionId || null,
      },
    }
  );

  return {
    success: true,
    message: "Refund successful",
    uuid,
    orderId: originalPayment.orderId,
    originalAmount,
    refundAmount,
    totalRefundedAmount,
    currency: curr,
    transactionId: gatewayResult.transactionId,
    refundId: gatewayResult.transactionId,
    gatewayCode: gatewayResult.gatewayCode,
    result: gatewayResult.result,
  };
}

module.exports = {
  checkHealth,
  fetchPayments,
  refundPayment_services,
};
