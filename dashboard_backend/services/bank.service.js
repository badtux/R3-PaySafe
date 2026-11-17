const axios = require('axios');
const https = require('https');
const crypto = require('crypto');
const { getPaymentCollection } = require('../models/Payment');

function extractTenant(hostname) {
  if (!hostname) return 'malkey';
  const tenant = hostname.split('.')[0];
  if (!tenant || tenant === 'localhost' || tenant === 'go') {
    return 'malkey';
  }
  return tenant;
}

/**
 * Fetch order details directly from Mastercard MPGS Bank API
 */
async function fetchBankOrder(orderId, options = {}) {
  const cleanOrderId = String(orderId || '').trim();
  if (!cleanOrderId) {
    throw new Error('Order ID or Reference is required');
  }

  const domain = process.env.MPGS_DOMAIN || 'cbcmpgs.gateway.mastercard.com';
  const apiVersion = process.env.MPGS_API_VERSION || '100';
  const merchantId = options.merchantId || process.env.MPGS_MERCHANT_ID || 'MALKEYRENLKR';
  const apiUsername = options.apiUsername || process.env.MPGS_API_USERNAME || 'merchant.MALKEYRENLKR';
  const apiPassword = options.apiPassword || process.env.MPGS_API_PASSWORD || '8ac724a6d1a9b99f4060c808142d47c6';

  const url = `https://${domain}/api/rest/version/${apiVersion}/merchant/${merchantId}/order/${cleanOrderId}`;
  const auth = Buffer.from(`${apiUsername}:${apiPassword}`).toString('base64');

  console.log(`[BankService] Querying Bank API: ${url} (Merchant: ${merchantId})`);

  try {
    const response = await axios.get(url, {
      headers: {
        Authorization: `Basic ${auth}`,
        Accept: 'application/json',
      },
      timeout: 25000,
      httpsAgent: new https.Agent({ rejectUnauthorized: true }),
    });

    const data = response.data;
    if (!data || data.result === 'ERROR') {
      const errMsg = data?.error?.explanation || data?.error?.cause || 'Bank API returned an error';
      throw new Error(errMsg);
    }

    return data;
  } catch (error) {
    const errData = error.response?.data;
    const explanation = errData?.error?.explanation || errData?.error?.cause || error.message;
    console.error(`[BankService] Error fetching order ${cleanOrderId}:`, explanation);
    throw new Error(explanation);
  }
}

/**
 * Transform MPGS order response into a Plutos payment database document and save it
 */
