const { getPaymentCollection } = require('../config/db');

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
    bank:String,
    updatedAt: Date
};

module.exports = {
    getPaymentCollection: (dbName) => getPaymentCollection(dbName), // Pass dbName to getPaymentCollection
    schema: paymentSchema
};