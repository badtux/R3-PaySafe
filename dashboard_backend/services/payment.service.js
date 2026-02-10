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

  // Base filter
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

  // Make a dedicated stats filter.
  // If caller provided status, respect it; otherwise default stats to SUCCESS (matches UI behavior).
  const statsFilter = {
    ...baseFilter,
    paymentStatus: status || 'SUCCESS'
  };

  // FIXED: Correct syntax for dynamic sort
  let sortOption = { createdAt: -1 };
  if (sort && sort.includes(":")) {
    const [field, dir] = sort.split(":");
    if (field && ["1", "-1"].includes(dir)) {
      sortOption = { [field]: parseInt(dir) };  // ← Fixed: one bracket only
    }
  }

  let transactions = [];
  let total = 0;

  if (isRefundOnly) {
    console.log('REFUND-ONLY MODE ON');

    const pipeline = [
      { $match: baseFilter },
      {
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
      },
      { $match: { refundCount: { $gt: 0 } } },
      { $sort: sortOption },
      { $skip: skip },
      { $limit: parseInt(limit) },
    ];

    transactions = await collection.aggregate(pipeline).toArray();

    total = (await collection
      .aggregate([
        { $match: baseFilter },
        {
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
        },
        { $match: { refundCount: { $gt: 0 } } },
        { $count: "total" },
      ])
      .toArray())[0]?.total || 0;
  } else {
    transactions = await collection
      .find(baseFilter)
      .sort(sortOption)
      .skip(skip)
      .limit(parseInt(limit))
      .toArray();

    total = await collection.countDocuments(baseFilter);
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
      latestTotalRefunded: latestRefund?.totalRefundedAmount ?? 0,
      latestRefundId: latestRefund?.refundTransactionId ?? null,
    };
  });

  // Stats: sum amounts correctly even if stored as strings
  const sumAmountPipeline = (currency) => ([
    { $match: { ...statsFilter, currency } },
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

  const totalLKR = (await collection.aggregate(sumAmountPipeline("LKR")).toArray())[0]?.total || 0;
  const totalUSD = (await collection.aggregate(sumAmountPipeline("USD")).toArray())[0]?.total || 0;

  const successful = await collection.countDocuments({
    ...baseFilter,
    paymentStatus: "SUCCESS",
  });

  console.log(
    `Totals for tenant ${tenant}: totalLKR=${Number(totalLKR).toFixed(2)}, totalUSD=${Number(totalUSD).toFixed(2)}, transactions=${total}, successful=${successful}`
  );

  const stats = {
    totalTransactions: total,
    successfulTransactions: successful,
    totalAmountLKR: Number(totalLKR).toFixed(2),
    totalAmountUSD: Number(totalUSD).toFixed(2),
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