async function copyBankOrderToDatabase(orderData, tenant = 'malkey') {
  if (!orderData || !orderData.id) {
    throw new Error('Invalid order data from Bank API');
  }

  const collection = await getPaymentCollection(tenant);

  const amount = parseFloat(orderData.amount ?? orderData.merchantAmount ?? 0);
  const currency = String(orderData.currency ?? orderData.merchantCurrency ?? 'LKR').toUpperCase();

  const txList = Array.isArray(orderData.transaction) ? orderData.transaction : [];
  const rootCustomer = orderData.customer || {};
  const txCustomer = txList.find(t => t.customer?.firstName || t.customer?.lastName || t.customer?.email)?.customer || {};

  // Card Holder Name
  const cardHolderName = orderData.sourceOfFunds?.provided?.card?.nameOnCard ||
    txList.find(t => t.sourceOfFunds?.provided?.card?.nameOnCard)?.sourceOfFunds?.provided?.card?.nameOnCard;

  let fullName = cardHolderName || [
    txCustomer.firstName || rootCustomer.firstName,
    txCustomer.lastName || rootCustomer.lastName
  ].filter(Boolean).join(' ');

  if (!fullName || fullName.trim() === '') {
    fullName = 'N/A';
  }

  // Customer Email Extraction
  let email = rootCustomer.email || txCustomer.email || '';
  if (!email) {
    const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/;
    const match = (rootCustomer.lastName || '').match(emailRegex) ||
                  (rootCustomer.firstName || '').match(emailRegex) ||
                  (txCustomer.lastName || '').match(emailRegex) ||
                  (txCustomer.firstName || '').match(emailRegex);
    if (match) {
      email = match[0];
    } else if (rootCustomer.lastName && rootCustomer.lastName.includes('@')) {
      email = rootCustomer.lastName;
    } else {
      email = `${orderData.id}@malkey.lk`;
    }
  }

  // Card Information
  const cardInfo = orderData.sourceOfFunds?.provided?.card ||
    txList.find(t => t.sourceOfFunds?.provided?.card)?.sourceOfFunds?.provided?.card || {};
  const cardBrand = cardInfo.brand || cardInfo.scheme || 'VISA';
  const cardNumber = cardInfo.number || '';
  const fundingMethod = cardInfo.fundingMethod || '';

  // Reference / Description
  const description = orderData.description ||
    txList.find(t => t.order?.description)?.order?.description ||
    orderData.id;

  // Transaction ID
  // Prioritize actual transaction references over internal sequence IDs (e.g. "1")
  const root3dsTxId = orderData.authentication?.['3ds']?.transactionId;
  const acquirerTxId = txList.find(t => t.transaction?.acquirer?.transactionId)?.transaction?.acquirer?.transactionId;
  const attempt3dsTxId = txList.find(t => t.authentication?.['3ds']?.transactionId)?.authentication?.['3ds']?.transactionId;
  const paymentTx = txList.find(t => t.transaction?.type === 'PAYMENT') || txList[0];
  const txExplicitId = paymentTx?.transaction?.transactionId;

  const transactionId = root3dsTxId || acquirerTxId || attempt3dsTxId || txExplicitId || orderData.id;

  // Payment Status
  let paymentStatus = 'PENDING';
  const resUpper = String(orderData.result || '').toUpperCase();
  if (resUpper === 'SUCCESS') {
    paymentStatus = 'SUCCESS';
  } else if (resUpper === 'FAILURE' || resUpper === 'FAILED' || resUpper === 'ERROR') {
    paymentStatus = 'FAILED';
  } else {
    paymentStatus = resUpper || 'PENDING';
  }

  // Map transaction attempts for Attempts panel
  const attempts = txList.map((tx, idx) => {
    const finalResult = String(tx.result || 'UNKNOWN').toUpperCase();
    return {
      type: tx.transaction?.type || tx.type || (idx === 0 ? 'AUTHENTICATION' : 'PAYMENT'),
      finalResult: finalResult === 'FAIL' ? 'FAILED' : finalResult,
      gatewayCode: tx.response?.gatewayCode || null,
      timeOfRecord: tx.timeOfRecord || tx.time || null,
      timeOfLastUpdate: tx.timeOfLastUpdate || null,
      amount: tx.transaction?.amount ?? tx.order?.amount ?? amount,
      currency: tx.transaction?.currency ?? tx.order?.currency ?? currency,
      mpgsTransactionId: tx.transaction?.id || null,
      acquirerCode: tx.response?.acquirerCode || tx.transaction?.acquirer?.id || null,
      acquirerMessage: tx.response?.acquirerMessage || tx.response?.gatewayRecommendation || null,
      acquirerTransactionId: tx.transaction?.acquirer?.transactionId || null,
      authorizationCode: tx.transaction?.authorizationCode || null,
      order: tx.order || null,
      response: tx.response || null,
      transaction: tx.transaction || null,
    };
  });

  const now = new Date();
  const createdAt = orderData.creationTime ? new Date(orderData.creationTime) : now;
  const updatedAt = orderData.lastUpdatedTime ? new Date(orderData.lastUpdatedTime) : now;

  const orderDoc = {
    orderId: String(orderData.id),
    amount,
    currency,
    description: String(description),
    merchantId: orderData.merchant || 'MALKEYRENLKR',
    merchant: orderData.merchant || 'MALKEYRENLKR',
    updatedAt,
    cardBrand,
    cardNumber,
    fundingMethord: fundingMethod,
    nameOnCard: fullName,
    email,
    paymentStatus,
    transactionId: String(transactionId),
    bank: 'CBC MPGS',
    cron: true,          // Required for malkey tenant filter
    bankSynced: true,    // Marked with Orange Notification Icon on dashboard
    copiedFromBank: true,
    attempts,
    rawBankData: orderData,
  };

  // Upsert to database (never duplicate orderId)
  await collection.updateOne(
    { orderId: String(orderData.id) },
    {
      $set: orderDoc,
      $setOnInsert: {
        uuid: crypto.randomUUID(),
        createdAt: orderData.creationTime ? new Date(orderData.creationTime) : now,
      },
    },
    { upsert: true }
  );

  const savedDoc = await collection.findOne({ orderId: String(orderData.id) });
  console.log(`[BankService] Successfully copied order ${orderData.id} to database '${tenant}_paysafe' with bankSynced=true`);

  return savedDoc;
}

/**
 * Main function: Query bank API by reference/orderId and copy into DB
 */
async function queryAndSyncBankOrder(hostname, referenceOrOrderId) {
  const tenant = extractTenant(hostname);
  const cleanId = String(referenceOrOrderId || '').trim();

  if (!cleanId) {
    throw new Error('Please provide an Order ID or Reference');
  }

  const collection = await getPaymentCollection(tenant);

  // Check if order already exists in database
  const existingOrder = await collection.findOne({
    $or: [
      { orderId: cleanId },
      { orderId: Number(cleanId) || cleanId }
    ]
  });

  if (existingOrder) {
    console.log(`[BankService] Order ${cleanId} already exists in database '${tenant}_paysafe'`);
    return {
      success: true,
      alreadyExists: true,
      message: `This order is already in the database`,
      transaction: existingOrder,
    };
  }

  // 1. Fetch from bank API
  const orderData = await fetchBankOrder(cleanId);

  // 2. Save / upsert into MongoDB
  const savedDoc = await copyBankOrderToDatabase(orderData, tenant);

  return {
    success: true,
    alreadyExists: false,
    message: `Order ${savedDoc.orderId} found in Bank API and copied to database.`,
    transaction: savedDoc,
  };
}

module.exports = {
  extractTenant,
  fetchBankOrder,
  copyBankOrderToDatabase,
  queryAndSyncBankOrder,
};
