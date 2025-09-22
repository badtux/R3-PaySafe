// routes/paymentRoutes.js
const express = require('express');
const router = express.Router();
const { healthCheck, getPayments, exportPayments } = require('../controllers/paymentController');

router.get('/health', healthCheck);
router.get('/payments', getPayments);
router.get('/payments/export', exportPayments);

module.exports = router;