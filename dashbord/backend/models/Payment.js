// models/Payment.js
const { getPaymentCollection } = require('../config/db');

// Expected document structure for reference
const paymentSchema = {
    orderId: String,
    uuid: String,
    amount: Number,
    currency: String,
    description: String,
    merchantId: String,
    sessionId: String,
    createdAt: Date,
    cardBrand: String,
    email: String,
    fundingMethord: String,
    merchant: String,
    nameOnCard: String,
    paymentStatus: String,
    transactionId: String,
    updatedAt: Date
};

module.exports = {
    getCollection: () => getPaymentCollection(),
    schema: paymentSchema
};