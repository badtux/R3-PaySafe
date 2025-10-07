const express = require('express');
const router = express.Router();
const { healthCheck, getPayments, exportPayments } = require('../controllers/paymentController');

// Routes for transactions, payments, and export with dynamic :db parameter
router.get('/:db/transactions', healthCheck);
router.get('/:db/payments', getPayments);
router.get('/:db/payments/export', exportPayments);

module.exports = router;