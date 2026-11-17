const express = require('express');
const router = express.Router();
const { healthCheck, getPayments, refundPayment, queryBankOrder } = require('../controllers/paymentController');

router.get('/transactions', healthCheck);
router.get('/payments', getPayments);
router.post('/refund', refundPayment);
router.post('/bank/query-order', queryBankOrder);
router.get('/bank/query-order', queryBankOrder);

module.exports = router;

