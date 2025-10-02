const express = require('express');
const { downloadReceipt } = require('../controllers/receiptController');

const router = express.Router();

// Public route to generate PDF
router.get('/download/:orderId', downloadReceipt);

module.exports = router;
