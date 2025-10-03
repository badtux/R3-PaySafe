// routes/paymentRoutes.js
const express = require('express');
const router = express.Router();
const { healthCheck, getPayments, exportPayments } = require('../controllers/paymentController');

router.get(':db/transactions', healthCheck);
router.get(':db/payments', getPayments);
router.get(':db/payments/export', exportPayments);

module.exports = router;