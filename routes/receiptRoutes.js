const express = require('express');
const pdfController = require('../controllers/receiptController');

const router = express.Router();

// PDF download route (public, no authentication)
router.get('/download/:paymentId', (req, res, next) => {

  pdfController.generatePDF(req, res, next);
});

router.get("/test", (req, res) => {
  res.send("PDF route is working!");
});


module.exports = router;