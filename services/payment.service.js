
const { getPaymentCollection } = require('../models/Payment');


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
    const { from, to, status, search, page = 1, limit = 10 } = query;

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
    const skip = (parseInt(page) - 1) * parseInt(limit);

    const transactions = await collection.find(filter).skip(skip).limit(parseInt(limit)).toArray();
    const total = await collection.countDocuments(filter);
    const successful = await collection.countDocuments({ ...filter, paymentStatus: 'SUCCESS' });

    const totalLKRResult = await collection.aggregate([
        { $match: { ...filter, currency: 'LKR' } },
        { $group: { _id: null, total: { $sum: '$amount' } } }
    ]).toArray();
    const totalLKR = totalLKRResult[0]?.total || 0;

    const totalUSDResult = await collection.aggregate([
        { $match: { ...filter, currency: 'USD' } },
        { $group: { _id: null, total: { $sum: '$amount' } } }
    ]).toArray();
    const totalUSD = totalUSDResult[0]?.total || 0;

    const stats = {
        totalTransactions: total,
        successfulTransactions: successful,
        totalAmountLKR: totalLKR.toFixed(2),
        totalAmountUSD: totalUSD.toFixed(2)
    };

    return { transactions, total, stats };
}


async function exportPayments(hostname, query) {
    const tenant = extractTenant(hostname);
    const { from, to, status, search } = query;

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
    const transactions = await collection.find(filter).toArray();
    return transactions;
}

module.exports = {
    checkHealth,
    fetchPayments,
    exportPayments
};
