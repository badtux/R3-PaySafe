const express = require('express');
const router = express.Router();
const { healthCheck, getPayments, exportPayments, refundPayment } = require('../controllers/paymentController');



router.get('/transactions', healthCheck);
router.get('/payments', getPayments);
router.get('/payments/export', exportPayments);
router.post('/refund', refundPayment);

module.exports = router;

