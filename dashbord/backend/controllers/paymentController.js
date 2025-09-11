// controllers/paymentController.js
const { getCollection } = require('../models/Payment');

const healthCheck = async (req, res) => {
    try {
        const collection = getCollection();
        const collections = await collection.db.listCollections().toArray();
        const collectionExists = collections.some(c => c.name === 'pyment');
        const documentCount = collectionExists ? await collection.countDocuments() : 0;
        res.status(200).json({
            status: 'ok',
            connected: true,
            database: 'mulky',
            collection: 'pyment',
            collectionExists,
            documentCount
        });
    } catch (error) {
        console.error('Health check error:', error);
        res.status(500).json({ status: 'error', message: 'Failed to check database status' });
    }
};

const getPayments = async (req, res) => {
    const { from, to, status, search, page = 1, limit = 10 } = req.query;
    let filter = {};

    console.log('Request query:', req.query);

    // Apply date filters only if provided
    if (from || to) {
        filter.createdAt = {};
        if (from) {
            filter.createdAt.$gte = new Date(from);
        }
        if (to) {
            const toDate = new Date(to);
            toDate.setHours(23, 59, 59, 999);
            filter.createdAt.$lte = toDate;
        }
    }
    if (status) {
        filter.paymentStatus = status;
    }
    if (search) {
        filter.$or = [
            { orderId: { $regex: search, $options: 'i' } },
            { email: { $regex: search, $options: 'i' } },
            { description: { $regex: search, $options: 'i' } }
        ];
    }

    console.log('Applied filter:', filter);

    try {
        const collection = getCollection();
        const skip = (parseInt(page) - 1) * parseInt(limit);
        const transactions = await collection.find(filter).skip(skip).limit(parseInt(limit)).toArray();
        console.log('Fetched transactions:', transactions);
        const total = await collection.countDocuments(filter);
        console.log('Total documents:', total);
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

        res.status(200).json({ transactions, total, stats });
    } catch (error) {
        console.error('Error fetching payments:', error);
        res.status(500).json({ error: 'Failed to fetch payments' });
    }
};

const exportPayments = async (req, res) => {
    const { from, to, status, search } = req.query;
    let filter = {};

    if (from || to) {
        filter.createdAt = {};
        if (from) {
            filter.createdAt.$gte = new Date(from);
        }
        if (to) {
            const toDate = new Date(to);
            toDate.setHours(23, 59, 59, 999);
            filter.createdAt.$lte = toDate;
        }
    }
    if (status) {
        filter.paymentStatus = status;
    }
    if (search) {
        filter.$or = [
            { orderId: { $regex: search, $options: 'i' } },
            { email: { $regex: search, $options: 'i' } },
            { description: { $regex: search, $options: 'i' } }
        ];
    }

    try {
        const collection = getCollection();
        const transactions = await collection.find(filter).toArray();
        res.status(200).json(transactions);
    } catch (error) {
        console.error('Error exporting payments:', error);
        res.status(500).json({ error: 'Failed to export payments' });
    }
};

module.exports = {
    healthCheck,
    getPayments,
    exportPayments
};