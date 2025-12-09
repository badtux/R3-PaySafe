const express = require('express');
const router = express.Router();
const { healthCheck, getPayments, refundPayment } = require('../controllers/paymentController');



router.get('/transactions', healthCheck);
router.get('/payments', getPayments);
router.post('/refund', refundPayment);

module.exports = router;

