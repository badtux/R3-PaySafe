const express = require('express');
const { downloadReceipt } = require('../controllers/receiptController');

const router = express.Router();


router.get('/download/:db/:orderId', downloadReceipt);

module.exports = router;
